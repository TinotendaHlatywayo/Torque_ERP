<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class SaleVoidService
{
    public function __construct(
        private readonly StockLedger $stockLedger,
        private readonly AuditLogger $audit,
    ) {}

    public function void(Branch $branch, Sale $sale, User $actor, string $reason, string $idempotencyKey): Sale
    {
        if ($sale->branch_id !== $branch->id) {
            abort(404);
        }

        if ($sale->status === 'voided') {
            return $sale->load(['customer', 'items', 'payments', 'receipt']);
        }

        if ($sale->status !== 'posted') {
            throw new ConflictHttpException('Only posted sales can be voided.');
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A void reason is required.',
            ]);
        }

        return DB::transaction(function () use ($branch, $sale, $actor, $reason): Sale {
            $sale = Sale::where('branch_id', $branch->id)
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($sale->status === 'voided') {
                return $sale->load(['customer', 'items', 'payments', 'receipt']);
            }

            foreach ($sale->items as $item) {
                $product = $item->product;

                if (! $product || ! $product->track_stock) {
                    continue;
                }

                $this->stockLedger->postCorrection(
                    $branch,
                    $product,
                    (string) $item->quantity,
                    (string) $item->unit_id,
                    'Void of sale '.$sale->sale_number,
                    $actor,
                    'void:'.$sale->id.':'.$item->id,
                );
            }

            $sale->update([
                'status' => 'voided',
                'payment_status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ]);

            $this->audit->log(
                'sale.voided',
                $sale->refresh(),
                ['status' => 'posted'],
                [
                    'sale_number' => $sale->sale_number,
                    'reason' => $reason,
                ],
                $branch->id,
                $actor,
            );

            return $sale->load(['customer', 'items', 'payments', 'receipt']);
        }, 3);
    }
}
