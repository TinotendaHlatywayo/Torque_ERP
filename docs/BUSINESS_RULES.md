# Business rules

These rules are canonical for all TorqueERP modules. Where a module does not yet implement a rule, the rule still constrains design until the relevant stage lands.

## Branch and platform boundaries
- A platform may own many branches. Branch-owned data must never be read or mutated without an active membership for that branch.
- Platform administrators are separate from branch administrators. A platform flag does **not** grant branch data access; cross-branch platform access must use explicit platform permissions (Phase 3).
- A user-supplied `branch_id` or `X-Branch-Id` is never authorization. The server resolves the active membership from the authenticated user.
- Route branch parameters are authoritative. A conflicting client header must not widen access.
- Branch membership removal/deactivation takes effect on the next request because permissions are recomputed from persisted membership.

## Identity
- User account, employee record, customer record and online-customer identity are distinct concepts.
- Customer records are branch-owned in Stage 1.4. Cross-branch shared identity is deferred.
- Similar customer names, phone numbers or emails never merge automatically; creation returns duplicate warnings and candidates.
- Customers are archived, not hard-deleted, to preserve sales/history.
- Do not merge people because names, emails or phone numbers match. Staff user accounts are created/attached by authorized branch administrators.
- Public self-registration is disabled. Accounts are provisioned by an administrator.
- Do not delete users with audit/history. Deactivate memberships and revoke access.

## Roles and permissions
- Authorization is default-deny. An unknown or missing role grants no permissions.
- Branch roles are a fixed code-defined catalogue (`admin`, `manager`, `storekeeper`, `cashier`, `accountant`, `viewer`). Custom roles are deferred until an approved stage.
- A branch membership may carry explicit extra branch permissions. Platform permissions (`platform.*`) can never be granted through a branch membership, even if stored in the database.
- Every branch must keep at least one active administrator.
- Permission changes and revocation must be auditable.

## Inventory
- Products, categories and units are branch-owned. A SKU and barcode are unique within a branch, not globally. SKUs are uppercase.
- Product selling prices are stored in integer minor units with an ISO 4217 currency code; presentation must not change stored value.
- Every product has one base unit. Pack/alternate units require an explicit conversion factor through the base unit; implicit or global conversions are not allowed.
- Unknown, unconfigured or archived units cannot be used for product configuration or conversion. Conversion results must respect whole-number unit policies.
- A product base unit cannot change once alternate units or non-zero stock exist.
- Categories and units are archived, not hard-deleted. Archived references cannot be chosen for new products; historical product references remain valid.
- `stock_levels` is a balance foundation only in Stage 1.2. Stage 1.3 owns the canonical movement ledger; `stock_levels.quantity_on_hand` is its transaction-safe projection.
- Every stock change has a durable movement with branch, product, quantity/unit, source, reason, actor/system process and timestamp.
- Negative stock is not permitted: opening, correction, sale and reversal postings are rejected when the resulting balance would be below zero.
- Reversals are equal-and-opposite movements. A movement may be reversed once; reversal movements cannot be reversed.
- Opening stock can be posted once per product; later adjustments use corrections.
- Duplicate postings are prevented by branch-scoped idempotency keys and duplicate-posting guards.
- Receiving, sale, return, transfer, stocktake and loss must not double-post.

## Purchasing
- Purchase order, goods received note, supplier invoice/bill and supplier payment are separate events.
- Receiving changes stock once; billing creates liability once; payment reduces liability/cash once.

## Sales and corrections
- Sale state and payment state are distinct. Posted sales are not deleted; use authorized voids, reversals, refunds or credit notes.
- Receivables distinguish invoiced amount, cash collected and outstanding balance. Credit sales do not create money movements until collected.
- Return authorization, quantity, restock decision and refund/credit event are linked but distinct.
- Checkout posts sale, payment and stock movement in one transaction. Duplicate submissions are idempotent.
- Split payments are allowed when amounts and currency match the sale total.
- Each sale has one unique receipt.

## Returns
- Return authorization, physical restock, refund/payment reversal and credit note are linked but distinct. Restock only after an approved decision.

## Currency and time
- Every money movement records amount, currency, time, method, account and reference where relevant.
- FX conversions preserve rate, source, effective time, entered-by and rounding; historic conversions are never rewritten.
- Payment accounts are branch-owned and single-currency. Transfers are paired movements, not income or expense.
- Money movements are immutable; corrections use reversals.
- Store amounts in integer minor units with ISO 4217 currency codes. FX conversions preserve rate, source, effective time and rounding basis.
- Store timestamps in UTC and display in branch/user timezone. Business dates for reports/shifts/periods are explicit.

## Finance
- Invoiced revenue, cash collected, receivables, supplier liabilities, inventory value, COGS, expenses incurred and cash paid are separate metrics.
- A stock purchase is not automatically COGS; paying a bill is not the same as incurring an expense.
- Expenses distinguish incurred date from paid date. Supplier bills age by due date and payment allocation.
- Dashboard and reports share one metric dictionary.

## Price negotiation
- Document the order of base price, loyalty/manual discounts, floor price, tax and approval.
- Overrides require permission, reason and audit. The proposed 10% cashier threshold is configurable, not hard-coded.

## Payments
- Payment confirmation must be server-verified where a gateway exists.
- Gateway callback, browser redirect and manual confirmation are distinct. Never trust a redirect as payment proof.
- Callbacks and retried requests must be idempotent. Corrections use reversals; failed or duplicate callbacks are safe.

## Staff lifecycle
- Do not delete employees with payroll, leave, disciplinary or audit history. Deactivate/archive and revoke access.

## Demo data
- Demo data must be fictional, deterministic, relationally coherent, repeatable, branch-safe and blocked from accidental production execution.
