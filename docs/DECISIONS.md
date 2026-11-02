
## 0.4 Schema baseline
- **IDs**: ULID for domain entities; foreign keys match types.
- **Timestamps**: UTC in DB; display in branch/user timezone.
- **Archive/soft-delete**: softDeletes on platforms/branches/branch_users; audit_logs append-only (no soft delete).
- **FKs/uniques**: enforced at DB level with appropriate cascade/restrict.
- **Transactions/idempotency**: required for multi-step and retried operations.
- **Document numbering**: per (branch_id, document_type) with atomic allocation.

## 0.5 Auth and tenant
- **Auth**: Laravel Breeze API + Sanctum for SPA/auth tokens
- **Branch resolution**: Server-side from authenticated membership via ResolveBranchContext middleware; client-supplied branch_id never used for authorization
- **Authorization**: Default-deny via BranchOwnedPolicy
- **Sessions**: HttpOnly cookies, SameSite=lax, secure configurable
- **Rate limiting**: Use Laravel default throttling (to be configured per routes)

## 0.6 UI system
- **UI framework**: Tailwind CSS + shadcn/ui-style components (reusable, accessible)
- **State components**: Loading (skeleton + spinner), empty, error, success, permission denied
- **Layouts**: Public, authenticated app, auth shells with responsive design
- **i18n**: Simple translation system, extensible for more locales
- **Theme**: Light/dark with CSS variables
- **A11y**: Focus-visible, ARIA labels, skip links, semantic HTML

## 0.7 Routing and skeleton
- **Routing**: React Router v6 with public/auth/app/platform routes
- **Auth flow**: Login page wired to Breeze API (Sanctum) endpoints
- **Tenant**: Branch workspace and platform console separated
- **Data**: TanStack Query for API calls

## 1.1 Users, roles and permissions
- **Roles**: Fixed code-defined branch role catalogue (`admin`, `manager`, `storekeeper`, `cashier`, `accountant`, `viewer`). Custom role CRUD is deferred; adding a role is a reviewed code change.
- **Permissions**: Code-defined `branch.*` and `platform.*` catalogues in `App\Support\Permissions`. Branch membership stores optional explicit extra branch grants; platform permissions are ignored in branch context.
- **Platform separation**: `users.is_platform_admin` is a platform-level flag for Phase 1.1. It does not grant branch access. Granular platform roles are deferred to Phase 3.
- **Authorization**: Default-deny middleware (`branch.permission`, `platform.permission`) plus server-side branch resolution. Route branch wins over `X-Branch-Id`; both are checked against membership.
- **Revocation**: Permissions are recomputed from the database on every request. Deactivating/removing a user's last active branch membership deletes API tokens and database sessions immediately.
- **User provisioning**: Public registration is disabled. Branch administrators create/attach users by email from the branch workspace.
- **Last admin**: A branch cannot demote, deactivate or remove its last active administrator.
- **Identity**: A user can belong to multiple branches; employee HR profiles remain deferred.

## 1.2 Catalogue and stock foundation
- **Branch ownership**: Products, categories and units are branch-owned. Shared cross-branch catalogue items are deferred; the same SKU/barcode may exist independently in different branches.
- **Identifiers**: SKU is required, normalized to uppercase and unique per branch. Barcode is optional, unique per branch when present, and limited to one barcode per product in this stage. Multiple/pack barcodes are deferred.
- **Money**: Prices are stored as integer minor units with the branch currency's ISO 4217 exponent. The API accepts a major-unit decimal string and returns both minor units and a formatted string.
- **Archive semantics**: Products, categories and units use `is_active=false` for archive. No hard-delete endpoint is exposed. Archived categories/units cannot be selected for new products; existing references are retained.
- **Categories**: Category slugs are unique per branch and stable on rename unless an explicit slug is supplied. Parent-child cycles and cross-branch parents are rejected.
- **Units and conversion**: Each product has one base unit. Alternate units must be configured explicitly with a conversion factor through the base unit; there are no implicit global conversion tables. Unknown, unconfigured or inactive units are rejected. Conversion factors use six decimals and conversion results round half-up to six decimals.
- **Base unit changes**: A base unit cannot change while alternate units or non-zero stock exist.
- **Stock foundation**: `stock_levels` provides branch/product balances defaulting to zero. Stage 1.2 does not post stock movements; Stage 1.3 owns the canonical movement ledger and posting rules.
- **Permissions**: Catalogue APIs reuse `branch.inventory.view` and `branch.inventory.manage` rather than adding overlapping catalogue permissions.
- **Frontend**: `/app/products` provides product search/filter/pagination, product and category/unit management, alternate units and an explicit conversion tool.

## 1.3 Canonical stock ledger
- **Canonical ledger**: `stock_movements` is the only durable stock history. `stock_levels.quantity_on_hand` is a transaction-safe projection updated in the same transaction.
- **Movement types**: `opening`, `sale`, `correction`, `reversal`.
- **Units**: every movement stores the selected unit, converted base quantity, original unit quantity and conversion factor through the product base unit.
- **Idempotency**: `idempotency_key` is unique per branch. Replaying the same key with the same payload returns the original movement; a different payload returns `409`.
- **Duplicate posting guards**: one opening movement per product; one reversal per original movement; reversal movements cannot be reversed.
- **Negative stock**: posting is rejected when the resulting balance is below zero. No overselling is permitted.
- **Concurrency**: balance updates use `SELECT ... FOR UPDATE` on the stock level row and a three-attempt DB transaction. Concurrent sale attempts are tested on MariaDB.
- **Reversal**: reversals post an equal and opposite movement and restore the balance if sufficient stock exists.
- **Audit**: every movement records branch, product, quantity, unit, conversion, source, reason, actor and timestamp.

## 1.4 Customer records
- **Branch ownership**: customers are branch-owned in Stage 1.4. Cross-branch shared customer identity is deferred.
- **No silent merge**: creating a customer with a similar name, phone or email creates a new record and returns duplicate warnings/candidates.
- **Duplicate detection**: case-insensitive name/email and normalized phone comparison within the branch; warnings do not block creation.
- **Archive**: customers are archived with `is_active=false`; no hard delete endpoint.
- **Privacy**: customer data is branch-scoped and requires `branch.customers.view` or `branch.customers.manage`.

## 1.5 POS cart and price calculation
- **Order**: base unit price -> quantity -> discount -> floor price -> tax -> approval.
- **Price snapshots**: cart pricing returns immutable line and cart totals with unit price, quantity, discount, floor, tax and totals.
- **Discount threshold**: `POS_MAX_DISCOUNT_PERCENT_WITHOUT_APPROVAL` defaults to 10 and is read from `config/pricing.php`; it is not hard-coded.
- **Approval**: discounts above the threshold or prices below floor require approval and are audited with actor and reason.
- **Tax**: tax is calculated on the net line total after discount/floor enforcement.

## 1.6 Checkout, payments and receipt
- **Transaction**: checkout posts sale, items, payments, stock movements and receipt in one DB transaction.
- **Idempotency**: branch-scoped `Idempotency-Key` replays the original sale or rejects a changed payload.
- **States**: sale status (`posted`) and payment status (`unpaid`, `partial`, `paid`) are stored separately.
- **Payments**: split payments are supported in branch currency; amounts must equal the sale total.
- **Receipts**: each sale has one unique receipt number allocated under lock.
- **Stock**: tracked products post a sale movement; insufficient stock rolls back the entire checkout.

## 1.7 Sales history and correction foundation
- **History**: branch-scoped sales list/detail with status and payment filters.
- **Void**: authorized void reverses stock and marks sale/payment voided; duplicate void is idempotent.
- **Returns**: return requests record authorization, quantity, restock decision and refund/credit state; stock restock and refund execution are documented follow-up work.
- **No hard deletes**: posted sales remain and are corrected through void/reversal/return records.

## 1.8 End-to-end acceptance
- **Acceptance coverage**: product setup, opening stock, customer, checkout, payment, receipt, history, duplicate checkout, invalid discount, unauthorized override, tenant isolation and oversell protection are covered by `EndToEndTest`.
- **Phase 1 defects fixed during acceptance**: none remaining; migration and build checks pass.

## 2.2 Payment accounts and cash movements
- **Payment accounts**: branch-owned cash, bank and mobile money accounts with a currency.
- **Money movements**: immutable movements with amount, currency, direction, method/account, time, reference, reason, actor, source and reversal link.
- **Transfers**: represented as linked paired `transfer_out` and `transfer_in` movements; they are not income or expense.
- **Reversal**: a reversal is an opposite movement linked to the original; original movements are never edited or deleted.
- **Multi-currency**: each account has one currency; movements must match the account currency. Cross-currency transfers are rejected.
- **Reconciliation**: statement reconciliation is not implemented; do not claim bank reconciliation.

## 2.3 Expenses and bills to pay
- **Incurred vs paid**: expenses store `incurred_date`, `paid_date`, and `status`; paying an expense creates an `adjustment_out` money movement.
- **Bills to pay**: supplier bills track amount, paid amount, due date and status; payments allocate against the bill and update ageing buckets.
- **Ageing**: buckets are current, 1-30, 31-60, 61-90 and 90+ days based on due date.
- **Stock purchase vs COGS**: a stock purchase creates inventory value and a supplier liability; it is not COGS until the stock is sold. Paying a bill reduces cash/liability and is not an expense.
- **Attachments**: attachment path metadata is supported; safe file upload storage is documented as follow-up.

## 2.4 Receivables and collections
- **Invoice**: a sale is the invoice; `total_minor` is invoiced amount, `paid_minor` is cash collected, and their difference is outstanding.
- **Credit sales**: checkout may be created with partial or no payment; payment status becomes `unpaid` or `partial`.
- **Collections**: payments recorded after sale create a `Payment`, update sale totals/status, and post an `adjustment_in` money movement.
- **Deposits/partial payments**: supported through collection amounts up to the outstanding balance.
- **Write-offs/credit notes**: deferred; not implemented in this stage.
- **Double-counting prevention**: collections post money movements only when cash is received; invoices without payments do not create money movements.

## 2.5 Finance metrics and FX history
- **Metrics formulas**: Money In = recorded sale payments + non-collection manual money movements in; Money Out = non-transfer money movements out; Profit basis = invoiced revenue - expenses incurred (COGS deferred until cost layers exist); Money Owed to Us = outstanding receivables; Bills to Pay = outstanding supplier bills; Inventory value = stock on hand at selling price (provisional until cost layers exist); Cash position = net money movements.
- **FX history**: `fx_rates` preserves source, effective time, entered-by and rounding mode. Conversions use the latest effective rate at the requested time and never rewrite historical conversions.
- **Tax**: default tax rate is configurable via `TAX_DEFAULT_RATE_PERCENT`; per-line tax remains explicit.

## 2.6 Documents and templates
- **Interpretation**: a document type is a workflow output (receipt, invoice, supplier bill); a template is a versioned layout for one document type. Multiple named templates may exist per type, with one active version per name.
- **Preview/versioning/validation**: templates are versioned on save, validated against an allow-list of placeholders, and previewed with sample or supplied data.
- **Print CSS**: receipt views use print CSS to hide navigation and controls.
- **QR codes**: QR codes point to `/api/verify/{documentType}/{documentId}`, which returns real validity and document data.

## 2.8 Financial reconciliation acceptance
- **Reconciliation**: sale/payment, supplier bill/payment, expense incurred/paid, credit/collection, returns, reversals, FX and report totals are covered by `FinancialReconciliationTest`.
- **No double-counting**: receiving and purchases create inventory/liability, not COGS; expenses are separate; money movements are created only for actual cash movement; transfers are paired.
- **Voided sales**: excluded from receivables and revenue metrics.

## 3.1 Branch registration and lifecycle
- **Lifecycle states**: pending, active, suspended, archived.
- **Registration**: platform owners register branches; approval activates them.
- **Suspension**: suspended branches reject writes but allow authorized historical reads.
- **Archive**: archived branches are soft-deleted; branches with history are never hard-deleted.
- **Uniqueness**: branch slug and host are unique.

## 3.2 Membership and context switching
- **Deterministic selection**: when no branch is explicitly requested, the first active membership ordered by creation time is used.
- **Server verification**: every branch-scoped request re-checks membership, active status, host and suspension state.
- **Context switching**: clients may send `X-Branch-Id`; the server validates it against membership and never trusts it for authorization.
- **Leak prevention**: branch-scoped queries, route binding, permissions and audit logs are used; caches must include branch in their keys.
