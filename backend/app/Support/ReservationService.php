<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReservationService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly AuditLogger $audit,
        private readonly ReservationNotificationService $notifications,
    ) {}

    /** @return array{reservation: Reservation, token: string, replayed: bool} */
    public function create(Branch $branch, Product $product, array $contact, string $idempotencyKey, ?User $actor = null): array
    {
        $idempotencyKey = trim($idempotencyKey);
        if (! Str::isUuid($idempotencyKey)) {
            throw ValidationException::withMessages(['idempotency_key' => 'A valid idempotency key is required.']);
        }

        $quantity = Decimal::trim(bcadd((string) $contact['quantity'], '0', 4));
        $normalized = [
            'product_id' => $product->id,
            'quantity' => $quantity,
            'customer_name' => trim($contact['name']),
            'customer_email' => isset($contact['email']) ? mb_strtolower(trim($contact['email'])) : null,
            'customer_phone' => isset($contact['phone']) ? trim($contact['phone']) : null,
        ];
        $requestHash = $this->hash($normalized);
        $token = hash_hmac('sha256', $branch->id.'|'.$idempotencyKey, (string) config('app.key'));

        $existing = Reservation::query()->where('branch_id', $branch->id)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $this->replay($existing, $requestHash, $token);
        }

        $this->expireForProduct($branch, $product);

        try {
            $result = DB::transaction(function () use ($branch, $product, $normalized, $requestHash, $idempotencyKey, $token, $actor): array {
                $currentProduct = Product::query()->where('branch_id', $branch->id)->whereKey($product->id)
                    ->with('baseUnit')->lockForUpdate()->firstOrFail();
                if (! $currentProduct->is_active || ! $currentProduct->is_public || ! $currentProduct->track_stock || $branch->status !== Branch::STATUS_ACTIVE) {
                    throw ValidationException::withMessages(['product' => 'This product is not currently available for reservation.']);
                }
                $quantity = $normalized['quantity'];

                $existing = Reservation::query()->where('branch_id', $branch->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing) {
                    return $this->replay($existing, $requestHash, $token);
                }

                if (! $currentProduct->baseUnit?->allow_decimal && bccomp($quantity, bcadd($quantity, '0', 0), 4) !== 0) {
                    throw ValidationException::withMessages(['quantity' => 'This product can only be reserved in whole units.']);
                }

                $level = StockLevel::query()->where('branch_id', $branch->id)->where('product_id', $currentProduct->id)->lockForUpdate()->first();
                if (! $level) {
                    throw ValidationException::withMessages(['product' => 'This product is not currently available for reservation.']);
                }
                $available = bcsub((string) $level->quantity_on_hand, (string) $level->quantity_reserved, 4);
                if (bccomp($available, $quantity, 4) < 0) {
                    throw new ConflictHttpException('There is not enough unreserved stock for that quantity.');
                }

                $amountMinor = $this->multiplyPrice($currentProduct->selling_price_minor, $quantity);
                $expiresAt = now()->addMinutes(max(1, min((int) config('catalog.reservation_expiration_minutes', 15), 1440)));
                $reservation = Reservation::create([
                    'branch_id' => $branch->id,
                    'product_id' => $currentProduct->id,
                    'unit_id' => $currentProduct->base_unit_id,
                    'reservation_number' => $this->numbers->allocate($branch, 'reservation'),
                    'product_name' => $currentProduct->name,
                    'quantity' => $quantity,
                    'amount_minor' => $amountMinor,
                    'currency_code' => $currentProduct->currency_code,
                    'customer_name' => $normalized['customer_name'],
                    'customer_email' => $normalized['customer_email'],
                    'customer_phone' => $normalized['customer_phone'],
                    'token_hash' => hash('sha256', $token),
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'status' => Reservation::STATUS_AWAITING_PAYMENT,
                    'expires_at' => $expiresAt,
                    'actor_id' => $actor?->id,
                ]);
                $level->update(['quantity_reserved' => bcadd((string) $level->quantity_reserved, $quantity, 4)]);
                $this->audit->log('reservation.created', $reservation, null, [
                    'reservation_number' => $reservation->reservation_number,
                    'product_id' => $currentProduct->id,
                    'quantity' => $quantity,
                    'amount_minor' => $amountMinor,
                    'status' => $reservation->status,
                ], $branch->id, $actor);

                return ['reservation' => $reservation, 'token' => $token, 'replayed' => false];
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Reservation::query()->where('branch_id', $branch->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $this->replay($existing, $requestHash, $token);
            }
            throw new ConflictHttpException('The reservation conflicted with another concurrent request.', $exception);
        }

        if (! $result['replayed']) {
            $this->notifications->queueCreated($result['reservation']);
        }

        return $result;
    }

    public function findByToken(string $token): Reservation
    {
        return Reservation::query()->where('token_hash', hash('sha256', $token))->firstOrFail();
    }

    public function cancel(string $token): Reservation
    {
        $reservation = $this->findByToken($token);

        return DB::transaction(function () use ($reservation): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if ($reservation->status === Reservation::STATUS_CANCELLED || $reservation->status === Reservation::STATUS_EXPIRED) {
                return $reservation;
            }
            if ($reservation->status !== Reservation::STATUS_AWAITING_PAYMENT) {
                throw new ConflictHttpException('This reservation can no longer be cancelled online.');
            }
            if ($reservation->expires_at->isPast()) {
                $this->releaseHold($reservation, Reservation::STATUS_EXPIRED);

                return $reservation->refresh();
            }
            $this->releaseHold($reservation, Reservation::STATUS_CANCELLED);

            return $reservation->refresh();
        });
    }

    public function confirmPayment(Reservation $reservation): bool
    {
        $confirmed = DB::transaction(function () use ($reservation): bool {
            $current = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if ($current->status === Reservation::STATUS_CONFIRMED) {
                return true;
            }
            if ($current->status !== Reservation::STATUS_AWAITING_PAYMENT) {
                return false;
            }

            $current->update(['status' => Reservation::STATUS_CONFIRMED, 'confirmed_at' => now()]);
            app(AuditLogger::class)->log('reservation.payment.confirmed', $current, [
                'status' => Reservation::STATUS_AWAITING_PAYMENT,
            ], [
                'status' => Reservation::STATUS_CONFIRMED,
                'reservation_number' => $current->reservation_number,
            ], $current->branch_id);

            return true;
        }, 3);

        if ($confirmed) {
            $this->notifications->queuePaymentConfirmed($reservation);
        }

        return $confirmed;
    }

    public function cancelAfterPaymentFailure(Reservation $reservation): void
    {
        DB::transaction(function () use ($reservation): void {
            $current = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if ($current->status === Reservation::STATUS_AWAITING_PAYMENT) {
                $this->releaseHold($current, Reservation::STATUS_CANCELLED);
            }
        }, 3);
    }

    public function markRefunded(Reservation $reservation): void
    {
        DB::transaction(function () use ($reservation): void {
            $current = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if (in_array($current->status, [Reservation::STATUS_AWAITING_PAYMENT, Reservation::STATUS_CONFIRMED], true)) {
                $this->releaseHold($current, Reservation::STATUS_REFUNDED);
            } elseif ($current->status === Reservation::STATUS_PICKED_UP) {
                $current->update(['status' => Reservation::STATUS_REFUNDED]);
                app(AuditLogger::class)->log('reservation.refunded_after_pickup', $current, [
                    'status' => Reservation::STATUS_PICKED_UP,
                ], ['status' => Reservation::STATUS_REFUNDED], $current->branch_id);
            }
        }, 3);
    }

    public function expireDue(int $limit = 250): int
    {
        $ids = Reservation::query()->where('status', Reservation::STATUS_AWAITING_PAYMENT)
            ->where('expires_at', '<=', now())->orderBy('expires_at')->limit($limit)->pluck('id');
        $expired = 0;
        foreach ($ids as $id) {
            if ($this->expireOne((string) $id)) {
                $expired++;
            }
        }

        return $expired;
    }

    public function expireForProduct(Branch $branch, Product $product): void
    {
        $ids = Reservation::query()->where('branch_id', $branch->id)->where('product_id', $product->id)
            ->where('status', Reservation::STATUS_AWAITING_PAYMENT)->where('expires_at', '<=', now())->pluck('id');
        foreach ($ids as $id) {
            $this->expireOne((string) $id);
        }
    }

    /** @return array{reservation: Reservation, token: string, replayed: bool} */
    private function replay(Reservation $reservation, string $requestHash, string $token): array
    {
        if (! hash_equals($reservation->request_hash, $requestHash)) {
            throw new ConflictHttpException('This idempotency key has already been used for a different reservation.');
        }

        return ['reservation' => $reservation, 'token' => $token, 'replayed' => true];
    }

    private function expireOne(string $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $reservation = Reservation::query()->lockForUpdate()->find($id);
            if (! $reservation || $reservation->status !== Reservation::STATUS_AWAITING_PAYMENT || $reservation->expires_at->isFuture()) {
                return false;
            }
            $this->releaseHold($reservation, Reservation::STATUS_EXPIRED);

            return true;
        }, 3);
    }

    private function releaseHold(Reservation $reservation, string $status): void
    {
        $level = StockLevel::query()->where('branch_id', $reservation->branch_id)
            ->where('product_id', $reservation->product_id)->lockForUpdate()->firstOrFail();
        $remaining = bcsub((string) $level->quantity_reserved, (string) $reservation->quantity, 4);
        if (bccomp($remaining, '0', 4) < 0) {
            throw new \LogicException('Reservation stock holds are inconsistent with the stock balance.');
        }
        $level->update(['quantity_reserved' => $remaining]);
        $timestamp = now();
        $reservation->update([
            'status' => $status,
            'cancelled_at' => $status === Reservation::STATUS_CANCELLED ? $timestamp : $reservation->cancelled_at,
            'expired_at' => $status === Reservation::STATUS_EXPIRED ? $timestamp : $reservation->expired_at,
        ]);
        app(AuditLogger::class)->log('reservation.'.$status, $reservation, ['status' => Reservation::STATUS_AWAITING_PAYMENT], [
            'status' => $status,
            'reservation_number' => $reservation->reservation_number,
        ], $reservation->branch_id);
    }

    private function multiplyPrice(int $unitPriceMinor, string $quantity): int
    {
        $total = bcmul((string) $unitPriceMinor, $quantity, 4);
        [$whole, $fraction] = array_pad(explode('.', $total, 2), 2, '0000');
        if ((int) str_pad($fraction, 4, '0')[0] >= 5) {
            $whole = bcadd($whole, '1', 0);
        }
        if (strlen($whole) > 18) {
            throw ValidationException::withMessages(['quantity' => 'The reservation amount is too large.']);
        }

        return (int) $whole;
    }

    private function hash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
