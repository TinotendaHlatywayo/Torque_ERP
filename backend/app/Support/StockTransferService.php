<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class StockTransferService
{
    public function __construct(
        private readonly StockLedger $stockLedger,
        private readonly DocumentNumberService $documentNumbers,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Branch $source, User $actor, array $data): StockTransfer
    {
        $destination = Branch::findOrFail($data['destination_branch_id']);

        $this->assertCrossBranchAccess($source, $destination, $actor);

        [$sourceProduct, $sourceUnit] = $this->assertProductAndUnit(
            $source,
            (string) $data['product_id'],
            (string) $data['unit_id'],
            'source',
        );

        [$destinationProduct, $destinationUnit] = $this->assertProductAndUnit(
            $destination,
            (string) $data['destination_product_id'],
            (string) $data['destination_unit_id'],
            'destination',
        );

        $quantity = $this->assertQuantity($data['quantity']);
        $reason = trim((string) $data['reason']);

        $transfer = DB::transaction(fn (): StockTransfer => $source->stockTransfers()->create([
            'transfer_number' => $this->documentNumbers->allocate($source, 'transfer'),
            'source_branch_id' => $source->id,
            'destination_branch_id' => $destination->id,
            'product_id' => $sourceProduct->id,
            'unit_id' => $sourceUnit->id,
            'destination_product_id' => $destinationProduct->id,
            'destination_unit_id' => $destinationUnit->id,
            'quantity' => $quantity,
            'reason' => $reason,
            'status' => StockTransfer::STATUS_DRAFT,
            'created_by' => $actor->id,
        ]));

        $this->audit->log('stock_transfer.created', $transfer, null, $this->snapshot($transfer), $source->id, $actor);

        return $transfer->load(['sourceBranch', 'destinationBranch', 'product', 'unit', 'destinationProduct', 'destinationUnit']);
    }

    public function dispatch(StockTransfer $transfer, User $actor): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $actor): StockTransfer {
            $locked = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($locked, [StockTransfer::STATUS_DRAFT], 'dispatch');

            $source = Branch::findOrFail($locked->source_branch_id);
            $destination = Branch::findOrFail($locked->destination_branch_id);
            $this->assertCrossBranchAccess($source, $destination, $actor);

            $product = Product::where('branch_id', $source->id)->whereKey($locked->product_id)->firstOrFail();
            $before = $this->snapshot($locked);

            $posting = $this->stockLedger->postTransferDispatch(
                $source,
                $product,
                (string) $locked->quantity,
                (string) $locked->unit_id,
                (string) $locked->id,
                (string) $locked->reason,
                $actor,
                'stock-transfer-dispatch:'.$locked->id,
            );

            $locked->update([
                'status' => StockTransfer::STATUS_DISPATCHED,
                'dispatched_by' => $actor->id,
                'dispatched_at' => now(),
                'dispatched_quantity' => $locked->quantity,
                'source_movement_id' => $posting->movement->id,
            ]);

            $this->audit->log('stock_transfer.dispatched', $locked, $before, $this->snapshot($locked), $source->id, $actor);

            return $locked->refresh();
        }, 3);
    }

    public function markInTransit(StockTransfer $transfer, User $actor): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $actor): StockTransfer {
            $locked = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($locked, [StockTransfer::STATUS_DISPATCHED], 'mark in transit');

            $source = Branch::findOrFail($locked->source_branch_id);
            $destination = Branch::findOrFail($locked->destination_branch_id);
            $this->assertCrossBranchAccess($source, $destination, $actor);

            $before = $this->snapshot($locked);
            $locked->update([
                'status' => StockTransfer::STATUS_IN_TRANSIT,
                'in_transit_by' => $actor->id,
                'in_transit_at' => now(),
            ]);

            $this->audit->log('stock_transfer.in_transit', $locked, $before, $this->snapshot($locked), $source->id, $actor);

            return $locked->refresh();
        }, 3);
    }

    public function receive(StockTransfer $transfer, User $actor): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $actor): StockTransfer {
            $locked = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($locked, [StockTransfer::STATUS_DISPATCHED, StockTransfer::STATUS_IN_TRANSIT], 'receive');

            $source = Branch::findOrFail($locked->source_branch_id);
            $destination = Branch::findOrFail($locked->destination_branch_id);
            $this->assertCrossBranchAccess($source, $destination, $actor);

            $product = Product::where('branch_id', $destination->id)->whereKey($locked->destination_product_id)->firstOrFail();
            $before = $this->snapshot($locked);

            $posting = $this->stockLedger->postTransferReceipt(
                $destination,
                $product,
                (string) ($locked->dispatched_quantity ?? $locked->quantity),
                (string) $locked->destination_unit_id,
                (string) $locked->id,
                (string) $locked->reason,
                $actor,
                'stock-transfer-receipt:'.$locked->id,
            );

            $locked->update([
                'status' => StockTransfer::STATUS_RECEIVED,
                'received_by' => $actor->id,
                'received_at' => now(),
                'destination_movement_id' => $posting->movement->id,
            ]);

            $this->audit->log('stock_transfer.received', $locked, $before, $this->snapshot($locked), $source->id, $actor);

            return $locked->refresh();
        }, 3);
    }

    public function cancel(StockTransfer $transfer, User $actor, string $reason): StockTransfer
    {
        $reason = trim($reason);

        throw_unless($reason !== '', ValidationException::withMessages([
            'reason' => 'A cancellation reason is required.',
        ]));

        return DB::transaction(function () use ($transfer, $actor, $reason): StockTransfer {
            $locked = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($locked, [
                StockTransfer::STATUS_DRAFT,
                StockTransfer::STATUS_DISPATCHED,
                StockTransfer::STATUS_IN_TRANSIT,
            ], 'cancel');

            $source = Branch::findOrFail($locked->source_branch_id);
            $destination = Branch::findOrFail($locked->destination_branch_id);
            $this->assertCrossBranchAccess($source, $destination, $actor);

            $before = $this->snapshot($locked);
            $cancellationMovementId = null;

            if ($locked->source_movement_id !== null) {
                $movement = StockMovement::whereKey($locked->source_movement_id)->firstOrFail();
                $posting = $this->stockLedger->reverse(
                    $source,
                    $movement,
                    'Stock transfer cancelled: '.$reason,
                    $actor,
                    'stock-transfer-cancel:'.$locked->id,
                );
                $cancellationMovementId = $posting->movement->id;
            }

            $locked->update([
                'status' => StockTransfer::STATUS_CANCELLED,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'cancellation_movement_id' => $cancellationMovementId,
            ]);

            $this->audit->log('stock_transfer.cancelled', $locked, $before, [
                ...$this->snapshot($locked),
                'source_stock_reversed' => $cancellationMovementId !== null,
                'destination_stock_reversed' => false,
            ], $source->id, $actor);

            return $locked->refresh();
        }, 3);
    }

    private function assertCrossBranchAccess(Branch $source, Branch $destination, User $actor): void
    {
        foreach ([$source, $destination] as $branch) {
            $membership = BranchUser::where('branch_id', $branch->id)
                ->where('user_id', $actor->id)
                ->where('is_active', true)
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->first();

            if (! $membership || ! $membership->hasPermission(Permissions::BRANCH_TRANSFERS_MANAGE)) {
                abort(403);
            }
        }

        throw_unless($destination->status === Branch::STATUS_ACTIVE, ValidationException::withMessages([
            'destination_branch_id' => 'The destination branch must be active.',
        ]));
    }

    private function assertProductAndUnit(Branch $branch, string $productId, string $unitId, string $field): array
    {
        $product = Product::where('branch_id', $branch->id)->whereKey($productId)->first();

        throw_unless($product, ValidationException::withMessages([
            $field.'_product_id' => 'The product must belong to the '.$field.' branch.',
        ]));

        throw_unless($product->is_active && $product->track_stock, ValidationException::withMessages([
            $field.'_product_id' => 'The '.$field.' product must be active and track stock.',
        ]));

        $unit = Unit::where('branch_id', $branch->id)->whereKey($unitId)->first();

        throw_unless($unit && $unit->is_active, ValidationException::withMessages([
            $field.'_unit_id' => 'The unit must be an active unit of the '.$field.' branch.',
        ]));

        throw_unless($unit->id === $product->base_unit_id, ValidationException::withMessages([
            $field.'_unit_id' => 'Transfers must use the product base unit.',
        ]));

        return [$product, $unit];
    }

    private function assertQuantity(mixed $quantity): string
    {
        throw_unless(is_numeric($quantity) && Decimal::fitsScale((string) $quantity, 4) && bccomp((string) $quantity, '0', 4) > 0, ValidationException::withMessages([
            'quantity' => 'The quantity must be greater than zero with at most four decimal places.',
        ]));

        return Decimal::trim(bcadd((string) $quantity, '0', 4));
    }

    private function assertStatus(StockTransfer $transfer, array $allowed, string $action): void
    {
        if (in_array($transfer->status, [StockTransfer::STATUS_RECEIVED, StockTransfer::STATUS_CANCELLED], true)) {
            throw new ConflictHttpException('This transfer is already '.$transfer->status.'.');
        }

        throw_unless(in_array($transfer->status, $allowed, true), ValidationException::withMessages([
            'status' => "The transfer cannot {$action} while it is {$transfer->status}.",
        ]));
    }

    private function snapshot(StockTransfer $transfer): array
    {
        return [
            'transfer_number' => $transfer->transfer_number,
            'source_branch_id' => $transfer->source_branch_id,
            'destination_branch_id' => $transfer->destination_branch_id,
            'product_id' => $transfer->product_id,
            'destination_product_id' => $transfer->destination_product_id,
            'quantity' => $transfer->quantity,
            'status' => $transfer->status,
            'source_movement_id' => $transfer->source_movement_id,
            'destination_movement_id' => $transfer->destination_movement_id,
            'cancellation_movement_id' => $transfer->cancellation_movement_id,
        ];
    }
}
