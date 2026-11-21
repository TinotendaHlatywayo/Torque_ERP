# Metric dictionary

## Money In
- Formula: recorded sale payments + manual money movements with direction `in`, excluding receivable collection movements that are already represented by payments.
- Date basis: payment/movement creation time, UTC.
- Currency: branch currency for payments; movement currency for manual movements.
- Included: paid sale payments, collections, manual money in.
- Excluded: unpaid invoices, voided sale payments, transfers (net zero).
- Permission: `branch.finance.view` or `branch.reports.view`.

## Money Out
- Formula: sum of money movements with direction `out`, excluding `transfer_out`.
- Date basis: movement creation time, UTC.
- Currency: movement currency.
- Included: expense payments, bill payments, manual money out.
- Excluded: transfers, voided movements.
- Permission: `branch.finance.view` or `branch.reports.view`.

## Invoiced revenue
- Formula: sum `sales.total_minor` for non-voided sales.
- Date basis: sale `created_at`, UTC.
- Currency: sale currency.
- Included: posted sales.
- Excluded: voided sales, unpaid status does not exclude invoiced revenue.

## Cash collected
- Formula: sum `sales.paid_minor` for non-voided sales.
- Date basis: payment creation time, UTC.
- Currency: payment currency.
- Included: payments recorded at checkout or as collections.
- Excluded: unpaid invoices and voided sales.

## Money Owed to Us
- Formula: sum `total_minor - paid_minor` for non-voided sales.
- Date basis: sale `created_at` or `due_date` for ageing.
- Currency: sale currency.
- Included: unpaid and partially paid sales.
- Excluded: voided sales and fully paid sales.

## Bills to Pay
- Formula: sum `amount_minor - paid_minor` for non-paid supplier bills.
- Date basis: bill `due_date`.
- Currency: bill currency.
- Included: open and partial supplier bills.
- Excluded: paid bills.

## Inventory value
- Formula: sum `stock_levels.quantity_on_hand * products.selling_price_minor`.
- Date basis: current stock level.
- Currency: branch currency.
- Included: all stock levels.
- Excluded: cost basis, because cost layers are not implemented.
- Permission: `branch.inventory.view` or `branch.reports.view`.

## Profit basis
- Formula: invoiced revenue - expenses incurred.
- COGS: cost of goods sold is not yet available; profit basis excludes COGS until cost layers are implemented.
- Date basis: sale creation date and expense incurred date, UTC.
- Currency: branch currency.
- Included: non-voided sales and incurred expenses.
- Excluded: voided sales, COGS, unpaid status does not exclude invoiced revenue.

## Cash position
- Formula: sum of money movements, adding `in` amounts and subtracting `out` amounts.
- Date basis: movement creation time, UTC.
- Currency: movement currency; multi-currency totals are not converted unless an FX rate is applied.
- Included: all money movements.
- Excluded: transfers net to zero across accounts.

## Reorder suggestion (Stage 7.6)
- Formula: average daily sales = SUM(`sale_items.quantity` for `sales.status='posted'` in the reorder sales window) / `sales_window_days`; reorder point = average daily sales x (`lead_time_days` + `days_of_cover`); suggested order = MAX(0, reorder point - (`stock_levels.quantity_on_hand` - `quantity_reserved`)), rounded up to whole base units.
- Date basis: `sales.created_at`, UTC; as-of controls the window end.
- Included: posted non-void sale item quantities in the window, all branch stock levels with a product/base unit.
- Excluded: voided/cancelled sales, reserved quantity, sales outside the window. Products without sales in the window get `insufficient_sales_history` and no suggestion.
- Source: `DashboardMetricsService` via `GET /api/branches/{branch}/dashboard/v2`.
- Permission: `branch.inventory.view`.

## Stock ageing / dead stock (Stage 7.6)
- Formula: age days = ABS(as-of date - MAX(`stock_movements.created_at`) for the product). `active` below slow threshold, `slow_moving` at or above slow threshold, `dead_stock` at or above dead threshold, `no_stock` when on hand <= 0, `unknown`/null classification when stock exists but no movement history.
- Bucket value: `stock_levels.quantity_on_hand * products.selling_price_minor` (selling-price basis, same as inventory value; not cost).
- Date basis: canonical stock movement `created_at`, UTC. Reversals count as movement history.
- Included: branch stock levels, last ledger movement per product.
- Excluded: movements from other branches; no inferred movement date for missing history (warning instead).
- Permission: `branch.inventory.view`.

## ABC analysis (Stage 7.6)
- Formula: product revenue = SUM(`sale_items.total_minor`) over the ABC sales window for posted non-void sales; sort descending; class A while cumulative share <= A threshold, B while cumulative share <= B threshold, otherwise C.
- Date basis: `sales.created_at`, UTC.
- Included: posted sale items with revenue > 0 in the window.
- Excluded: voided sales, out-of-window sales, zero/negative revenue lines. If total revenue is 0 the API returns empty classes and `insufficient_sales_data`.
- Permission: `branch.inventory.view`.

## Cash forecast and cash target (Stage 7.6, FORECAST)
- Beginning cash: `cash_position_minor` from `FinanceMetricsService` at the as-of date.
- Average daily inflow = (non-void sale `paid_minor` in history window + manual `money_movements` direction `in` excluding `receivable_collection`) / `history_window_days`; average daily outflow = direction `out` movements excluding `transfer_out` / `history_window_days`.
- Forecast closing cash = beginning cash + (average daily inflow - average daily outflow) x `horizon_days` - unpaid supplier bills due inside the horizon (subtracted once). If cash events are below the configured minimum, all projections are null.
- Cash target = all unpaid `bills_to_pay_minor` from `FinanceMetricsService` plus incurred expenses with no `paid_date`. It is an illustrative liquidity requirement, not a budget, statement or approved target.
- Label: `FORECAST`; `authoritative_finance_statement=false`. Warnings cover insufficient cash history, mixed currencies and future as-of dates.
- Included: branch-currency minor units, no FX conversion; sale payments are dated by sale creation date.
- Excluded: seasonality, growth, payment-timing models, transfers (net zero), receivable collection movements (already in sale `paid_minor`).
- Permission: `branch.finance.view`.

## Phase 7 integration acceptance fixtures (Stage 7.7)
- No metric formula changed in Stage 7.7; these values are acceptance assertions over the existing definitions above.
- Job fixture: labour `2500`, parts `2000`, estimate/manual invoice `4500`, stock `10 → 8`, one `job_consumption` movement, zero expenses and zero money movements.
- Transfer/stocktake fixture: source `10 → 7 → 6`, destination `0 → 3`, one `transfer_out`, one `transfer_in`, one `correction`; duplicate receipt/approval add no movements.
- Sale/loyalty fixture: subtotal `2000`, discount `100`, paid/revenue `1900`, net qualifying `1900`; quote `7%`/`1860`, approved `13%`, floor `1900`; return `950` reduces net qualifying to `950` and removes eligibility.
- Report/dashboard fixture: sales revenue `1900`, discount `100`, cash `1900`, outstanding `0`; stock quantity `8` and selling-price value `8000`; finance revenue/money-in/profit basis `1900`, cash position `0`; ABC revenue `1900`; active stock value `8000`; labelled cash forecast `190` in / `0` out / `950` forecast / `0` target.
- Reorder: one planning row with `needs_reorder=false` and `suggested_order_units=0` is expected for a product with sales history and ample stock; `needs_reorder_count=0` is the acceptance assertion.
- COGS: BLOCKED. No cost basis or cost layers exist; acceptance asserts no duplicate expense/stock/discount postings instead of a COGS amount.

## Performance metrics (Stage 8.2)
- `sales.index` benchmark: median-ish wall time from test harness, query count from `DB::getQueryLog()`, payload bytes from JSON response length.
- `items_count`: number of sale items omitted from the sales summary payload; full lines remain available on the show endpoint.
- Frontend chunk size: Vite build output size and gzip size; main bundle target reduced via route-level code splitting.
