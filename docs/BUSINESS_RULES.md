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

## Loyalty and win-back (Stage 7.4)
- Qualification is derived only from committed (`status=posted`, not voided) branch sales for a branch customer, minus documented sale returns for those same sales. Voided sales and returns on excluded sales never contribute.
- Each sale event is idempotent by branch `event_key` and by a deterministic business dedupe hash of branch, customer, source reference, amount and occurred-at time. Replaying the same event is a no-op; reusing an event key with different facts is rejected with 409.
- Qualification snapshots are explicit: recalculation writes gross sales, returned amount, net qualifying amount, sale count, last purchase time, qualifies and inactivity flags. Recalculation is idempotent and audited.
- The inactivity window, minimum qualifying spend, minimum qualifying sales, loyalty discount percent and combined-discount approval threshold are stored per branch in `loyalty_configs` and exposed through the API. No silent hard-coded rule is used; defaults live in `config/loyalty.php` and are materialised into the branch row on first access.
- A customer qualifies when the net qualifying amount and qualifying sale count both meet the branch configuration. Inactivity is measured from the latest qualifying sale event against the branch inactivity window.
- Stacking order: base price → loyalty discount → manual discount → combined discount capped at 100% → floor price clamp → tax → approval. The floor price is never breached; a combined discount that exceeds the configured threshold or would breach the floor requires an authorized approver and a reason, which is audited through the existing price override audit.
- Win-back outreach eligibility requires a qualifying, inactive customer with explicit marketing consent, a configured channel and an active channel for that customer. Consent and channel state are settable and auditable via `PATCH /api/branches/{branch}/customers/{customer}`; absence excludes the customer.
- No automated message is sent in this stage. The win-back export is a redacted stub (`delivery_status=NOT_RUN`, `channel_integration=not_configured`) and actual delivery remains BLOCKED until consent proof and a channel integration are configured.

## Payments
- Payment confirmation must be server-verified where a gateway exists.
- Gateway callback, browser redirect and manual confirmation are distinct. Never trust a redirect as payment proof.
- Callbacks and retried requests must be idempotent. Corrections use reversals; failed or duplicate callbacks are safe.

## Staff lifecycle
- Do not delete employees with payroll, leave, disciplinary or audit history. Deactivate/archive and revoke access.
- An employee record is not a login account. Link them explicitly; creating an employee grants no system access.
- Employee lifecycle: hired → active/suspended/terminated, active ↔ suspended, terminated → archived. Archived records are read-only.
- Terminating or archiving an employee revokes that branch's access (membership plus tokens/sessions when no other branch access remains). The login account is not deleted.
- Sensitive HR fields (national ID, date of birth, address, emergency contacts, notes) require the separate sensitive-data permission.

## Leave, attendance and discipline
- Leave days are inclusive and exclude weekends unless the leave type explicitly counts weekends. Public holidays are not configured in Stage 6.2; approval must account for them manually.
- A leave request must not overlap an existing submitted or approved request for the same employee.
- Annual leave limits apply to approved days charged to the request's start-date year. Limits are rechecked when a submitted request is approved.
- Leave approval requires `branch.hr.leave.approve`; creating and cancelling requests requires `branch.hr.manage`. A rejection must include a decision note.
- Attendance is one record per employee per work date. Re-posting updates it, future dates are rejected, and overtime is recorded as minutes rather than money.
- Disciplinary records are sensitive: viewing requires `branch.hr.sensitive.view`; changing them also requires `branch.hr.manage`.
- Disciplinary lifecycle: open → resolved/closed, resolved → appealed/closed, appealed → resolved/closed. Closed records are read-only.
- HR attachments are private. PDF, JPG/JPEG, PNG and WebP files up to 5 MB are allowed; disciplinary attachments require sensitive HR permission.


## Payroll
- Salary grades define monthly or hourly pay, overtime handling and currency amounts in minor units.
- Employee assignment windows must not overlap for the same employee; recurring components and statutory rules are effective-dated.
- Payroll periods must be unique per branch and cannot overlap. Approved periods are locked and cannot be regenerated.
- Draft runs are reproducible and replace previous drafts for the same open period. Approval snapshots payslips, lines, totals, repayments and applied adjustments.
- Monthly pay is prorated by payable weekdays / period weekdays. Hourly pay is based on attendance minutes; overtime uses configured rate or multiplier.
- Statutory rates and fixed amounts are configurable per branch and are not asserted as legally correct.
- Loan installments are deducted only when a run is approved and cannot be applied twice. Future open periods use the reduced balance.
- Approved-run corrections require pending adjustments or reversals in later open periods; approved payslips are never edited in place.
- Payroll view, manage and approve permissions are distinct and all payroll data is branch-scoped.



## Tasks and notifications
- A task can only be completed or cancelled while open or in progress.
- Every task state change must create a distinct event; completion must also create an immutable completion record.
- Notifications respect recipient preferences and record delivery state, attempts and errors without changing task state.
- Failed notification delivery must be visible to the user and must not repeat financial actions.

## Catalogue fitment and OEM (Stage 7.1)
- Core product records remain the source of truth for SKU/barcode/name/price/stock. Fitment and OEM data are additive, branch-scoped references.
- Exact SKU/barcode matches must be reliable and prioritized.
- Fitment/OEM imports must declare `source`, `source_ref`, and format; imported rows are tagged as declared/verified metadata per row (`verified='declared'` by default). No inferred compatibility.
- Search results must show match type and source. Typo tolerance is limited (tokens length >= 5, Levenshtein <= 1) and must not produce false positives in tests.

## Payroll payment and reconciliation
- An approved run creates exactly one accrued payroll expense for gross plus employer statutory cost.
- Payroll payments are applied only to approved runs, cannot exceed net pay and post one canonical cash outflow per allocation.
- Retrying a payment with the same idempotency key must not duplicate money movements or expenses.
- Reconciliation must pass before a run is treated as settled; payslip export requires explicit export permission.

## Service catalogue and job cards (Stage 7.2)
- Job cards are branch-scoped and must reference a branch customer; technician assignment requires an active branch membership.
- Labour and parts can only be added while a job is in `draft`. The estimate snapshots the sum of labour and part lines.
- Customer approval (reference + channel) is required before work can start. Completion requires a recorded diagnosis.
- Every part consumption posts exactly one canonical `job_consumption` stock movement via `StockLedger`; retries with the same `Idempotency-Key` and payload must not post another movement.
- Cancelling or reopening a job must not reverse stock, change stock balances, create/void invoices, or detach an existing invoice link.
- Invoices are linked manually from existing branch sales; jobs never generate invoices automatically.
- Warranty references are unsupported/NOT RUN in this stage.

## Stock transfers and stocktakes (Stage 7.3)
- Transfers are branch-scoped records that move stock from one explicit branch product to another explicit branch product.
- Both source and destination branches must be active, and the actor must have `branch.transfers.manage` in both branches for transfer lifecycle mutations.
- A transfer posts in base units only. Source and destination products must be selected explicitly; no SKU match, unit match or conversion factor is inferred.
- Transfer states are `draft`, `dispatched`, `in_transit`, `received` and `cancelled`. In-transit is informational and posts no movement.
- Dispatch posts one canonical source `transfer_out` movement. Receipt posts one canonical destination `transfer_in` movement. Duplicate receipt is rejected with 409.
- Cancellation is allowed before receipt and posts one equal-and-opposite source movement. It never creates a destination reversal; a received transfer is terminal.
- Stocktakes are branch-scoped counts with `draft`, `reviewed` and `approved` states.
- Expected quantity is snapshotted from the locked on-hand balance; each nonzero variance requires a reason and posts one canonical `stocktake_adjustment` movement only at approval.
- Stocktake review and approval are separate actions; approval requires `branch.stocktakes.approve` and duplicate approval is rejected with 409.
- Reserved/hold stock is not separately counted in this stage; stocktakes compare against on-hand balance only.

## Assets and maintenance (Stage 7.5)
- Assets are branch-scoped and store acquisition date, `cost_minor`, currency, location, custodian, status and optional source type/id.
- An asset may link to one existing same-branch `expense`, `supplier_bill` or `money_movement`; the same link cannot be reused by another asset in that branch (409).
- Acquisition and maintenance linking never creates an `Expense`, `SupplierBill`, `PaymentAccount` or `MoneyMovement`; the linked record remains the financial source of truth.
- Linked source currency must match the asset currency. Cost is explicit and may differ from the source amount because a source can cover multiple assets.
- Maintenance records store performed date, cost, provider, description and optional same-branch expense/payment links; they do not post payments.
- Depreciation is ESTIMATE only: useful life and residual are caller-supplied, every response is labelled ESTIMATE / BLOCKED, and no posting, asset mutation or audit row results. Accounting/tax use is BLOCKED until policy and professional rules are confirmed.
- Disposal is terminal, requires a date, proceeds and a caller-supplied accumulated estimate (capped at cost), writes an `asset.disposed` audit entry, and returns cost, accumulated estimate, proceeds and estimated gain/loss. No accounting entry or money movement is posted automatically.
- Disposal requires `branch.assets.dispose`; acquisition/maintenance require `branch.assets.manage`; reads require `branch.assets.view`.

## Inventory intelligence and dashboard v2 (Stage 7.6)
- All dashboard v2 charts are computed once per request by `DashboardMetricsService` from the existing finance metrics, stock ledger, stock levels, sales and expenses; charts must not recompute or reinterpret the numbers.
- Reorder demand is posted non-void sale quantity over the configured window divided by the whole window length. Lead time and days of cover are explicit config. Reserved stock is unavailable. Without window sales, a suggestion is null/omitted and `insufficient_sales_history` is returned.
- Ageing uses the last canonical stock movement date. Slow-moving/dead-stock thresholds are configurable. Stock with no movement history is reported with a null classification and a warning; zero stock is `no_stock`, not dead stock.
- ABC uses posted sale line revenue over the configured window and inclusive cumulative contribution thresholds. Cost or margin must not be inferred because cost layers are not implemented.
- Cash forecast output must be labelled `FORECAST`, carry its assumptions and data-quality warnings, and must never be presented as an authoritative finance statement. The cash target is an illustrative unpaid-commitment figure, not an approved budget.
- Missing data must produce named warnings and null/zero values instead of invented demand, movement dates or contribution. Mixed currencies are warned about and never silently converted.
- `/api/branches/{branch}/dashboard/v2` is branch-scoped. Inventory sections require `branch.inventory.view`; finance metrics and forecast require `branch.finance.view`; users with neither are denied. Section nulls reflect permission, not missing data.
- Charts must expose the same numbers as accessible text (captions, header scopes, text alternatives) and must render only from the shared endpoint.

## Phase 7 integration acceptance (Stage 7.7)
- Integrated Phase 7 flows must be proven end to end with known fixtures: job labour/parts/manual invoice, transfer dispatch/receipt, stocktake approval, loyalty stacking/qualification, sale return effects, reports and dashboard v2.
- Retries of job part, checkout, loyalty event and sale return must return the original record or a 409 conflict, never duplicate stock, sale, discount or qualification side effects.
- Duplicate transfer receipt and duplicate stocktake approval must be rejected with 409 and must not post a second stock movement.
- Discount quotes are non-posting. Loyalty plus manual discounts may be combined only through the existing pricing rules, with floor clamping and permission/reason approval; a rejected quote must not create a sale, expense, money movement or audit-free discount.
- A documented sale return reduces loyalty net qualifying amount but does not restock inventory, reverse the original sale, or retroactively net the sales report in the current workflow.
- Cross-branch reads, actions and references are rejected; a transfer actor must hold membership in both branches before service-level product mapping is evaluated.
- Exact COGS cannot be asserted until cost layers exist; no cost basis may be invented for acceptance. The no-double-posting assertions cover stock, expenses, money movements, sales and discounts only.
- External data and operational policies remain blocked where no source exists: catalogue/OEM imports are manual, win-back has no automated channel, depreciation and cash forecasts are estimates, job invoices are linked manually, base-unit equivalence is unconfirmed, and statutory/tax rates require confirmation.

## Security threat review and fixes (Stage 8.1)
- Stateful login and logout requests must satisfy Laravel CSRF protection. The SPA obtains a Sanctum CSRF cookie before login and sends the XSRF header on mutating requests; public webhook endpoints are exempt from CSRF but must verify provider signatures.
- Inactive users and revoked tokens must be rejected on the next authenticated API request. Deactivating or removing a user's last active membership must revoke tokens and database sessions.
- A branch user must never access another branch's data, and a missing branch/platform permission must deny the action even when the route and model are otherwise valid.
- Request validators are allow-lists. Unknown or protected fields such as `branch_id`, `id` or internal price columns are ignored even when the model has them in its fillable list.
- Public HTML must escape user-supplied content. JSON-LD must encode HTML-sensitive characters; mail and Blade templates must use escaping for customer/product content.
- Exports require the explicit export/view permission and must record an audit row with actor, scope and expiry. Download links must remain permission-gated and expire.
- Paynow callbacks must pass signature verification before any HTTP poll, payment state change or audit write. Invalid signatures return 400.
- Sensitive audit logs are append-only, redact secrets/PII keys and must never be edited or deleted through application code.
- No production or destructive database test may be used as verification. Real secrets must stay out of code, docs and `.env.example`; no custom cryptography is introduced.

## Demo data
- Demo data must be fictional, deterministic, relationally coherent, repeatable, branch-safe and blocked from accidental production execution.

## Financial reconciliation
- Receiving, purchases, expenses and COGS remain separate metrics.
- Voided sales are excluded from revenue and receivables.
- Credit sales do not create money movements until collected.

## Performance and payload rules
- List endpoints must bound page size and expose pagination metadata.
- List payloads should omit heavy child relations unless the single-record endpoint is used.
- Cache keys must be tenant-scoped and invalidation-defined; no cache was added in Stage 8.2.

## SEO verification (Stage 8.3)
- Every indexable public page must expose one unique title and one unique, concise description (bounded at 160 characters as hygiene, not a ranking guarantee), one self-referencing canonical, `index, follow` robots meta, exactly one H1 as the first heading, `lang`, favicon and a valid 1200x630 OG image declaration.
- Structured data must be valid JSON with the `https://schema.org` context, a truthful `@type` and URLs/prices matching the rendered page. Blade-authored JSON-LD must avoid literal `@context` because Laravel 13 compiles it as a directive.
- Search result, reservation request and token-based reservation status pages must remain `noindex`; the private React workspace and its iframe mockup must always be `noindex, nofollow`. Non-production must be fully disallowed via robots.
- The sitemap may contain only public, indexable, canonical URLs: home, published CMS pages, the configured public branch shop, published products and active categories with published products. Private, draft, search, reservation and other-branch URLs must never appear.
- All rendered images need descriptive alt text; CMS image sections and public product images require alt text at save time. Slugs must be lowercase hyphenated, and public HTML must not link to private workspace or API paths.
- No production or crawler-console claim may be made from test evidence alone; browser runtime and external HTML validation are NOT RUN until tooling/deployment is available.

## Queues, external services and monitoring (Stage 8.4)
- Notification jobs are bounded: at most three attempts, 30-second timeout, 15/60-second backoff and a 300-second unique lock keyed by delivery ID. Duplicate delivery dispatches must not create duplicate queue rows or notifications.
- A failed notification delivery is marked `failed` with the exception class only; recipient details, SMTP text and secrets are never written to logs or the delivery error field.
- Every signed Paynow callback is identified by a hash of reference, provider reference, amount, status and poll URL and recorded once in `webhook_events`. A repeated callback must be ignored before any provider poll and must never re-confirm, cancel, refund or downgrade a payment.
- Callbacks with invalid signatures, mismatched amounts or untrusted poll URLs are rejected before provider calls; terminal payment states are never downgraded by late or out-of-order events.
- Provider HTTP calls are capped: initiate is a single attempt; status polling has clamped connect/read timeouts and at most three attempts per callback; each webhook triggers at most one status poll.
- `GET /api/health` is an operational signal only: 200 when database and queue checks pass, 503 when degraded, with failed-job count and correlation IDs. It exposes no exception detail and is not a security or authorization boundary.
- Logs must carry correlation/request IDs and redact configured secrets/PII recursively, including nested values and exception messages. No real credential, webhook URL, card, bank or personal contact detail may be printed.
- Failed jobs emit a redacted `queue.job.failed` hook. Actual paging depends on deployment configuring `FAILED_JOB_LOG_CHANNEL=slack` and a webhook URL; until then paging is BLOCKED and logging is the only observable outcome.
- A circuit breaker is NOT APPLICABLE while there is a single bounded external provider; revisit if provider count, outage evidence or shared breaker infrastructure changes.

## Backup, restore and retention (Stage 8.5)
- A backup is not accepted until a restore into a separate non-production database has been demonstrated and record counts plus key workflow invariants are verified.
- Production data and production credentials must never be used in a backup drill. Only `torqueerp_*_test` databases are eligible, and shared test databases cannot be restore targets.
- The source database must never be the restore target. Restores create or deliberately replace only an explicitly named non-production target; production-like names are refused.
- Every artifact set must have a machine-readable manifest with UTC creation time, source, tool versions, key-table counts, byte sizes and SHA-256 digests. A checksum, size or manifest-schema mismatch blocks the restore.
- Backups must include the MySQL dump and Laravel private storage (`storage/app/private`) because CMS originals and HR attachments live there.
- Secrets must stay in the environment/secret manager. Manifests, logs, docs and the repository must never contain database, off-site, GPG, Paynow or notification credentials.
- Encryption at rest and in transit, off-site storage, retention/pruning and job alerting are deployment responsibilities of the infrastructure owner; they are not active controls until configured.
- Retention must keep the newest successful set and the newest set with a PASS restore verification. Proposed local retention is 14 daily sets, off-site 90 daily plus 12 monthly sets.
- The business owner approves RPO/RTO. Nightly full dumps provide up to 24 hours of RPO; a 15-minute RPO requires binary logging/PITR and is BLOCKED until implemented. The 2-hour RTO is a proposal, not a measured production SLA.
- Restore responsibilities are split: infrastructure owner (schedule, storage, keys, retention), on-call engineer (executes restore and records evidence), application owner (selects backup and signs off verification), security owner (rotation and access review).
- Secret rotation must be rehearsed in non-production and followed by config cache clear, worker restart, health check and a new backup/restore drill when backup credentials or encryption keys change. `APP_KEY` rotation requires a re-encryption plan before production use.

## Deployment and operations (Stage 8.6)
- Deployment documentation must describe the host that was actually inspected; unverified VPS/cPanel or production-host details are placeholders, not claims.
- Supported local tooling observed here is PHP 8.4.27 with `pdo_mysql`/`pdo_sqlite`, Node 22.17.1, npm 10.9.2, Composer 2.7.1, Laravel 13.39.0, nginx 1.24.0, XAMPP Apache 2.4.58 (installed, not running) and MariaDB 8.4.11.
- Development may use `artisan serve` and Vite; production must use a real web server rooted at `backend/public`, built `frontend/dist`, HTTPS with a validated certificate, `APP_ENV=production`, `APP_DEBUG=false` and secure session cookies.
- Production secrets must be injected from the secret store into the ignored backend `.env`; no production credential, key, webhook URL or private value may appear in docs, manifests, logs or repository files.
- Migrations require a verified backup first; `migrate:status` and `migrate --force --no-interaction` are the forward path. `migrate:rollback --step=1 --force --no-interaction` is allowed only for confirmed reversible migrations after draining workers and owner approval.
- Storage must be writable by the real web user without `777`; `storage/app/private` is backup-critical and must never be publicly served.
- Queue workers use the database queue with bounded retries/timeouts; the scheduler must run `reservations:expire` every minute in production. Absence of either is an operational blocker for notifications and reservation expiry.
- Health checks must be treated as application signals only: HTTP 200 with `status=ok` for database/queue/failed-job checks, 503 when degraded. TLS, worker/scheduler liveness, storage and external uptime require separate checks.
- Logs stay in `backend/storage/logs` (`laravel.log` and daily `observability-*.log`) with redaction; production log rotation/shipping and external uptime/pager checks are deployment responsibilities and are BLOCKED until configured.
- Rollback must prefer switching to a previous immutable release and restoring verified backups over ad-hoc data edits; recovery is not complete until health, queue, scheduler, logs and one business smoke test pass.

## Translations, accessibility and mobile (Stage 8.7)
- A language may be claimed complete only when the owner has named it, approved its copy and every user-facing screen and error in that language has been translated; otherwise report it as a partial foundation.
- Because no owner-selected languages exist, translation completeness for Stage 8.7 is BLOCKED. The two existing locales (`en`, `sn`, 11 keys each) are not a product guarantee and no six-language claim is permitted.
- Every form control must have a programmatic accessible name; errors must be announced and associated with the relevant field(s).
- Interactive controls must be keyboard operable with a visible focus indicator; tab-like controls must expose their selected state.
- Data tables must expose column headers programmatically and remain horizontally scrollable on narrow screens.
- POS cart controls must present at least 44 px touch targets. Other touch targets need real-device verification before sign-off.
- Success/error messages must use the appropriate live role so assistive technology reports outcome without a page reload.
- Static checks are evidence for source defects only; runtime browser, screen-reader and device checks remain mandatory before accessibility acceptance.

## Release-candidate acceptance (Stage 8.8)
- A release may be labelled PASS or production-ready only when every critical gate is PASS with named-owner evidence. The current overall status is PARTIAL / BLOCKED; local test success does not close host, TLS, pager, backup, language, legal or browser-runtime gates.
- Acceptance must run on a new disposable non-production database; `migrate:fresh` must never be pointed at production, a shared test template or any database containing real data.
- Every criterion must be reported as PASS / FAIL / BLOCKED / NOT RUN with the exact command and outcome. Unverified criteria must not be stated as passing, and no dependency, feature or assertion may be added solely to improve the acceptance report.
- The Stage 8.5 restore drill and Stage 8.2 performance figures remain valid local evidence only: a checksum/count/invariant PASS does not imply off-site, encrypted or PITR recovery, and test-harness timings do not imply production latency.
- Frontend typecheck, lint, build and static accessibility scans are code-quality evidence; browser, screen reader, keyboard and touch-device checks are mandatory before accessibility or release sign-off.
- Dependency advisories, owner-approved languages, statutory/tax/payroll assumptions, accounting policy and cost-basis limitations are release blockers until formally resolved by their owners; no invented value may be used to close them.
- The release owner must follow `docs/RELEASE_CHECKLIST.md`: close G1-G12, prefer forward recovery over schema rollback, restore only verified backups into separate non-production targets, and re-run health, queue, scheduler, log and one business smoke test before declaring a deployment recovered.
