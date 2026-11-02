<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\MoneyMovement;
use App\Models\SupplierBill;

final class FinanceMetricsService
{
    public function metrics(Branch $branch, ?string $asOf = null): array
    {
        $asOfDate = $asOf ?? now()->toDateString();

        $moneyIn = (int) $branch->sales()
            ->where('status', '!=', 'voided')
            ->sum('paid_minor');

        $manualMoneyIn = (int) MoneyMovement::where('branch_id', $branch->id)
            ->where('direction', 'in')
            ->whereNotIn('source', ['receivable_collection'])
            ->sum('amount_minor');

        $moneyIn += $manualMoneyIn;

        $moneyOut = (int) MoneyMovement::where('branch_id', $branch->id)
            ->where('direction', 'out')
            ->whereNotIn('movement_type', ['transfer_out'])
            ->sum('amount_minor');

        $expensesIncurred = (int) Expense::where('branch_id', $branch->id)
            ->where('incurred_date', '<=', $asOfDate)
            ->sum('amount_minor');

        $invoicedRevenue = (int) $branch->sales()
            ->where('status', '!=', 'voided')
            ->sum('total_minor');

        $moneyOwedToUs = (int) $branch->sales()
            ->where('status', '!=', 'voided')
            ->selectRaw('COALESCE(SUM(total_minor - paid_minor), 0) as outstanding')
            ->value('outstanding');

        $billsToPay = (int) SupplierBill::where('branch_id', $branch->id)
            ->where('status', '!=', 'paid')
            ->selectRaw('COALESCE(SUM(amount_minor - paid_minor), 0) as outstanding')
            ->value('outstanding');

        $inventoryValue = (int) $branch->products()
            ->join('stock_levels', function ($join) use ($branch) {
                $join->on('stock_levels.product_id', '=', 'products.id')
                    ->where('stock_levels.branch_id', '=', $branch->id);
            })
            ->selectRaw('COALESCE(SUM(stock_levels.quantity_on_hand * products.selling_price_minor), 0) as value')
            ->value('value');

        $cashPosition = (int) MoneyMovement::where('branch_id', $branch->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount_minor ELSE -amount_minor END), 0) as net")
            ->value('net');

        return [
            'as_of' => $asOfDate,
            'money_in_minor' => $moneyIn,
            'money_out_minor' => $moneyOut,
            'invoiced_revenue_minor' => $invoicedRevenue,
            'expenses_incurred_minor' => $expensesIncurred,
            'profit_basis_minor' => $invoicedRevenue - $expensesIncurred,
            'money_owed_to_us_minor' => $moneyOwedToUs,
            'bills_to_pay_minor' => $billsToPay,
            'inventory_value_minor' => $inventoryValue,
            'cash_position_minor' => $cashPosition,
            'currency_code' => $branch->currency_code,
        ];
    }
}
