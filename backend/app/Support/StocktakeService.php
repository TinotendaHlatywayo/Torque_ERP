<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Stocktake;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class StocktakeService
{
    public function __construct(
        private readonly StockLedger $stockLedger,
        private readonly DocumentNumberService $documentNumbers,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Branch $branch, User $actor, array $data): Stocktake
    {
        $lines = $data['lines'] ?? [];

        throw_unless(is_array($lines) && $lines !== [], ValidationException::withMessages([
            'lines' => 'At least one counted line is required.',
        ]));

        $headerReason = trim((string) $data['reason']);
        $seenProducts = [];
        $stocktakeNumber = $this->documentNumbers->allocate($branch, 'stocktake');

        $stocktake = DB::transaction(function () use ($branch, $actor, $lines, $headerReason, $seenProducts, $stocktakeNumber): Stocktake {
            $stocktake = $branch->stocktakes()->create([
                'stocktake_number' => $stocktakeNumber,
                'branch_id' => $branch->id,
                'reason' => $headerReason,
                'status' => Stocktake::STATUS_DRAFT,
                'created_by' => $actor->id,
            ]);

            foreach ($lines as $line) {
                $productId = (string) $line['product_id'];
                $unitId = (string) $line['unit_id'];

                throw_unless(! isset($seenProducts[$productId]), ValidationException::withMessages([
                    'lines' => 'Each product may be counted only once per stocktake.',
                ]));

                $seenProducts[$productId] = true;

                [$product, $unit] = $this->assertProductAndUnit($branch, $productId, $unitId);
                $counted = $this->assertCountedQuantity($line['counted_quantity']);

                $level = StockLevel::where('branch_id', $branch->id)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                throw_unless($level, ValidationException::withMessages([
                    'lines' => 'Every counted product must have a stock balance row.',
                ]));

                $expected = (string) $level->quantity_on_hand;
                $variance = bcsub($counted, $expected, 4);
                $lineReason = trim((string) ($line['reason'] ?? ''));

                if (bccomp($variance, '0', 4) !== 0) {
                    throw_unless($lineReason !== '', ValidationException::withMessages([
                        'lines' => 'A reason is required for every counted line with a variance.',
                    ]));
                }

                $stocktake->lines()->create([
                    'stocktake_id' => $stocktake->id,
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'unit_id' => $unit->id,
                    'expected_quantity' => $expected,
                    'counted_quantity' => $counted,
                    'variance' => $variance,
                    'reason' => $lineReason,
                ]);
            }

            return $stocktake;
        }, 3);

        $this->audit->log('stocktake.created', $stocktake, null, $this->snapshot($stocktake), $branch->id, $actor);

        return $stocktake->load(['branch', 'creator', 'lines.product:id,name,sku', 'lines.unit:id,code']);
    }

    public function review(Stocktake $stocktake, User $actor): Stocktake
    {
        return DB::transaction(function () use ($stocktake, $actor): Stocktake {
            $locked = Stocktake::whereKey($stocktake->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($locked, [Stocktake::STATUS_DRAFT], 'review');

            $before = $this->snapshot($locked);

            foreach ($locked->lines()->with(['product:id,branch_id', 'unit:id,branch_id'])->get() as $line) {
                $variance = bcsub((string) $line->counted_quantity, (string) $line->expected_quantity, 4);
                $line->update(['variance' => $variance]);

                if (bccomp($variance, '0', 4) !== 0) {
                    throw_unless(trim((string) $line->reason) !== '', ValidationException::withMessages([
                        'lines' => 'A reason is required for every counted line with a variance.',
                    ]));
                }
            }

            $locked->update([
                'status' => Stocktake::STATUS_REVIEWED,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            $this->audit->log('stocktake.reviewed', $locked, $before, $this->snapshot($locked), $locked->branch_id, $actor);

            return $locked->refresh();
        }, 3);
    }

    public function approve(Stocktake $stocktake, User $actor, array $data): Stocktake
    {
        $approvalReference = $this->nullableString($data['approval_reference'] ?? null);

        return DB::transaction(function () use ($stocktake, $actor, $approvalReference): Stocktake {
            $locked = Stocktake::whereKey($stocktake->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($locked, [Stocktake::STATUS_REVIEWED], 'approve');

            $branch = Branch::findOrFail($locked->branch_id);
            $before = $this->snapshot($locked);

            foreach ($locked->lines()->with(['product', 'unit'])->get() as $line) {
                $variance = (string) $line->variance;

                if (bccomp($variance, '0', 4) === 0) {
                    continue;
                }

                $product = Product::where('branch_id', $branch->id)->whereKey($line->product_id)->firstOrFail();

                throw_unless($product->is_active && $product->track_stock, ValidationException::withMessages([
                    'lines' => 'Adjustments can only be approved for active products that track stock.',
                ]));

                $reason = trim((string) $line->reason);

                if ($reason === '') {
                    $reason = trim((string) $locked->reason);
                }

                $posting = $this->stockLedger->postStocktakeAdjustment(
                    $branch,
                    $product,
                    $variance,
                    (string) $line->unit_id,
                    (string) $line->id,
                    $reason,
                    $actor,
                    'stocktake-adjustment:'.$line->id,
                );

                $line->update(['stock_movement_id' => $posting->movement->id]);
            }

            $locked->update([
                'status' => Stocktake::STATUS_APPROVED,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'approval_reference' => $approvalReference,
            ]);

            $this->audit->log('stocktake.approved', $locked, $before, $this->snapshot($locked), $branch->id, $actor);

            return $locked->refresh();
        }, 3);
    }

    private function assertProductAndUnit(Branch $branch, string $productId, string $unitId): array
    {
        $product = Product::where('branch_id', $branch->id)->whereKey($productId)->first();

        throw_unless($product, ValidationException::withMessages([
            'lines' => 'The product must belong to the counted branch.',
        ]));

        throw_unless($product->is_active && $product->track_stock, ValidationException::withMessages([
            'lines' => 'Only active products that track stock can be counted.',
        ]));

        $unit = Unit::where('branch_id', $branch->id)->whereKey($unitId)->first();

        throw_unless($unit && $unit->is_active, ValidationException::withMessages([
            'lines' => 'The unit must be an active unit of the counted branch.',
        ]));

        throw_unless($unit->id === $product->base_unit_id, ValidationException::withMessages([
            'lines' => 'Counts must use the product base unit.',
        ]));

        return [$product, $unit];
    }

    private function assertCountedQuantity(mixed $quantity): string
    {
        throw_unless(
            is_numeric($quantity)
                && Decimal::fitsScale((string) $quantity, 4)
                && bccomp((string) $quantity, '0', 4) >= 0,
            ValidationException::withMessages([
                'lines' => 'Counted quantities must be zero or greater with at most four decimal places.',
            ]),
        );

        return Decimal::trim(bcadd((string) $quantity, '0', 4));
    }

    private function assertStatus(Stocktake $stocktake, array $allowed, string $action): void
    {
        if ($stocktake->status === Stocktake::STATUS_APPROVED) {
            throw new ConflictHttpException('This stocktake has already been approved.');
        }

        throw_unless(in_array($stocktake->status, $allowed, true), ValidationException::withMessages([
            'status' => "The stocktake cannot {$action} while it is {$stocktake->status}.",
        ]));
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function snapshot(Stocktake $stocktake): array
    {
        return [
            'stocktake_number' => $stocktake->stocktake_number,
            'branch_id' => $stocktake->branch_id,
            'reason' => $stocktake->reason,
            'status' => $stocktake->status,
            'reviewed_at' => $stocktake->reviewed_at?->toIso8601String(),
            'approved_at' => $stocktake->approved_at?->toIso8601String(),
            'approval_reference' => $stocktake->approval_reference,
        ];
    }
}
