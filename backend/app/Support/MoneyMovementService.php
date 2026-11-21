<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\MoneyMovement;
use App\Models\PaymentAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class MoneyMovementService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function postMovement(
        Branch $branch,
        PaymentAccount $account,
        string $movementType,
        int $amountMinor,
        string $currencyCode,
        ?string $reference,
        string $reason,
        ?string $source,
        ?string $sourceId,
        User $actor,
        string $idempotencyKey,
    ): MoneyMovement {
        if ($account->branch_id !== $branch->id) {
            abort(404);
        }

        if (! $account->is_active) {
            throw ValidationException::withMessages([
                'payment_account_id' => 'The payment account is archived.',
            ]);
        }

        if ($currencyCode !== $account->currency_code) {
            throw ValidationException::withMessages([
                'currency_code' => 'The movement currency must match the account currency.',
            ]);
        }

        if ($amountMinor <= 0) {
            throw ValidationException::withMessages([
                'amount_minor' => 'The movement amount must be greater than zero.',
            ]);
        }

        $direction = in_array($movementType, ['adjustment_out', 'sale_return_refund'], true) ? 'out' : 'in';
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required.',
            ]);
        }

        $payload = [
            'branch_id' => $branch->id,
            'payment_account_id' => $account->id,
            'movement_type' => $movementType,
            'amount_minor' => $amountMinor,
            'currency_code' => $currencyCode,
            'direction' => $direction,
            'source' => $source ?: 'manual',
            'source_id' => $sourceId,
            'reference' => $reference,
            'reason' => $reason,
        ];

        $requestHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);

        $existing = MoneyMovement::where('branch_id', $branch->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            if (! hash_equals($existing->request_hash, $requestHash)) {
                throw new ConflictHttpException('The Idempotency-Key has already been used with a different money movement.');
            }

            return $existing;
        }

        return DB::transaction(function () use ($branch, $account, $movementType, $amountMinor, $currencyCode, $direction, $source, $sourceId, $reference, $reason, $actor, $idempotencyKey, $requestHash): MoneyMovement {
            $existing = MoneyMovement::where('branch_id', $branch->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    throw new ConflictHttpException('The Idempotency-Key has already been used with a different money movement.');
                }

                return $existing;
            }

            $movement = MoneyMovement::create([
                'branch_id' => $branch->id,
                'payment_account_id' => $account->id,
                'movement_type' => $movementType,
                'amount_minor' => $amountMinor,
                'currency_code' => $currencyCode,
                'direction' => $direction,
                'source' => $source ?: 'manual',
                'source_id' => $sourceId,
                'reference' => $reference,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'actor_id' => $actor->id,
            ]);

            $this->audit->log(
                'money_movement.posted',
                $movement,
                null,
                [
                    'account_id' => $account->id,
                    'movement_type' => $movementType,
                    'amount_minor' => $amountMinor,
                    'currency_code' => $currencyCode,
                    'direction' => $direction,
                ],
                $branch->id,
                $actor,
            );

            return $movement;
        }, 3);
    }

    public function transfer(
        Branch $branch,
        PaymentAccount $from,
        PaymentAccount $to,
        int $amountMinor,
        string $currencyCode,
        string $reason,
        ?string $reference,
        User $actor,
        string $idempotencyKey,
    ): array {
        if ($from->branch_id !== $branch->id || $to->branch_id !== $branch->id) {
            abort(404);
        }

        if ($from->id === $to->id) {
            throw ValidationException::withMessages([
                'to_account_id' => 'Transfer accounts must be different.',
            ]);
        }

        if (! $from->is_active || ! $to->is_active) {
            throw ValidationException::withMessages([
                'payment_account_id' => 'Both payment accounts must be active.',
            ]);
        }

        if ($currencyCode !== $from->currency_code || $currencyCode !== $to->currency_code) {
            throw ValidationException::withMessages([
                'currency_code' => 'Transfers require both accounts to use the same currency.',
            ]);
        }

        if ($amountMinor <= 0) {
            throw ValidationException::withMessages([
                'amount_minor' => 'The transfer amount must be greater than zero.',
            ]);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required.',
            ]);
        }

        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);
        $transferKey = 'transfer:'.substr(hash('sha256', $branch->id.'|'.$idempotencyKey), 0, 32);
        $outgoingKey = $transferKey.':out';
        $incomingKey = $transferKey.':in';

        $payload = [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount_minor' => $amountMinor,
            'currency_code' => $currencyCode,
            'reason' => $reason,
            'reference' => $reference,
        ];

        $requestHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        $existingOutgoing = MoneyMovement::where('branch_id', $branch->id)
            ->where('idempotency_key', $outgoingKey)
            ->where('movement_type', 'transfer_out')
            ->first();

        if ($existingOutgoing) {
            if (! hash_equals($existingOutgoing->request_hash, $requestHash)) {
                throw new ConflictHttpException('The Idempotency-Key has already been used with a different transfer.');
            }

            $incoming = MoneyMovement::where('transfer_group_id', $existingOutgoing->transfer_group_id)
                ->where('movement_type', 'transfer_in')
                ->first();

            return ['outgoing' => $existingOutgoing, 'incoming' => $incoming];
        }

        return DB::transaction(function () use ($branch, $from, $to, $amountMinor, $currencyCode, $reason, $reference, $actor, $outgoingKey, $incomingKey, $requestHash): array {
            $existingOutgoing = MoneyMovement::where('branch_id', $branch->id)
                ->where('idempotency_key', $outgoingKey)
                ->where('movement_type', 'transfer_out')
                ->lockForUpdate()
                ->first();

            if ($existingOutgoing) {
                if (! hash_equals($existingOutgoing->request_hash, $requestHash)) {
                    throw new ConflictHttpException('The Idempotency-Key has already been used with a different transfer.');
                }

                $incoming = MoneyMovement::where('transfer_group_id', $existingOutgoing->transfer_group_id)
                    ->where('movement_type', 'transfer_in')
                    ->first();

                return ['outgoing' => $existingOutgoing, 'incoming' => $incoming];
            }

            $groupId = (string) Str::uuid();

            $outgoing = MoneyMovement::create([
                'branch_id' => $branch->id,
                'payment_account_id' => $from->id,
                'movement_type' => 'transfer_out',
                'amount_minor' => $amountMinor,
                'currency_code' => $currencyCode,
                'direction' => 'out',
                'source' => 'transfer',
                'source_id' => $groupId,
                'reference' => $reference,
                'reason' => $reason,
                'transfer_group_id' => $groupId,
                'idempotency_key' => $outgoingKey,
                'request_hash' => $requestHash,
                'actor_id' => $actor->id,
            ]);

            $incoming = MoneyMovement::create([
                'branch_id' => $branch->id,
                'payment_account_id' => $to->id,
                'movement_type' => 'transfer_in',
                'amount_minor' => $amountMinor,
                'currency_code' => $currencyCode,
                'direction' => 'in',
                'source' => 'transfer',
                'source_id' => $groupId,
                'reference' => $reference,
                'reason' => $reason,
                'related_movement_id' => $outgoing->id,
                'transfer_group_id' => $groupId,
                'idempotency_key' => $incomingKey,
                'request_hash' => $requestHash,
                'actor_id' => $actor->id,
            ]);

            $outgoing->update(['related_movement_id' => $incoming->id]);

            return ['outgoing' => $outgoing->refresh(), 'incoming' => $incoming->refresh()];
        }, 3);
    }

    public function reverse(Branch $branch, MoneyMovement $movement, string $reason, User $actor, string $idempotencyKey): MoneyMovement
    {
        if ($movement->branch_id !== $branch->id) {
            abort(404);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required.',
            ]);
        }

        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);

        $existing = MoneyMovement::where('branch_id', $branch->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        $requestHash = hash('sha256', json_encode([
            'movement_id' => $movement->id,
            'reason' => $reason,
        ], JSON_THROW_ON_ERROR));

        if ($existing) {
            $this->verifyReversalReplay($existing, $movement, $requestHash);

            return $existing;
        }

        $oppositeDirection = $movement->direction === 'in' ? 'out' : 'in';

        return DB::transaction(function () use ($branch, $movement, $reason, $actor, $idempotencyKey, $requestHash, $oppositeDirection): MoneyMovement {
            $movement = MoneyMovement::where('branch_id', $branch->id)
                ->whereKey($movement->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = MoneyMovement::where('branch_id', $branch->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->verifyReversalReplay($existing, $movement, $requestHash);

                return $existing;
            }

            if ($movement->reversedBy()->exists()) {
                return $movement->reversedBy()->first();
            }

            $reversal = MoneyMovement::create([
                'branch_id' => $branch->id,
                'payment_account_id' => $movement->payment_account_id,
                'movement_type' => 'reversal',
                'amount_minor' => $movement->amount_minor,
                'currency_code' => $movement->currency_code,
                'direction' => $oppositeDirection,
                'source' => 'reversal',
                'source_id' => (string) $movement->id,
                'reference' => $movement->reference,
                'reason' => $reason,
                'reverses_movement_id' => $movement->id,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'actor_id' => $actor->id,
            ]);

            $this->audit->log(
                'money_movement.reversed',
                $reversal,
                ['movement_id' => $movement->id],
                [
                    'reversal_id' => $reversal->id,
                    'reason' => $reason,
                ],
                $branch->id,
                $actor,
            );

            return $reversal;
        }, 3);
    }

    private function verifyReversalReplay(MoneyMovement $existing, MoneyMovement $movement, string $requestHash): void
    {
        if ((string) $existing->reverses_movement_id !== (string) $movement->id
            || ! hash_equals((string) $existing->request_hash, $requestHash)) {
            throw new ConflictHttpException('The Idempotency-Key has already been used with a different reversal.');
        }
    }

    private function normalizeIdempotencyKey(string $key): string
    {
        $key = trim($key);

        if ($key === '') {
            return (string) Str::uuid();
        }

        if (strlen($key) > 100) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The Idempotency-Key header cannot exceed 100 characters.',
            ]);
        }

        return $key;
    }
}
