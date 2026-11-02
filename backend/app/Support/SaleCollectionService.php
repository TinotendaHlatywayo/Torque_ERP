<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class SaleCollectionService
{
    public function __construct(
        private readonly MoneyMovementService $movements,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function collect(Branch $branch, Sale $sale, User $actor, array $payload, string $idempotencyKey): Sale
    {
        if ($sale->branch_id !== $branch->id) {
            abort(404);
        }

        if ($sale->status === 'voided') {
            throw new ConflictHttpException('Collections cannot be recorded against a voided sale.');
        }

        $idempotencyKey = trim($idempotencyKey);

        if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'A non-empty Idempotency-Key header of at most 100 characters is required.',
            ]);
        }

        $amountMinor = (int) ($payload['amount_minor'] ?? 0);
        $method = (string) ($payload['method'] ?? '');
        $currencyCode = (string) ($payload['currency_code'] ?? '');
        $reference = isset($payload['reference']) && is_string($payload['reference']) ? trim($payload['reference']) : null;
        $accountId = (string) ($payload['payment_account_id'] ?? '');

        if ($amountMinor <= 0) {
            throw ValidationException::withMessages([
                'amount_minor' => 'The collection amount must be greater than zero.',
            ]);
        }

        $requestHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        $existingPayment = Payment::where('branch_id', $branch->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingPayment) {
            if (! hash_equals((string) $existingPayment->request_hash, $requestHash)) {
                throw new ConflictHttpException('The Idempotency-Key has already been used with a different collection payload.');
            }

            return $sale->load(['customer', 'items', 'payments', 'receipt']);
        }

        $outstanding = $sale->total_minor - $sale->paid_minor;

        if ($amountMinor > $outstanding) {
            throw ValidationException::withMessages([
                'amount_minor' => 'The collection amount cannot exceed the outstanding balance.',
            ]);
        }

        return DB::transaction(function () use ($branch, $sale, $actor, $amountMinor, $method, $currencyCode, $reference, $accountId, $idempotencyKey, $requestHash): Sale {
            $sale = Sale::where('branch_id', $branch->id)
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingPayment = Payment::where('branch_id', $branch->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingPayment) {
                if (! hash_equals((string) $existingPayment->request_hash, $requestHash)) {
                    throw new ConflictHttpException('The Idempotency-Key has already been used with a different collection payload.');
                }

                return $sale->load(['customer', 'items', 'payments', 'receipt']);
            }

            $outstanding = $sale->total_minor - $sale->paid_minor;

            if ($amountMinor > $outstanding) {
                throw ValidationException::withMessages([
                    'amount_minor' => 'The collection amount cannot exceed the outstanding balance.',
                ]);
            }

            if ($currencyCode !== $sale->currency_code) {
                throw ValidationException::withMessages([
                    'currency_code' => 'The collection currency must match the sale currency.',
                ]);
            }

            $account = PaymentAccount::where('branch_id', $branch->id)
                ->whereKey($accountId)
                ->firstOrFail();

            if ($account->currency_code !== $currencyCode) {
                throw ValidationException::withMessages([
                    'payment_account_id' => 'The payment account currency must match the collection currency.',
                ]);
            }

            $payment = Payment::create([
                'branch_id' => $branch->id,
                'sale_id' => $sale->id,
                'method' => $method,
                'amount_minor' => $amountMinor,
                'currency_code' => $currencyCode,
                'reference' => $reference,
                'status' => 'recorded',
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'actor_id' => $actor->id,
            ]);

            $this->movements->postMovement(
                $branch,
                $account,
                'adjustment_in',
                $amountMinor,
                $currencyCode,
                $reference,
                'Collection for sale '.$sale->sale_number,
                'receivable_collection',
                (string) $sale->id,
                $actor,
                'collection:'.$sale->id.':'.$payment->id,
            );

            $sale->paid_minor += $amountMinor;
            $sale->payment_status = $sale->paid_minor >= $sale->total_minor ? 'paid' : 'partial';
            $sale->save();

            $this->audit->log(
                'sale.payment.collected',
                $sale,
                null,
                [
                    'sale_number' => $sale->sale_number,
                    'amount_minor' => $amountMinor,
                    'currency_code' => $currencyCode,
                    'payment_status' => $sale->payment_status,
                ],
                $branch->id,
                $actor,
            );

            return $sale->load(['customer', 'items', 'payments', 'receipt']);
        }, 3);
    }
}
