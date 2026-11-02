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
