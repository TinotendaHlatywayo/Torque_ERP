
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

## 3.3 Platform/branch settings
- **Scopes**: platform and branch settings are stored separately; user, device and document settings are deferred unless a built feature needs them.
- **Precedence**: branch settings override platform settings; platform settings override defaults. No silent inheritance or copying occurs.
- **Validation**: only allow-listed setting keys are accepted for each scope.
- **Audit**: every setting change is audited.

## 3.4 Cross-branch reports and exports
- **Platform-only comparison**: branch comparison metrics are available only through platform permissions.
- **Authorization**: queries and exports enforce platform permissions; branch users cannot access platform reports.
- **Exports**: export files are stored privately, scoped to the platform, expire after 24 hours and are audited.

## 3.5 Audit and platform acceptance
- **Audit coverage**: login/logout, permission changes, price overrides, stock corrections, sale void/return, payments, branch lifecycle and exports are audited.
- **Redaction**: sensitive values such as passwords, tokens, card numbers and CVVs are redacted in audit payloads.
- **Retention**: audit retention defaults to 365 days via `AUDIT_RETENTION_DAYS`.
- **Immutability**: audit logs cannot be updated or deleted by application code.
- **Platform-admin boundaries**: platform admins manage platforms/branches and cross-branch reports but do not receive branch data without an active branch membership.

## 4.6 Interruption/recovery acceptance
- **Exactly-once**: duplicate sync retries return the prior result without repeating stock or payment effects.
- **Invalid payload**: rejected with validation errors.
- **Expired session**: rejected with 401.
- **Unavailable offline**: branch registration, user management, settings, reports, exports, supplier bills, FX, templates and platform administration.

## 7.1 Catalogue fitment and OEM search
- **Additive data**: fitment/OEM live in `product_fitments` and `product_oem_refs`, branch-scoped, linked to products. Core `products` semantics unchanged.
- **Sources required**: imports must provide `source`, `source_ref`, `format=json`, and `Idempotency-Key`. No compatibility invented; results always display source.
- **Search priority**: exact SKU/barcode first (reliable), then OEM exact, then fitment filters (make/model/year/engine) with deterministic typo tolerance (Levenshtein<=1, tokens length>=5). Tests must cover false positives and no results.
- **Permissions**: `branch.catalogue.view` for search, `branch.catalogue.manage` for imports (added to branch roles per policy).

## 5.1 Public rendering and SEO
- **Rendering**: Laravel Blade serves indexable public pages as server-rendered HTML; React/Vite remains the private ERP workspace.
- **Canonical host**: public canonical and sitemap URLs use `APP_URL`; the separate team workspace link uses `FRONTEND_URL`.
- **Crawling**: production robots allow public content and disallow private app/API paths; non-production robots block crawling. The SPA HTML shell is also `noindex`.
- **Structured data**: public business details use only verified Taziva Motor Spares name, Rusape locality and Zimbabwe country code; no unsupported hours, contact details, ratings or inventory claims.

## 5.2 CMS and media
- **Publishing**: public pages are top-level clean slugs, rendered from an immutable version selected by `published_version`; editing or rollback creates a draft and leaves the live revision unchanged until publish.
- **Content safety**: page sections are plain text and escaped by Blade; arbitrary HTML is not accepted. Reserved routes cannot be used as CMS slugs.
- **Authorization**: page, revision and media management requires the platform `content.manage` permission; previews and originals are served from private local storage.
- **Uploads**: accept JPEG, PNG and WebP up to 8 MiB and 4000×4000 pixels. SVG and executable formats are rejected. GD generates resized WebP/AVIF variants only when the encoder is available; original files remain private and public media is served only when referenced by a published revision.
- **Storage**: CMS media uses Laravel's private `local` disk. Backups must include `storage/app/private`.

## 5.3 Public catalogue
- **Branch scope**: only the active branch whose slug matches `PUBLIC_CATALOGUE_BRANCH_SLUG` supplies the public catalogue; default is the seeded `tms-rusape-main` branch.
- **Publication**: products are hidden until `is_public` is enabled with a unique public slug and customer-facing description. Internal description, SKU, supplier/cost fields and stock quantities are never rendered publicly.
- **Availability**: online pages ask customers to confirm availability with the store and make no live stock promise. Archived product URLs return HTTP 410; unpublished URLs return 404.
- **Structured data**: public product schema uses the visible product name, public description, image, category and current branch price. It omits availability and review claims.

## 5.4 Stock reservations
- **Hold behavior**: a customer request creates a short `awaiting_payment` hold only when current unreserved stock is sufficient; branch staff confirm current details with the customer.
- **Stock integrity**: `quantity_reserved` is held separately from `quantity_on_hand`. Every stock ledger posting must leave on-hand at or above reserved quantity.
- **Release**: cancellation and expiration release a hold exactly once. Expiration runs every minute and is also reconciled before a new hold for the same product.
- **Privacy**: customer contact fields are encrypted at rest, visible only through authorized branch access, and omitted from the customer's private status page. The status URL is an unguessable token and is marked noindex/no-store/no-referrer.
- **Payment boundary**: a reservation starts awaiting payment. Phase 5.5 may confirm it only after the configured provider verifies payment; a return redirect alone is not proof of payment.

## 5.5 Paynow integration
- **Provider**: use Paynow Zimbabwe hosted checkout for the configured reservation currency. Credentials are supplied only through `PAYNOW_INTEGRATION_ID` and `PAYNOW_INTEGRATION_KEY`; they are not stored in source control.
- **Proof of payment**: the customer return redirect is informational. A reservation becomes confirmed only after a hash-verified Paynow callback and a hash-verified server-side poll report a paid status (`Paid`, `Awaiting Delivery` or `Delivered`) with matching merchant reference and amount.
- **Request signing**: Paynow SHA-512 hashes concatenate the URL-decoded form values in message order, excluding `hash`, then append the integration key. The resulting hex digest is uppercase.
- **Idempotency and ordering**: each reservation has one stable merchant reference. Duplicate status events do not repeat stock effects; a paid event after a released/expired hold is marked `paid_late` for staff review and never reclaims stock.
- **Network**: provider calls have bounded connect/overall timeouts. Initiation is not automatically retried after an ambiguous timeout. Paynow requires a publicly reachable HTTPS `APP_URL` for callbacks; localhost preview cannot receive live results.
- **Card data**: customers pay on Paynow's hosted page. TorqueERP stores no card number, CVV, or payment instrument token.

## 5.6 Reservation notifications and verification
- **Delivery evidence**: notification intent is stored in `reservation_notification_deliveries` before a queue job is dispatched. The unique reservation/event/recipient key prevents provider retries or customer request replays from creating another delivery.
- **Recipient boundary**: customer mail is sent only to the supplied reservation email. Branch mail is limited to active branch members with `branch.reservations.manage`, and that permission is checked again when the job executes.
- **Failure policy**: mail failures retry three times with a 15/60 second backoff. A terminal failure is visible in delivery state but never reverses a payment, changes a reservation, or releases/reserves stock.
- **Local browser check**: TorqueERP currently renders on its documented `127.0.0.1:8000` development server. `tms.lvh.me` is routed to a separate local Kairo CORE host and returns 404, so the desired local lvh.me host needs a deliberate Apache virtual-host change before it can be used for TorqueERP verification.
- **Dependency risk**: `composer audit --locked` on 2026-11-08 reported six Symfony advisories in the current lockfile. Dependency upgrades are deferred to Stage 8.1 security hardening because they are outside this bounded functional stage.

## 6.1 Employee records and lifecycle
- **Separate identity**: `employees` is a branch-owned HR record. `user_id` is an optional explicit link to a login account; creating or changing one never creates a login or grants access.
- **Lifecycle**: states are `hired`, `active`, `suspended`, `terminated` and `archived`. Allowed moves are hired → active/suspended/terminated, active ↔ suspended, either → terminated, and terminated → archived. Archived records are read-only.
- **No destructive delete**: termination and archival preserve the record, audit history and future payroll/leave references. A mistaken record is terminated then archived.
- **Sensitive data**: national ID, date of birth, address, emergency contacts and HR notes require `branch.hr.sensitive.view`; responses explicitly flag redaction. Audit snapshots redact the same fields.
- **Access revocation**: terminating or archiving a linked employee deactivates their membership in that branch and revokes tokens/sessions when they have no other active branch access. The login account itself stays active so the employee and login lifecycles remain separate. The last active branch administrator cannot be removed this way.
- **Permissions**: administrators and managers receive HR view/manage/sensitive permissions by default; custom branch permissions can grant view-only HR access. Reinstating an employee does not automatically restore ERP access.

## 6.2 Leave, attendance and disciplinary records
- **Leave day counting**: the stored `days_requested` is computed from the inclusive date range, excluding Saturday/Sunday unless the leave type explicitly counts weekends. Public holidays are not modelled in Stage 6.2; approval remains the manual control and the limitation is documented for payroll integration.
- **Annual limit basis**: approved days are counted by the request's start-date year, not by calendar days split across years. A request spanning two years is therefore charged to the start year.
- **Approval separation**: `branch.hr.leave.approve` is independent of `branch.hr.manage`, allowing a supervisor to decide leave without gaining full HR administration. Approval rechecks overlap and annual limits so a request cannot become valid merely because it waited.
- **Auto-approval**: a leave type with `requires_approval=false` is approved at submission, with the submitting user recorded as the decider and an explicit auto-approval note. This is a configuration choice per leave type, not a role bypass.
- **Attendance**: attendance is an operational fact, not a payroll calculation. Each branch/employee/work date has one record; re-posting updates it and writes an audit event. Future dates and cross-branch employees are rejected.
- **Disciplinary sensitivity**: the whole disciplinary record, including attachments, requires `branch.hr.sensitive.view`. Mutation additionally requires `branch.hr.manage`. A closed record is immutable except through a new record, preserving investigation integrity.
- **Attachments**: HR files live on the private local disk and are never exposed as public URLs. Downloads set private/no-store and nosniff headers; filenames are basenames, MIME types are server-detected and accepted extensions are the allowlist.
- **Audit**: leave, attendance, disciplinary and attachment actions are audited. Disciplinary audit snapshots retain the sensitive detail fields for investigation integrity; Stage 6.2 has no audit viewer, and any future audit exposure must enforce `branch.hr.sensitive.view`.

## 6.3 Salary grades, payroll and loans
- **Money representation**: all payroll amounts are integer minor units in the database to avoid floating-point drift; the UI converts user-entered major units at the boundary.
- **Rounding**: calculations use half-up rounding to minor units. Calculation ordering and snapshot inputs are deterministic for a given branch configuration, period, attendance and loan state.
- **Salary basis**: monthly grades prorate by payable weekdays / period weekdays. Hourly grades use attendance `worked_minutes`; overtime comes from `overtime_minutes` and the configured grade rate/multiplier.
- **Working days**: Saturdays and Sundays are excluded. Public holidays are not modelled, so proration is an operational approximation until an authoritative holiday calendar is supplied.
- **Statutory values**: no Zimbabwe rates are invented. Rates, fixed amounts and effective windows are configurable under `PayrollStatutoryRule`; configured values remain a production blocker until legally confirmed.
- **Approval lock**: an approved run locks its period. Regeneration is only possible for open periods and replaces the draft, making draft output disposable and approved output stable.
- **Loans**: approval applies installments and writes loan repayments atomically with payslips. Reapproval is impossible because the period is locked.
- **Adjustments**: approved-period corrections are pending and consumed by the next open run. Applied adjustments can be reversed only through an opposite pending adjustment, preserving the audit trail.
- **Access**: view, manage and approve are separate permissions. Payees are selected from a payroll-permission employee options endpoint so payroll staff do not need full HR permissions.

## 6.4 Payroll payment and reconciliation
- **Accrual basis**: approved runs create one incurred expense for gross plus employer statutory cost; employee net payments are cash allocations, not the full labour cost.
- **Cash basis**: each payroll allocation posts through the canonical money movement ledger as an outflow; no parallel cash ledger was introduced.
- **Partial payments**: allocations are cumulative and capped at approved net pay. Replayed idempotency keys return the original allocation rather than posting another movement.
- **Reconciliation**: run totals, payslip lines, allocations, money movements and accrual existence are checked together; FAIL means do not treat the run as settled.
- **Payslip access**: views require `branch.payroll.view`; export requires the separate `branch.payroll.export` permission.
- **Residual risk**: statutory remittances and employer cost settlement are accrued but not separately allocated as cash movements in this stage.

## 6.5 Tasks and notifications
- **Separation of concerns**: tasks hold operational state, task events hold immutable history, and task completions hold human notes; notification deliveries are a separate delivery ledger.
- **Delivery model**: both in-app and email channels create rows with attempts and status; in-app delivery marks rows sent without contacting the mail transport.
- **Preferences**: defaults are enabled, with explicit per-branch/user/channel/event rows only when displayed or changed.
- **Failure isolation**: notification failure only updates the delivery row; task state and completion history are not rolled back or replayed by the notification worker.
- **Idempotency**: one delivery row per task/event/recipient/channel prevents duplicate mail when task events are retried.

## 6.6 HR/payroll acceptance
- **Acceptance evidence**: `HrPayrollAcceptanceTest` is the executable Phase 6 acceptance fixture set; it asserts finance/audit side effects rather than only HTTP status codes.
- **Production blockers**: statutory rates, tax filing and leave-pay effects are marked `BLOCKED` in configuration and must be confirmed by the owner/professional advisers before production use.
- **Correction model**: locked runs remain immutable; post-lock adjustments are pending records and never silently mutate an approved run.

## 7.2 Service catalogue and job cards
- **Domain naming**: the job card model/table is `ServiceJob`/`service_jobs` because Laravel already reserves `jobs` for the queue table; API paths remain `/jobs` for readability.
- **State machine**: transitions are explicit and validated server-side. `cancel` stores `previous_status`; `reopen` restores it instead of inferring a state, preventing accidental reuse of completed jobs.
- **Stock**: part consumption is the only stock side effect and posts through the canonical `StockLedger` as `job_consumption`. No reversal is issued on cancel/reopen because the parts may already be physically used.
- **Idempotency**: part lines are unique by job + `Idempotency-Key`, and the stock ledger is keyed by the resulting part line ID. A different payload with the same key is a 409, not a silent replay.
- **Invoices**: jobs link to an existing branch `Sale`; the job engine never creates or voids invoices. Auto-generation was deliberately excluded to avoid inventing tax/pricing rules.
- **Warranty**: warranty references are outside this stage and marked NOT RUN/unsupported until explicitly scoped.

## 7.3 Stock transfers and stocktakes
- **Transfer product mapping**: source and destination products are explicit fields on each transfer. The API does not assume SKUs match across branches and does not invent a conversion factor.
- **Base units**: both transfer legs post in the selected products' base units. Operational equivalence between base units is an explicit master-data assumption and remains BLOCKED until confirmed by the owner.
- **State machine**: dispatch, in-transit, receive and cancel are separate actions. In-transit is informational and does not move stock; only dispatch and receipt post movements.
- **Movement timing**: source stock leaves at dispatch and arrives at destination only at receipt, matching physical transit and preventing destination oversell before receipt.
- **Cancellation**: cancelling before receipt reverses only the source dispatch. A received transfer is terminal; destination receipt is never silently reversed.
- **Authorization**: cross-branch transfers require active membership and `branch.transfers.manage` in both branches; view/manage/approve permissions are default-deny and branch-scoped.
- **Idempotency**: deterministic internal keys (`stock-transfer-dispatch:{id}`, `stock-transfer-receipt:{id}`, `stock-transfer-cancel:{id}`, `stocktake-adjustment:{lineId}`) make state transitions and ledger postings replay-safe without exposing a new client header contract.
- **Stocktakes**: expected quantity is a locked snapshot of on-hand balance; nonzero variance requires a reason; review and approval are separate actions and approval is limited to holders of `branch.stocktakes.approve`.
- **Schema naming**: MariaDB's 64-character identifier limit is handled with short named FK constraints (`st_source_branch_fk`, etc.). The `foreignUlid` length argument is not an index name.
- **Frontend**: `/app/transfers` uses the authenticated membership list for authorized destination branches and hides actions without the relevant permission. Browser runtime verification is NOT RUN in this stage.

## 7.4 Loyalty and win-back
- **Source of truth**: qualification is computed from existing `sales` and `sale_returns` records; no parallel sales ledger is invented. A separate `loyalty_sale_events` table records idempotent sale/import events so duplicate submissions cannot double-count.
- **Idempotency**: events are unique per branch and `event_key`, with a second unique branch-scoped hash of customer, source reference, amount and occurred-at. Same key plus different facts returns 409; same facts under a different key returns the original event.
- **Configuration**: defaults live in `config/loyalty.php` and are materialised into one `loyalty_configs` row per branch on first access. The inactivity window and discount/approval thresholds are therefore explicit branch data visible via API, not a hidden constant.
- **Recalculation**: qualification is a snapshot, not a live aggregate. An explicit recalculation endpoint ingests committed sales, subtracts documented returns and upserts one row per branch/customer. This keeps reads cheap and makes the calculation auditable and repeatable.
- **Returns**: all linked `sale_returns` rows count as documented returns except explicit `cancelled`/`rejected` statuses; the current workflow only creates `requested` returns, so all existing returns reduce qualification.
- **Stacking**: the existing `PriceCalculator` remains the single place that enforces floor price and tax. Loyalty and manual discount percentages are combined before being passed to it, and branch configuration decides the approval threshold. Approval reasons are audited via the existing `price.override.approved` action.
- **Privacy**: win-back candidates require explicit `marketing_consent_at`, a supported `marketing_channel` and `marketing_channel_active`. No delivery integration exists, so export is a redacted stub and actual outreach is NOT RUN/BLOCKED.
- **Frontend**: `/app/loyalty` reuses the existing AppShell, permission-aware states and TanStack Query patterns. Browser runtime verification is NOT RUN in this stage.

## 7.5 Assets and maintenance
- **Source links are informational**: an asset points at an existing branch `Expense`, `SupplierBill` or `MoneyMovement` for provenance. The original financial record stays the single source of truth; acquisition and maintenance never create a second expense or payment.
- **Branch and currency checks**: source and maintenance links must belong to the route branch and use the same currency as the asset; this prevents cross-tenant or cross-currency attribution without inventing FX rules.
- **Duplicate links**: uniqueness is enforced by `as_source_uniq` on branch + source type + source id with a service pre-check under lock; duplicates return 409 rather than silently overwriting provenance.
- **Cost versus source amount**: cost is an explicit field, not copied from the source, because one invoice/payment can cover several assets. Currency must match, but amount equality is not assumed.
- **Depreciation**: no useful-life or residual default is asserted. The caller supplies assumptions to a calculation-only endpoint whose output is labelled ESTIMATE / BLOCKED with `policy_status=unconfirmed`. No asset column, ledger row, expense or audit entry is written by estimation. Accounting/tax use is BLOCKED until policy and professional rules are confirmed.
- **Disposal**: the accumulated estimate is an explicit caller input (normally copied from the estimate report), capped at cost. The API returns cost, accumulated estimate, proceeds and estimated gain/loss but posts no entries; the `asset.disposed` audit row carries both the before/after snapshot and the financial effect so the decision is traceable.
- **Permissions**: view/manage/dispose are separate branch permissions. Disposal is deliberately not included in the manage permission because it produces a financial-effect statement.
- **Frontend**: `/app/assets` reuses AppShell, permission-aware states and TanStack Query. Amount inputs are integer minor units to avoid major-unit rounding assumptions. Browser runtime verification is NOT RUN in this stage.

## 7.6 Inventory intelligence and dashboard v2
- **One shared computation path**: reorder, ageing, ABC and cash forecast are computed in `DashboardMetricsService`, which calls the existing `FinanceMetricsService` and reads `StockLevel`, `StockMovement`, `SaleItem`, `Sale`, `Expense` and `SupplierBill`. No chart performs its own aggregation and no second sales/stock ledger was introduced.
- **Explicit windows**: all windows and thresholds live in `config/inventory_intelligence.php` and are echoed in the API response so a chart can display the rule it is using. A missing sales history produces a named warning and a null/omitted suggestion rather than a guessed demand rate.
- **Reorder basis**: demand uses non-void `status=posted` sale item quantities divided by the full window length (not days with sales). Available stock subtracts reservations because reserved units cannot be re-ordered against; suggestions are rounded up to whole base units because purchase quantities are discrete.
- **Ageing basis**: the last canonical stock movement date is the activity signal; reversals count as movement history because they are ledger events. Stock with no movement history remains unclassified (`classification=null`) and is surfaced in a warning.
- **ABC basis**: contribution is revenue (`sale_items.total_minor`) over the configured window, not cost or margin, because cost layers are not implemented. Cumulative thresholds are applied inclusively after sorting descending by revenue.
- **Forecast limits**: the cash forecast is a labelled `FORECAST`, not a finance statement. It extrapolates trailing cash averages linearly, deducts known bills inside the horizon once, and defines the cash target as unpaid bills plus incurred expenses with no paid date. Missing cash history, mixed currencies and future as-of dates are data-quality warnings; no seasonality, growth, FX or payment-timing rule is invented.
- **Permissions**: existing `branch.inventory.view` and `branch.finance.view` permissions are sufficient; adding a dashboard permission would duplicate role data. Section-level nulls are returned for sections the caller cannot view, while the route itself denies users with neither permission.
- **Accessibility**: charts are semantic HTML tables with captions, header scopes and text values; visual bars are supplementary, carry `role="img"` labels and are never the only representation. This avoids adding a chart dependency and keeps contrast under application control.

## 7.7 Phase 7 integration acceptance
- **Executable contract**: `PhaseSevenIntegrationAcceptanceTest` is the acceptance evidence for cross-module Phase 7 flows. It adds no production behavior, permissions, routes or schema; a failure must be fixed in the owning module with a bounded, documented change rather than weakened acceptance assertions.
- **Deterministic fixtures**: frozen time and explicit config overrides make reorder, ABC and cash-forecast assertions reproducible. Amounts are integer minor units with exact expected values rather than tolerance-based checks.
- **Existing service contracts win**: reorder returns planning rows with `needs_reorder=false` when history exists but no order is needed; acceptance asserts `needs_reorder_count=0` and zero suggested units instead of requiring an empty list.
- **Tenant setup**: cross-branch transfer tests use a user with memberships in both branches so the test reaches service-level cross-branch reference validation; a single-branch actor is correctly rejected earlier by branch middleware.
- **No duplicate effects**: acceptance treats idempotent replay or conflict as success only when stock, sale, expense, money, discount and qualification side effects remain single-posted.
- **Returns**: the current return workflow is a documented request that affects loyalty but does not restock, refund or net the original sales report. Acceptance asserts this current behavior and records it as a limitation rather than inventing new accounting rules.
- **COGS**: exact COGS verification is BLOCKED until cost layers exist. No cost basis is invented in fixtures; the test asserts the absence of duplicate expenses/stock/discounts instead.

## 8.1 Security threat review and fixes
- **CSRF**: stateful `login` and `logout` are no longer exempt from Laravel CSRF validation. The SPA already fetches `/sanctum/csrf-cookie` before login and the shared Axios client sends `X-XSRF-TOKEN`; no custom token handling was added.
- **Bounded fixes only**: the only production change is removal of the CSRF exemption. Authorization, tenancy, validation, escaping, webhook hashing, audit redaction/immutability and throttling were reviewed and verified with negative tests rather than rewritten.
- **Webhooks**: Paynow remains signature-verified with the existing `PaynowHash` helper and `hash_equals`; no custom cryptography was introduced. Invalid signatures return 400 before any provider HTTP call, payment write or status audit row.
- **Secrets**: `.env.example` uses blank placeholders for integration keys, admin password bootstrap and AWS credentials. No real secret was printed, committed or added to docs.
- **Dependencies**: known Composer advisories are recorded as residual risk/BLOCKED because project rules require approval before dependency changes. No package version was changed in this stage.
- **Verification boundary**: no production or destructive database test was run; browser runtime verification remains NOT RUN and frontend checks were not needed because no frontend file changed.

## 8.2 Performance
- **Evidence first**: measurements were taken with the Laravel test harness and MariaDB query log against a seeded test database, not guessed from production intuition.
- **No new indexes**: existing indexes were adequate once N+1 and payload shape were fixed; adding speculative indexes was rejected.
- **Route splitting**: large pages load as separate chunks; below-fold intelligence charts lazy-load to reduce initial bundle size.
- **No infrastructure changes**: PgBouncer, replicas, CQRS, partitioning and sharding remain rejected without load-test evidence.

## 8.3 SEO verification
- **Rendered HTML as evidence**: verification asserts on server-rendered Blade output and built static HTML via DOMXPath rather than relying on source templates or guessed crawler behavior.
- **JSON-LD workaround**: Laravel 13 treats `@context` as a Blade directive, so literal keys in public views were compiled into PHP fragments. Keys are written as `'@'.'context'` instead of moving structured data into controllers or adding a package; this is the smallest correct fix and is documented as a risk to revisit if generation moves out of Blade.
- **Bounded fixes only**: unique catalogue/category descriptions, a CMS title H1 fallback for text/image-only pages and an ItemList `url` were added; no public page layout, design or copy structure was redesigned.
- **Robots source of truth**: the environment-aware `/robots.txt` route governs crawler policy. The tracked static `public/robots.txt` default was deleted because Apache's `!-f` rewrite let it shadow the route with an allow-all policy in production.
- **Private surfaces**: the React workspace and its iframe mockup stay noindex; public HTML and the sitemap must not link to `/app`, `/platform`, `/api` or unpublished/other-branch data. Negative tests guard these boundaries.
- **Verification boundary**: 160-character descriptions are a hygiene guideline, not a ranking rule. Browser runtime, external W3C validation and production crawler-console checks remain NOT RUN/BLOCKED without a deployed origin and tooling.

## 8.4 Queues, external services and monitoring
- **Bounded retries over infinite loops**: notification jobs already had `tries=3`, `timeout=30` and `backoff=[15,60]`; these were retained and made explicitly assertable. `ShouldBeUnique` with a 300-second delivery-key lock was added so duplicate dispatches collapse without new infrastructure.
- **Dedupe at the callback boundary**: a unique `webhook_events(provider, event_key)` row is written after a signed callback is fully applied, using a hash of reference, provider reference, amount, status and poll URL. Hash-before-poll skips repeat provider calls; the unique constraint absorbs concurrent replays. The existing terminal-status guards remain as a second layer.
- **Configuration-driven provider limits**: Paynow connect/read timeouts and retry count live in `config/paynow.php` with hard clamps, and `max_polls_per_callback=1` prevents a replayed callback from fanning out into extra status requests. No new HTTP client package was introduced.
- **Correlation propagation through Laravel primitives**: a middleware accepts/validates IDs and uses `Log::shareContext`; `Queue::createPayloadUsing` copies them into job payloads, and `Queue::before` restores them in workers. A static one-time registration guard was rejected because Laravel 13's test lifecycle clears payload callbacks between tests; registration happens per app boot instead.
- **Dedicated observability channel**: structured logs go to a daily `observability` channel with recursive denylist redaction and exception-message suppression. Failed jobs use the same shape through `Queue::failing`, keeping the alert hook generic; routing to Slack is an environment choice, not a code change.
- **No circuit breaker yet**: one bounded payment provider, mail jobs and existing route throttling do not justify a persistent breaker store, cache dependency or new package. The decision is explicitly NOT APPLICABLE for this stage and should be revisited with evidence of repeated provider outages or additional providers.
- **Verification boundary**: all provider and mail failures are simulated with fakes/mocks. Actual pager delivery is BLOCKED without a configured webhook; browser runtime and live external calls are NOT RUN.

## 8.5 Backup, restore and retention drill
- **Proven, not assumed**: a backup is accepted only after a restore into a separate non-production database and a read-only verification pass. The drill's manifest, counts and workflow checks are the evidence.
- **Host tools only**: `mysqldump`, `mysql`, `tar`, `gzip`, `sha256sum` and optional `gpg` are sufficient for a bounded dump plus private-storage archive. No backup package or application dependency was added.
- **Manifest as contract**: `torqueerp-backup-manifest/v1` carries tool versions, counts, sizes and SHA-256 digests. Restore refuses a manifest that fails schema validation or an artifact whose hash/size changed. No credential is ever recorded in it.
- **Safety by construction**: database names must match `torqueerp_*_test`; shared test databases cannot be restore targets; the source database from the manifest can never be the target. Restores create a fresh target rather than mutating an existing one unless explicitly replaced.
- **Encryption and off-site are deployment responsibilities**: the scripts can GPG-encrypt artifacts, but production encryption at rest/in transit, an off-site target and lifecycle pruning need infrastructure configuration. They are documented as proposals/BLOCKED, not claimed as active controls.
- **Honest RPO**: a nightly full dump yields up to 24 hours of potential loss. The 15-minute target requires binary logging/PITR and is BLOCKED until implemented; RTO is an unmeasured 2-hour proposal.
- **Evidence boundary**: the fixture is deterministic and synthetic, covers 14 key tables and seven workflow invariants, and was restored in seconds. It does not prove production-scale timing or every domain table.
- **Secret rotation is documented, not executed**: no real key or password was rotated. `APP_KEY` rotation is explicitly gated on a re-encryption plan because encrypted attributes and sessions would otherwise become unreadable.

## 8.6 Deployment and operations
- **Observe before documenting**: the runbook is built from commands run on this host. `php8.4 artisan --version` from the repo root fails because Artisan is in `backend/`; the correct command and the exact failure are both recorded.
- **No VPS/cPanel assumption**: the observed topology is local PHP 8.4 CLI dev server + Vite + MariaDB, with nginx serving an unrelated default page and XAMPP Apache installed but not running. Production host, domain, document root, web user, TLS paths and service names remain explicit placeholders.
- **Dev vs production boundary**: `artisan serve` and Vite are development-only. Production uses a real vhost pointed at `backend/public`, a built static `frontend/dist` and TLS; no guess at a service manager was made.
- **Database and queue choices match configuration**: MariaDB via `pdo_mysql`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database` and `SESSION_DRIVER=database` are confirmed locally. Queue workers and scheduler are required operations but no supervisor/cron unit was installed in this stage.
- **Health scope is explicit**: `GET /api/health` covers database, queue size and failed-job count. Scheduler freshness, storage writability, TLS and external uptime are separate monitors, not claimed by the endpoint.
- **Secrets stay in the environment**: docs and `.env.example` contain names and blank placeholders only. `APP_KEY` rotation remains gated by the existing re-encryption plan.
- **Recovery over cleverness**: backups must be verified before migration, rollbacks are bounded and gated, and no destructive production operation was performed to prove the runbook.

## 8.7 Translations, accessibility and mobile
- **No invented languages**: en/sn are a partial technical foundation, not an approved product scope. Translation completeness is BLOCKED until the owner names the languages and supplies copy; mounting a switcher or claiming six languages without translated screens would be misleading.
- **Fix the defects that block operation first**: unlabelled controls, unannounced errors, missing header scopes, heading skips and invisible focus were fixed in existing components instead of adding a new a11y dependency or redesigning the app.
- **Token-based focus and contrast**: the global focus fallback and corrected success/dashboard classes reuse existing `--ring`, `--foreground` and `Button` variants rather than new one-off colors; fixed white-on-dark notice boxes keep explicit dark text.
- **Wrap labels for dynamic forms**: POS lines and repeated payroll/HR forms wrap their single control in `<Label>` instead of generating colliding `id` values.
- **44 px for POS**: `min-h-11` is applied only to POS cart controls, where touch use is expected; other dense admin grids remain unchanged pending device testing.
- **Evidence boundary**: static source checks plus typecheck/lint/build prove code correctness, not assistive-technology behaviour. Browser, screen-reader and device verification are explicitly NOT RUN.

## 8.8 Release-candidate acceptance
- **Honest overall status**: PARTIAL / BLOCKED, not production-ready. Every locally executable acceptance command passed, but a PASS is never promoted across an owner, host, secret, legal or runtime boundary. No FAIL was hidden; BLOCKED and NOT RUN criteria are listed with owners in `docs/RELEASE_CHECKLIST.md`.
- **Fresh non-production evidence**: the clean setup used a new `torqueerp_stage88_acceptance_test` database and `migrate:fresh`, not a shared test database or a production-like name. `migrate:fresh` is safe only because the name is new and disposable; it is not a production migration strategy.
- **Re-run, do not reinvent**: focused suites, Stage 8.2 bounded performance tests and the Stage 8.5 read-only restore verification were executed against the current tree. No new feature, new test, new dependency or new instrumentation was added to make acceptance look better.
- **Two kinds of performance evidence**: Stage 8.2 recorded test-harness before/after measurements remain the authoritative optimization evidence; Stage 8.8 re-ran their bounded assertions and captured current build sizes. Neither is presented as production latency.
- **Restore proof stays separate from backup creation**: the existing manifest, checksums, 14 counts and 7 invariants passed in verify-only mode. This does not close off-site storage, encryption, PITR or production-scale RTO; those remain infrastructure-owned gates.
- **Release checklist as the decision record**: the new `docs/RELEASE_CHECKLIST.md` names each gate, owner and evidence needed to close it, plus a rollback plan anchored in `docs/DEPLOYMENT.md` section 5. Deployment remains blocked until G5-G12 are closed.
- **No production claim**: no TLS, live Paynow, SMTP, pager, browser, screen-reader, device or production-deploy action was attempted, and no secret was printed or committed.
