<?php

namespace App\Support;

use App\Models\Asset;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

final class DepreciationEstimateService
{
    public const LABEL = 'ESTIMATE';

    public const ACCOUNTING_USE = 'BLOCKED';

    public const NOTICE = 'Straight-line estimate only. No depreciation policy or professional rule has been confirmed. Do not use for accounting, tax or ledger postings.';

    /**
     * @return array<string, mixed>
     */
    public function estimate(Asset $asset, string $asOf, int $usefulLifeYears, int $residualMinor = 0): array
    {
        throw_unless($usefulLifeYears >= 1 && $usefulLifeYears <= 100, ValidationException::withMessages([
            'useful_life_years' => 'Useful life must be between 1 and 100 years.',
        ]));

        throw_unless($residualMinor >= 0 && $residualMinor <= $asset->cost_minor, ValidationException::withMessages([
            'residual_minor' => 'Residual value must be between zero and the asset cost.',
        ]));

        $asOfDate = Carbon::parse($asOf)->startOfDay();
        $acquiredAt = $asset->acquisition_date->copy()->startOfDay();
        $effectiveAsOf = $asset->disposal_date
            ? $asset->disposal_date->copy()->startOfDay()->min($asOfDate)
            : $asOfDate;
        $monthsElapsed = $acquiredAt->greaterThan($effectiveAsOf) ? 0 : $acquiredAt->diffInMonths($effectiveAsOf);
        $usefulLifeMonths = $usefulLifeYears * 12;
        $depreciableMinor = $asset->cost_minor - $residualMinor;
        $accumulatedEstimateMinor = min($depreciableMinor, intdiv($depreciableMinor * $monthsElapsed, $usefulLifeMonths));

        return [
            'asset_id' => $asset->id,
            'asset_name' => $asset->name,
            'status' => $asset->status,
            'as_of' => $asOfDate->toDateString(),
            'effective_as_of' => $effectiveAsOf->toDateString(),
            'label' => self::LABEL,
            'accounting_use' => self::ACCOUNTING_USE,
            'policy_status' => 'unconfirmed',
            'notice' => self::NOTICE,
            'cost_minor' => $asset->cost_minor,
            'residual_minor' => $residualMinor,
            'depreciable_minor' => $depreciableMinor,
            'currency_code' => $asset->currency_code,
            'useful_life_years' => $usefulLifeYears,
            'useful_life_months' => $usefulLifeMonths,
            'months_elapsed' => $monthsElapsed,
            'monthly_estimate_minor' => intdiv($depreciableMinor, $usefulLifeMonths),
            'accumulated_estimate_minor' => $accumulatedEstimateMinor,
            'book_value_estimate_minor' => $asset->cost_minor - $accumulatedEstimateMinor,
            'disposal_date' => $asset->disposal_date?->toDateString(),
            'posted' => false,
            'financial_movement_posted' => false,
        ];
    }
}
