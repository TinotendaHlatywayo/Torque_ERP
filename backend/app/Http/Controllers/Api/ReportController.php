<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function sales(Request $request, Branch $branch): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $sales = $branch->sales()
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->get();

        return response()->json([
            'data' => [
                'from' => $from,
                'to' => $to,
                'date_basis' => 'created_at UTC',
                'currency_code' => $branch->currency_code,
                'sale_count' => $sales->where('status', '!=', 'voided')->count(),
                'voided_count' => $sales->where('status', 'voided')->count(),
                'invoiced_revenue_minor' => (int) $sales->where('status', '!=', 'voided')->sum('total_minor'),
                'discount_minor' => (int) $sales->where('status', '!=', 'voided')->sum('discount_minor'),
                'tax_minor' => (int) $sales->where('status', '!=', 'voided')->sum('tax_minor'),
                'cash_collected_minor' => (int) $sales->where('status', '!=', 'voided')->sum('paid_minor'),
                'outstanding_minor' => (int) $sales->where('status', '!=', 'voided')->sum(fn ($sale) => $sale->total_minor - $sale->paid_minor),
            ],
        ]);
    }

    public function stock(Branch $branch): JsonResponse
    {
        $products = $branch->products()->with('stockLevel')->get();
        $levels = $branch->stockLevels()->with('product:id,track_stock,is_active,selling_price_minor')->get();

        return response()->json([
            'data' => [
                'currency_code' => $branch->currency_code,
                'product_count' => $products->count(),
                'tracked_product_count' => $products->where('track_stock', true)->count(),
                'active_product_count' => $products->where('is_active', true)->count(),
                'quantity_on_hand' => (string) $levels->sum('quantity_on_hand'),
                'quantity_reserved' => (string) $levels->sum('quantity_reserved'),
                'inventory_value_at_selling_price_minor' => (int) $levels->sum(fn ($level) => (int) round((float) $level->quantity_on_hand * (float) ($level->product->selling_price_minor ?? 0))),
                'low_stock_count' => $levels->filter(fn ($level) => $level->product?->track_stock && $level->reorder_level !== null && bccomp((string) $level->quantity_on_hand, (string) $level->reorder_level, 4) <= 0)->count(),
            ],
        ]);
    }

    public function receivables(Branch $branch): JsonResponse
    {
        $sales = $branch->sales()
            ->where('status', '!=', 'voided')
            ->whereColumn('paid_minor', '<', 'total_minor')
            ->get();

        $buckets = [
            'current' => 0,
            'days_1_30' => 0,
            'days_31_60' => 0,
            'days_61_90' => 0,
            'days_over_90' => 0,
        ];

        foreach ($sales as $sale) {
            $outstanding = $sale->total_minor - $sale->paid_minor;
            $dueDate = $sale->due_date ?? $sale->created_at?->toDateString() ?? now()->toDateString();
            $days = (int) Carbon::now()->diffInDays($dueDate, false) * -1;

            if ($days <= 0) {
                $buckets['current'] += $outstanding;
            } elseif ($days <= 30) {
                $buckets['days_1_30'] += $outstanding;
            } elseif ($days <= 60) {
                $buckets['days_31_60'] += $outstanding;
            } elseif ($days <= 90) {
                $buckets['days_61_90'] += $outstanding;
            } else {
                $buckets['days_over_90'] += $outstanding;
            }
        }

        return response()->json([
            'data' => [
                'currency_code' => $branch->currency_code,
                'outstanding_invoice_count' => $sales->count(),
                'outstanding_minor' => (int) $sales->sum(fn ($sale) => $sale->total_minor - $sale->paid_minor),
                'buckets' => $buckets,
            ],
        ]);
    }
}
