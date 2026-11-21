<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\MoneyMovement;
use App\Models\SupplierBill;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AssetService
{
    private const SOURCE_MODELS = [
        Asset::SOURCE_EXPENSE => Expense::class,
        Asset::SOURCE_SUPPLIER_BILL => SupplierBill::class,
        Asset::SOURCE_MONEY_MOVEMENT => MoneyMovement::class,
    ];

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function acquire(Branch $branch, User $actor, array $data): Asset
    {
        $sourceType = $data['source_type'] ?? null;
        $sourceId = $data['source_id'] ?? null;

        $this->assertSource($branch, $sourceType, $sourceId, (string) $data['currency_code']);

        $asset = DB::transaction(function () use ($branch, $actor, $data, $sourceType, $sourceId): Asset {
            if ($sourceType !== null && $sourceId !== null) {
                $duplicate = Asset::where('branch_id', $branch->id)
                    ->where('source_type', $sourceType)
                    ->where('source_id', $sourceId)
                    ->lockForUpdate()
                    ->first();

                throw_if($duplicate, new ConflictHttpException('This purchase or payment is already linked to an asset in this branch.'));
            }

            $asset = $branch->assets()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'acquisition_date' => $data['acquisition_date'],
                'cost_minor' => $data['cost_minor'],
                'currency_code' => $data['currency_code'],
                'location' => $data['location'],
                'custodian' => $data['custodian'],
                'status' => Asset::STATUS_ACTIVE,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'actor_id' => $actor->id,
            ]);

            $this->audit->log(
                'asset.acquired',
                $asset,
                null,
                $this->snapshot($asset),
                $branch->id,
                $actor,
            );

            return $asset;
        }, 3);

        return $asset->load('actor:id,name');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordMaintenance(Branch $branch, Asset $asset, User $actor, array $data): AssetMaintenance
    {
        $this->assertAssetBranch($branch, $asset);

        $currencyCode = (string) $data['currency_code'];

        throw_unless($currencyCode === $asset->currency_code, ValidationException::withMessages([
            'currency_code' => 'Maintenance currency must match the asset currency.',
        ]));

        $expenseId = $data['expense_id'] ?? null;
        $paymentId = $data['payment_id'] ?? null;

        if ($expenseId !== null) {
            throw_unless(
                Expense::where('branch_id', $branch->id)->whereKey($expenseId)->exists(),
                ValidationException::withMessages(['expense_id' => 'The linked expense must belong to this branch.']),
            );
        }

        if ($paymentId !== null) {
            throw_unless(
                MoneyMovement::where('branch_id', $branch->id)->whereKey($paymentId)->exists(),
                ValidationException::withMessages(['payment_id' => 'The linked payment must belong to this branch.']),
            );
        }

        $maintenance = DB::transaction(function () use ($branch, $asset, $actor, $data, $expenseId, $paymentId): AssetMaintenance {
            $maintenance = $asset->maintenances()->create([
                'branch_id' => $branch->id,
                'performed_date' => $data['performed_date'],
                'cost_minor' => $data['cost_minor'],
                'currency_code' => $data['currency_code'],
                'provider' => $data['provider'],
                'description' => $data['description'],
                'expense_id' => $expenseId,
                'payment_id' => $paymentId,
                'actor_id' => $actor->id,
            ]);

            $this->audit->log(
                'asset.maintenance_recorded',
                $maintenance,
                null,
                [
                    'asset_id' => $asset->id,
                    'performed_date' => $maintenance->performed_date->toDateString(),
                    'cost_minor' => $maintenance->cost_minor,
                    'currency_code' => $maintenance->currency_code,
                    'expense_id' => $maintenance->expense_id,
                    'payment_id' => $maintenance->payment_id,
                ],
                $branch->id,
                $actor,
            );

            return $maintenance;
        }, 3);

        return $maintenance->load(['expense:id,description', 'payment:id,reference']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{asset: Asset, financial_effect: array<string, mixed>}
     */
    public function dispose(Branch $branch, Asset $asset, User $actor, array $data): array
    {
        $this->assertAssetBranch($branch, $asset);

        throw_if($asset->status === Asset::STATUS_DISPOSED, new ConflictHttpException('This asset has already been disposed.'));

        $accumulatedEstimateMinor = (int) $data['accumulated_estimate_minor'];
        $proceedsMinor = (int) $data['proceeds_minor'];

        throw_unless($accumulatedEstimateMinor >= 0 && $accumulatedEstimateMinor <= $asset->cost_minor, ValidationException::withMessages([
            'accumulated_estimate_minor' => 'Accumulated estimate must be between zero and the asset cost.',
        ]));

        $bookValueEstimateMinor = $asset->cost_minor - $accumulatedEstimateMinor;

        $financialEffect = [
            'cost_minor' => $asset->cost_minor,
            'accumulated_estimate_minor' => $accumulatedEstimateMinor,
            'book_value_estimate_minor' => $bookValueEstimateMinor,
            'proceeds_minor' => $proceedsMinor,
            'estimated_gain_loss_minor' => $proceedsMinor - $bookValueEstimateMinor,
            'currency_code' => $asset->currency_code,
            'label' => DepreciationEstimateService::LABEL,
            'accounting_use' => DepreciationEstimateService::ACCOUNTING_USE,
            'notice' => 'Disposal effect uses an unconfirmed depreciation estimate. No accounting entry or money movement is posted automatically.',
            'automatic_entries_posted' => 0,
        ];

        $disposed = DB::transaction(function () use ($branch, $asset, $actor, $data, $financialEffect, $proceedsMinor): Asset {
            $locked = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();

            throw_if($locked->status === Asset::STATUS_DISPOSED, new ConflictHttpException('This asset has already been disposed.'));

            $before = $this->snapshot($locked);

            $locked->update([
                'status' => Asset::STATUS_DISPOSED,
                'disposal_date' => $data['disposal_date'],
                'disposal_proceeds_minor' => $proceedsMinor,
                'disposal_notes' => $data['notes'] ?? null,
            ]);

            $this->audit->log(
                'asset.disposed',
                $locked,
                $before,
                [...$this->snapshot($locked), 'financial_effect' => $financialEffect],
                $branch->id,
                $actor,
            );

            return $locked->refresh();
        }, 3);

        return ['asset' => $disposed, 'financial_effect' => $financialEffect];
    }

    private function assertAssetBranch(Branch $branch, Asset $asset): void
    {
        abort_unless($asset->branch_id === $branch->id, 404);
    }

    private function assertSource(Branch $branch, ?string $sourceType, ?string $sourceId, string $currencyCode): void
    {
        if ($sourceType === null && $sourceId === null) {
            return;
        }

        throw_unless(
            $sourceType !== null && $sourceId !== null && isset(self::SOURCE_MODELS[$sourceType]),
            ValidationException::withMessages(['source_type' => 'Select a valid expense, supplier bill or payment source.']),
        );

        $source = self::SOURCE_MODELS[$sourceType]::where('branch_id', $branch->id)->whereKey($sourceId)->first();

        throw_unless($source, ValidationException::withMessages([
            'source_id' => 'The linked purchase or payment must belong to this branch.',
        ]));

        throw_unless($source->currency_code === $currencyCode, ValidationException::withMessages([
            'currency_code' => 'Asset currency must match the linked purchase or payment currency.',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'name' => $asset->name,
            'acquisition_date' => $asset->acquisition_date->toDateString(),
            'cost_minor' => $asset->cost_minor,
            'currency_code' => $asset->currency_code,
            'location' => $asset->location,
            'custodian' => $asset->custodian,
            'status' => $asset->status,
            'source_type' => $asset->source_type,
            'source_id' => $asset->source_id,
            'disposal_date' => $asset->disposal_date?->toDateString(),
            'disposal_proceeds_minor' => $asset->disposal_proceeds_minor,
            'disposal_notes' => $asset->disposal_notes,
        ];
    }
}
