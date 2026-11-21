<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\MoneyMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\SupplierBill;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DashboardMetricsService
{
    public function __construct(
        private readonly FinanceMetricsService $financeMetrics,
    ) {}

    /**
     * @param  array{inventory: bool, finance: bool}  $capabilities
     * @return array<string, mixed>
     */
    public function metrics(Branch $branch, ?string $asOf, array $capabilities): array
    {
        $asOfDate = $this->asOfDate($asOf);
        $warnings = [];

        $financeMetrics = $capabilities['finance']
            ? $this->financeMetrics->metrics($branch, $asOfDate->toDateString())
            : null;

        $inventory = null;

        if ($capabilities['inventory']) {
            $inventory = [
                'reorder' => $this->reorder($branch, $asOfDate, $warnings),
                'ageing' => $this->ageing($branch, $asOfDate, $warnings),
                'abc' => $this->abc($branch, $asOfDate, $warnings),
            ];
        }

        $cashForecast = $capabilities['finance']
            ? $this->cashForecast($branch, $asOfDate, $financeMetrics, $warnings)
            : null;

        return [
            'as_of' => $asOfDate->toDateString(),
            'currency_code' => $branch->currency_code,
            'capabilities' => $capabilities,
            'windows' => config('inventory_intelligence'),
            'finance_metrics' => $financeMetrics,
            'inventory_intelligence' => $inventory,
            'cash_forecast' => $cashForecast,
            'data_quality' => [
                'status' => $warnings === [] ? 'PASS' : 'WARNING',
                'warnings' => array_values($warnings),
            ],
        ];
    }

    /**
     * @param  array<int, array{section: string, code: string, message: string}>  $warnings
     * @return array<string, mixed>
     */
    private function reorder(Branch $branch, CarbonImmutable $asOfDate, array &$warnings): array
    {
        $config = config('inventory_intelligence.reorder');
        $windowDays = max(1, (int) $config['sales_window_days']);
        $leadTimeDays = max(0, (int) $config['lead_time_days']);
        $daysOfCover = max(0, (int) $config['days_of_cover']);
        [$from, $to] = $this->window($asOfDate, $windowDays);

        $sales = $this->salesByProduct($branch, $from, $to);
        $levels = $this->stockLevels($branch);
        $sectionWarnings = [];
        $suggestions = [];
        $productsWithoutHistory = 0;

        foreach ($levels as $level) {
            $saleRow = $sales->get((string) $level->product_id);

            if (! $saleRow || (float) $saleRow->units_sold <= 0) {
                $productsWithoutHistory++;

                continue;
            }

            $averageDailySales = round((float) $saleRow->units_sold / $windowDays, 4);
            $reorderPoint = round($averageDailySales * ($leadTimeDays + $daysOfCover), 4);
            $available = (float) bcsub((string) $level->quantity_on_hand, (string) $level->quantity_reserved, 4);
            $shortfall = max(0, round($reorderPoint - $available, 4));
            $suggestedOrderUnits = (int) ceil($shortfall);

            $suggestions[] = [
                'product_id' => (string) $level->product_id,
                'sku' => (string) $level->sku,
                'name' => (string) $level->name,
                'unit_code' => (string) $level->unit_code,
                'quantity_on_hand' => (string) $level->quantity_on_hand,
                'quantity_reserved' => (string) $level->quantity_reserved,
                'quantity_available' => number_format($available, 4, '.', ''),
                'units_sold_in_window' => round((float) $saleRow->units_sold, 4),
                'average_daily_sales_units' => $averageDailySales,
                'days_of_cover_on_hand' => $averageDailySales > 0 ? round($available / $averageDailySales, 2) : null,
                'lead_time_days' => $leadTimeDays,
                'days_of_cover' => $daysOfCover,
                'reorder_point_units' => $reorderPoint,
                'suggested_order_units' => $suggestedOrderUnits,
                'needs_reorder' => $suggestedOrderUnits > 0,
                'urgency' => $available <= round($averageDailySales * $leadTimeDays, 4) ? 'reorder_now' : 'plan_reorder',
            ];
        }

        if ($productsWithoutHistory > 0) {
            $sectionWarnings[] = $this->warning(
                'reorder',
                'insufficient_sales_history',
                "{$productsWithoutHistory} tracked products have no posted sales in the last {$windowDays} days; reorder suggestions are omitted for them.",
            );
        }

        if ($levels->isEmpty()) {
            $sectionWarnings[] = $this->warning('reorder', 'no_stock_levels', 'No branch stock levels are available for reorder analysis.');
        }

        usort($suggestions, static fn (array $left, array $right): int => [$right['needs_reorder'], $right['suggested_order_units'], $left['sku']]
            <=> [$left['needs_reorder'], $left['suggested_order_units'], $right['sku']]);

        $warnings = [...$warnings, ...$sectionWarnings];

        return [
            'sales_window_days' => $windowDays,
            'lead_time_days' => $leadTimeDays,
            'days_of_cover' => $daysOfCover,
            'products_without_history' => $productsWithoutHistory,
            'needs_reorder_count' => count(array_filter($suggestions, static fn (array $item): bool => $item['needs_reorder'])),
            'suggestions' => $suggestions,
            'data_quality' => $this->sectionQuality($sectionWarnings),
        ];
    }

    /**
     * @param  array<int, array{section: string, code: string, message: string}>  $warnings
     * @return array<string, mixed>
     */
    private function ageing(Branch $branch, CarbonImmutable $asOfDate, array &$warnings): array
    {
        $config = config('inventory_intelligence.ageing');
        $slowDays = max(0, (int) $config['slow_moving_threshold_days']);
        $deadDays = max($slowDays, (int) $config['dead_stock_threshold_days']);

        $lastMovements = StockMovement::query()
            ->where('branch_id', $branch->id)
            ->select('product_id', DB::raw('MAX(created_at) as last_movement_at'))
            ->groupBy('product_id')
            ->pluck('last_movement_at', 'product_id');

        $sectionWarnings = [];
        $items = [];
        $buckets = $this->emptyAgeingBuckets();

        foreach ($this->stockLevels($branch) as $level) {
            $productId = (string) $level->product_id;
            $onHand = (float) $level->quantity_on_hand;
            $value = (int) round($onHand * (float) $level->selling_price_minor);
            $status = 'no_stock';
            $ageDays = null;
            $lastMovementAt = null;

            if ($onHand > 0 && ! $lastMovements->has($productId)) {
                $status = 'unknown';
                $sectionWarnings[] = $this->warning(
                    'ageing',
                    'no_movement_history',
                    "Product {$level->sku} has stock but no stock movement history; ageing classification is null.",
                );
            } elseif ($onHand > 0) {
                $lastMovementAt = (string) $lastMovements->get($productId);
                $movementDate = CarbonImmutable::parse($lastMovementAt, 'UTC');
                $ageDays = abs($asOfDate->diffInDays($movementDate));
                $status = $ageDays >= $deadDays ? 'dead_stock' : ($ageDays >= $slowDays ? 'slow_moving' : 'active');
            }

            $items[] = [
                'product_id' => $productId,
                'sku' => (string) $level->sku,
                'name' => (string) $level->name,
                'quantity_on_hand' => (string) $level->quantity_on_hand,
                'stock_value_minor' => $value,
                'last_movement_at' => $lastMovementAt,
                'age_days' => $ageDays,
                'classification' => $status === 'unknown' ? null : $status,
                'status' => $status,
            ];

            $buckets[$status]['product_count']++;
            $buckets[$status]['stock_value_minor'] += $value;
        }

        usort($items, static fn (array $left, array $right): int => ($right['age_days'] ?? -1) <=> ($left['age_days'] ?? -1));

        $warnings = [...$warnings, ...$sectionWarnings];

        return [
            'as_of' => $asOfDate->toDateString(),
            'slow_moving_threshold_days' => $slowDays,
            'dead_stock_threshold_days' => $deadDays,
            'buckets' => $buckets,
            'items' => $items,
            'data_quality' => $this->sectionQuality($sectionWarnings),
        ];
    }

    /**
     * @param  array<int, array{section: string, code: string, message: string}>  $warnings
     * @return array<string, mixed>
     */
    private function abc(Branch $branch, CarbonImmutable $asOfDate, array &$warnings): array
    {
        $config = config('inventory_intelligence.abc');
        $windowDays = max(1, (int) $config['sales_window_days']);
        $aThreshold = (float) $config['a_cumulative_contribution_percent'];
        $bThreshold = max($aThreshold, (float) $config['b_cumulative_contribution_percent']);
        [$from, $to] = $this->window($asOfDate, $windowDays);

        $sales = $this->salesByProduct($branch, $from, $to);
        $products = $this->stockLevels($branch)->keyBy('product_id');
        $rows = [];

        foreach ($sales as $productId => $saleRow) {
            $revenue = (int) $saleRow->revenue_minor;

            if ($revenue <= 0) {
                continue;
            }

            $product = $products->get((string) $productId);
            $rows[] = [
                'product_id' => (string) $productId,
                'sku' => $product?->sku,
                'name' => $product?->name,
                'revenue_minor' => $revenue,
                'units_sold' => round((float) $saleRow->units_sold, 4),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => [$right['revenue_minor'], (string) $left['product_id']]
            <=> [$left['revenue_minor'], (string) $right['product_id']]);

        $totalRevenue = array_sum(array_column($rows, 'revenue_minor'));
        $sectionWarnings = [];
        $items = [];
        $summary = $this->emptyAbcSummary();

        if ($totalRevenue <= 0) {
            $sectionWarnings[] = $this->warning('abc', 'insufficient_sales_data', "No posted sales with revenue exist in the last {$windowDays} days; ABC classes are unavailable.");
        } else {
            $cumulativeRevenue = 0;

            foreach ($rows as $row) {
                $cumulativeRevenue += $row['revenue_minor'];
                $share = round($row['revenue_minor'] / $totalRevenue * 100, 2);
                $cumulativePercent = round($cumulativeRevenue / $totalRevenue * 100, 2);
                $class = $cumulativePercent <= $aThreshold ? 'A' : ($cumulativePercent <= $bThreshold ? 'B' : 'C');

                $items[] = [...$row, 'share_percent' => $share, 'cumulative_percent' => $cumulativePercent, 'class' => $class];
                $summary[$class]['product_count']++;
                $summary[$class]['revenue_minor'] += $row['revenue_minor'];
            }

            foreach (['A', 'B', 'C'] as $class) {
                $summary[$class]['share_percent'] = round($summary[$class]['revenue_minor'] / $totalRevenue * 100, 2);
            }

            if (count($rows) < 3) {
                $sectionWarnings[] = $this->warning('abc', 'limited_abc_population', 'ABC classes are derived from fewer than three sold products and are indicative only.');
            }
        }

        $warnings = [...$warnings, ...$sectionWarnings];

        return [
            'sales_window_days' => $windowDays,
            'a_cumulative_contribution_percent' => $aThreshold,
            'b_cumulative_contribution_percent' => $bThreshold,
            'total_revenue_minor' => $totalRevenue,
            'summary' => $summary,
            'items' => $items,
            'data_quality' => $this->sectionQuality($sectionWarnings),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $financeMetrics
     * @param  array<int, array{section: string, code: string, message: string}>  $warnings
     * @return array<string, mixed>
     */
    private function cashForecast(Branch $branch, CarbonImmutable $asOfDate, ?array $financeMetrics, array &$warnings): array
    {
        $config = config('inventory_intelligence.cash_forecast');
        $historyDays = max(1, (int) $config['history_window_days']);
        $horizonDays = max(1, (int) $config['horizon_days']);
        $minimumEvents = max(0, (int) $config['minimum_cash_events']);
        [$from, $to] = $this->window($asOfDate, $historyDays);
        $metrics = $financeMetrics ?? $this->financeMetrics->metrics($branch, $asOfDate->toDateString());

        $salesQuery = Sale::query()
            ->where('branch_id', $branch->id)
            ->where('status', '!=', 'voided')
            ->whereBetween('created_at', [$from, $to]);

        $paidSalesMinor = (int) (clone $salesQuery)->sum('paid_minor');
        $cashSaleEvents = (int) (clone $salesQuery)->where('paid_minor', '>', 0)->count('id');
        $manualInQuery = MoneyMovement::query()
            ->where('branch_id', $branch->id)
            ->where('direction', 'in')
            ->whereNotIn('source', ['receivable_collection'])
            ->whereBetween('created_at', [$from, $to]);
        $manualInMinor = (int) (clone $manualInQuery)->sum('amount_minor');
        $cashOutQuery = MoneyMovement::query()
            ->where('branch_id', $branch->id)
            ->where('direction', 'out')
            ->whereNotIn('movement_type', ['transfer_out'])
            ->whereBetween('created_at', [$from, $to]);
        $outMinor = (int) (clone $cashOutQuery)->sum('amount_minor');
        $cashEventCount = $cashSaleEvents
            + (int) (clone $manualInQuery)->count('id')
            + (int) (clone $cashOutQuery)->count('id');

        $billDueTo = $asOfDate->addDays($horizonDays)->toDateString();
        $knownBillsDueMinor = (int) SupplierBill::query()
            ->where('branch_id', $branch->id)
            ->where('status', '!=', 'paid')
            ->whereBetween('due_date', [$asOfDate->toDateString(), $billDueTo])
            ->selectRaw('COALESCE(SUM(amount_minor - paid_minor), 0) as outstanding')
            ->value('outstanding');
        $unpaidExpensesMinor = (int) Expense::query()
            ->where('branch_id', $branch->id)
            ->where('incurred_date', '<=', $asOfDate->toDateString())
            ->whereNull('paid_date')
            ->sum('amount_minor');

        $currencies = array_filter(array_unique([
            (string) $branch->currency_code,
            ...(clone $salesQuery)->where('paid_minor', '>', 0)->pluck('currency_code')->all(),
            ...(clone $cashOutQuery)->pluck('currency_code')->all(),
        ]));

        $sectionWarnings = [];

        if ($cashEventCount < $minimumEvents) {
            $sectionWarnings[] = $this->warning(
                'cash_forecast',
                'insufficient_cash_history',
                "Only {$cashEventCount} cash events were found in the last {$historyDays} days (minimum {$minimumEvents}); forecast values are null.",
            );
        }

        if ($asOfDate->isAfter(CarbonImmutable::now('UTC')->startOfDay())) {
            $sectionWarnings[] = $this->warning('cash_forecast', 'as_of_in_future', 'The requested as-of date is in the future; projections are illustrative only.');
        }

        if (count($currencies) > 1) {
            $sectionWarnings[] = $this->warning('cash_forecast', 'mixed_currency_data', 'Cash history uses more than one currency; no FX conversion is applied.');
        }

        $beginningCash = (int) $metrics['cash_position_minor'];
        $dailyIn = $cashEventCount < $minimumEvents ? null : (int) round(($paidSalesMinor + $manualInMinor) / $historyDays);
        $dailyOut = $cashEventCount < $minimumEvents ? null : (int) round($outMinor / $historyDays);
        $projectedIn = $dailyIn === null ? null : $dailyIn * $horizonDays;
        $projectedOut = $dailyOut === null ? null : $dailyOut * $horizonDays;
        $forecastCash = $projectedIn === null || $projectedOut === null
            ? null
            : $beginningCash + $projectedIn - $projectedOut - $knownBillsDueMinor;

        $series = [];

        if ($forecastCash !== null) {
            for ($day = 1; $day <= $horizonDays; $day++) {
                $series[] = [
                    'day' => $day,
                    'projected_cash_minor' => $beginningCash + ($day * (int) ($dailyIn - $dailyOut)) - $knownBillsDueMinor,
                ];
            }
        }

        $warnings = [...$warnings, ...$sectionWarnings];

        return [
            'label' => 'FORECAST',
            'authoritative_finance_statement' => false,
            'as_of' => $asOfDate->toDateString(),
            'history_window_days' => $historyDays,
            'horizon_days' => $horizonDays,
            'assumptions' => [
                "Opening cash is FinanceMetricsService cash position ({$beginningCash} minor units) at the as-of date.",
                "Average daily inflow = (non-voided sales paid_minor in the last {$historyDays} days + manual money-in excluding receivable collections) / {$historyDays}. Collections are counted through sale paid_minor only.",
                "Average daily outflow = money movements out excluding transfer_out in the last {$historyDays} days / {$historyDays}.",
                'Projection is a straight-line extrapolation: no seasonality, growth, FX conversion or payment-timing model is applied.',
                "Unpaid supplier bills due within the {$horizonDays}-day horizon ({$knownBillsDueMinor} minor units) are deducted once at the start of the forecast.",
                'Cash target is an illustrative liquidity requirement: all unpaid bills to pay plus incurred expenses without a paid date. It is not a budget or approved finance statement.',
            ],
            'beginning_cash_minor' => $beginningCash,
            'average_daily_inflow_minor' => $dailyIn,
            'average_daily_outflow_minor' => $dailyOut,
            'projected_inflow_minor' => $projectedIn,
            'projected_outflow_minor' => $projectedOut,
            'known_bills_due_minor' => $knownBillsDueMinor,
            'cash_target_minor' => (int) $metrics['bills_to_pay_minor'] + $unpaidExpensesMinor,
            'forecast_cash_position_minor' => $forecastCash,
            'series' => $series,
            'data_quality' => $this->sectionQuality($sectionWarnings),
        ];
    }

    private function asOfDate(?string $asOf): CarbonImmutable
    {
        return CarbonImmutable::parse($asOf ?? now()->toDateString(), 'UTC')->startOfDay();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function window(CarbonImmutable $asOfDate, int $days): array
    {
        return [$asOfDate->subDays($days)->startOfDay(), $asOfDate->endOfDay()];
    }

    /**
     * @return Collection<string, object>
     */
    private function salesByProduct(Branch $branch, CarbonImmutable $from, CarbonImmutable $to)
    {
        return SaleItem::query()
            ->join('sales', static function ($join) use ($branch): void {
                $join->on('sale_items.sale_id', '=', 'sales.id')
                    ->where('sales.branch_id', $branch->id)
                    ->where('sales.status', 'posted');
            })
            ->where('sale_items.branch_id', $branch->id)
            ->whereBetween('sales.created_at', [$from, $to])
            ->selectRaw('sale_items.product_id, SUM(sale_items.quantity) as units_sold, SUM(sale_items.total_minor) as revenue_minor')
            ->groupBy('sale_items.product_id')
            ->get()
            ->keyBy('product_id');
    }

    /**
     * @return Collection<int, StockLevel>
     */
    private function stockLevels(Branch $branch)
    {
        return StockLevel::query()
            ->join('products', static function ($join) use ($branch): void {
                $join->on('stock_levels.product_id', '=', 'products.id')
                    ->where('products.branch_id', $branch->id);
            })
            ->join('units', 'products.base_unit_id', '=', 'units.id')
            ->where('stock_levels.branch_id', $branch->id)
            ->select('stock_levels.*', 'products.sku', 'products.name', 'products.selling_price_minor', 'units.code as unit_code')
            ->get();
    }

    /**
     * @param  array{section: string, code: string, message: string}  $warning
     * @return array{section: string, code: string, message: string}
     */
    private function warning(string $section, string $code, string $message): array
    {
        return ['section' => $section, 'code' => $code, 'message' => $message];
    }

    /**
     * @param  array<int, array{section: string, code: string, message: string}>  $warnings
     * @return array{status: string, warnings: array<int, array{section: string, code: string, message: string}>}
     */
    private function sectionQuality(array $warnings): array
    {
        return [
            'status' => $warnings === [] ? 'PASS' : 'WARNING',
            'warnings' => array_values($warnings),
        ];
    }

    /**
     * @return array<string, array{product_count: int, stock_value_minor: int}>
     */
    private function emptyAgeingBuckets(): array
    {
        return [
            'active' => ['product_count' => 0, 'stock_value_minor' => 0],
            'slow_moving' => ['product_count' => 0, 'stock_value_minor' => 0],
            'dead_stock' => ['product_count' => 0, 'stock_value_minor' => 0],
            'no_stock' => ['product_count' => 0, 'stock_value_minor' => 0],
            'unknown' => ['product_count' => 0, 'stock_value_minor' => 0],
        ];
    }

    /**
     * @return array<string, array{product_count: int, revenue_minor: int, share_percent: float}>
     */
    private function emptyAbcSummary(): array
    {
        return [
            'A' => ['product_count' => 0, 'revenue_minor' => 0, 'share_percent' => 0.0],
            'B' => ['product_count' => 0, 'revenue_minor' => 0, 'share_percent' => 0.0],
            'C' => ['product_count' => 0, 'revenue_minor' => 0, 'share_percent' => 0.0],
        ];
    }
}
