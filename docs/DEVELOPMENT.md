# Development setup

## Prerequisites
- PHP 8.4+, Composer 2.x
- Node.js 22+, npm 10+
- MariaDB 10.4+ or MySQL 8.x
- XAMPP/LAMPP environment (as used here)

## Backend (Laravel)
```bash
cd backend
cp .env.example .env  # fill DB credentials locally
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

## Frontend (React + TypeScript + Vite)
```bash
cd frontend
npm install
npm run dev
```

## Health check
- Backend: `GET /api/health`
- Frontend: Vite dev server

## Public site (Stage 5.1)
- Laravel serves the indexable public homepage as server-rendered HTML at `/`.
- Set `APP_URL` to the canonical public origin and `FRONTEND_URL` to the team workspace origin.
- `/sitemap.xml` currently lists the homepage; published catalogue/CMS URLs are added in their stages.
- `/robots.txt` blocks crawling outside production and disallows private app/API paths in production. This is crawl guidance, not access control.
- The React/Vite workspace shell carries `noindex, nofollow` because it contains private application routes.
- Platform admins manage pages at `/platform/content`; public page drafts remain private until published.
- CMS originals are stored on Laravel's private `local` disk and must be included in backups. WebP/AVIF variants are generated only when the server's GD build supports them.
- Set `PUBLIC_CATALOGUE_BRANCH_SLUG` to the active branch that owns the public catalogue; the seeded TMS Rusape branch is the default. Publish products from `/app/products` with a public slug and customer-facing description.
- Public pages show the configured selling price and tell customers to confirm current availability. They do not show internal product notes, cost, supplier details or live stock counts.

## Seed administrator
The seeder creates a local administrator and branch membership. Set these before seeding a non-local environment:
```bash
SEED_ADMIN_EMAIL=you@example.com
SEED_ADMIN_PASSWORD=<strong-password>
php artisan db:seed
```
In production `SEED_ADMIN_PASSWORD` is required. Local development defaults to `admin@tms.com` / `admin123`; never reuse these credentials outside local development. The login page no longer pre-fills credentials.

## Access control (Stage 1.1)
- Public registration is disabled. Branch administrators add/attach users from `/app/users`.
- Branch roles: `admin`, `manager`, `storekeeper`, `cashier`, `accountant`, `viewer`.
- Permissions are recomputed from the database on each request. Removing a user's last branch membership revokes their API tokens and database sessions.
- Platform permissions are separate; `is_platform_admin` does not grant branch access.

## Catalogue (Stage 1.2)
- Open `/app/products` for product search, filters, pagination, product/category/unit management, alternate units and conversion.
- Catalogue permissions reuse `branch.inventory.view` (read/search/convert) and `branch.inventory.manage` (create/update/archive).
- Products, categories and units are branch-owned. SKU and barcode uniqueness are scoped per branch.
- Product prices are entered as major-unit strings and stored as integer minor units; the API returns both.
- Alternate units require an explicit conversion factor through the product's base unit.
- Stock ledger (Stage 1.3):
  - `GET /api/branches/{branch}/stock/levels` lists branch stock balances.
  - `GET /api/branches/{branch}/stock/movements` lists durable stock movements.
  - `POST /api/branches/{branch}/products/{product}/stock/movements` posts `opening` or `correction` movements and requires an `Idempotency-Key` header.
  - `POST /api/branches/{branch}/stock/movements/{movement}/reverse` reverses one movement and requires an `Idempotency-Key` header.
  - Posting is branch-scoped, transaction-safe, row-locked and rejects negative balances.
  - Sale posting is available through `StockLedger::postSale()` for Stage 1.6; it is not exposed as a public API in this stage.

## Customers (Stage 1.4)
- `GET /api/branches/{branch}/customers` lists branch customers with search and status filters.
- `POST /api/branches/{branch}/customers` creates a customer and returns duplicate warnings/candidates.
- `GET/PATCH/DELETE /api/branches/{branch}/customers/{customer}` read, update or archive a customer.
- Customers are branch-owned; similar names, phone numbers or emails never merge automatically.
- Permissions: `branch.customers.view` and `branch.customers.manage`.

## POS pricing (Stage 1.5)
- Domain service: `App\Support\PriceCalculator`
- Price order: base price, discount, floor price, tax, approval.
- Discount threshold is configured by `POS_MAX_DISCOUNT_PERCENT_WITHOUT_APPROVAL` (default 10).
- Approved overrides are audited with actor and reason.

## Checkout (Stage 1.6)
- `POST /api/branches/{branch}/checkout` requires `Idempotency-Key`.
- `GET /api/branches/{branch}/sales/{sale}` returns sale, items, payments and receipt.
- Frontend receipt view: `/app/sales/{saleId}/receipt`.

## Sales history (Stage 1.7)
- `GET /api/branches/{branch}/sales` lists sales.
- `POST /api/branches/{branch}/sales/{sale}/void` requires `branch.sales.void`.
- `POST /api/branches/{branch}/sales/{sale}/returns` creates a return request.
- Frontend: `/app/sales`.

## Payment accounts and cash movements (Stage 2.2)
- `GET/POST/PATCH /api/branches/{branch}/payment-accounts`
- `POST /api/branches/{branch}/payment-accounts/{account}/movements`
- `POST /api/branches/{branch}/transfers`
- `GET /api/branches/{branch}/money-movements`
- `POST /api/branches/{branch}/money-movements/{movement}/reverse`

## Expenses and bills to pay (Stage 2.3)
- `GET/POST /api/branches/{branch}/expenses`
- `POST /api/branches/{branch}/expenses/{expense}/pay`
- `GET/POST /api/branches/{branch}/supplier-bills`
- `POST /api/branches/{branch}/supplier-bills/{bill}/pay`
- `GET /api/branches/{branch}/bills-to-pay/ageing`

## Receivables and collections (Stage 2.4)
- `POST /api/branches/{branch}/sales/{sale}/payments` records a collection.
- `GET /api/branches/{branch}/receivables`
- `GET /api/branches/{branch}/receivables/ageing`

## Finance metrics (Stage 2.5)
- `GET /api/branches/{branch}/finance/metrics`
- `POST /api/branches/{branch}/finance/fx/convert`

## Documents and templates (Stage 2.6)
- `GET/POST /api/branches/{branch}/document-templates`
- `POST /api/branches/{branch}/document-templates/{template}/preview`
- `GET /api/verify/{documentType}/{documentId}`

## Branch lifecycle (Stage 3.1)
- `GET/POST /api/platform/branches`
- `PATCH /api/platform/branches/{branch}/approve`
- `PATCH /api/platform/branches/{branch}/suspend`
- `PATCH /api/platform/branches/{branch}/activate`
- `PATCH /api/platform/branches/{branch}/archive`

## Membership and context (Stage 3.2)
- Branch context is resolved server-side from membership.
- `X-Branch-Id` may select a branch but never grants access.

## Settings (Stage 3.3)
- `GET/PATCH /api/branches/{branch}/settings`
- `GET/PATCH /api/platforms/{platform}/settings`

## Cross-branch reports (Stage 3.4)
- `GET /api/platforms/{platform}/reports/branches`
- `POST /api/platforms/{platform}/reports/export`
- `GET /api/platform/report-exports/{export}/download`

## Audit (Stage 3.5)
- Audit logs are immutable and redact sensitive values.
- Retention defaults to 365 days via `AUDIT_RETENTION_DAYS`.

## Public storefront and reservations (Stage 5)
- Laravel Blade serves indexable public pages; the React/Vite app remains the private ERP workspace.
- Configure `PUBLIC_CATALOGUE_BRANCH_SLUG` to the active branch shown to customers.
- Branch users publish a product only after entering its customer description and public slug. Public pages omit internal SKU, cost, notes and stock counts.
- `POST /reservations` creates a short stock hold; customers manage it through the private link shown after submission. Holds expire every minute through Laravel's scheduler (`php artisan schedule:work` or the production scheduler).
- `GET /api/branches/{branch}/reservations` lists reservations for staff with `branch.reservations.view`.
- CMS uploads and originals are stored on Laravel's private local disk; include `storage/app/private` in protected backups.
- Paynow confirmation requires an authenticated provider result and server-side status poll; an unpaid hold is never reported as confirmed or collected revenue.
- Paynow configuration: set `PAYNOW_ENABLED=true`, `PAYNOW_INTEGRATION_ID`, `PAYNOW_INTEGRATION_KEY`, and `PAYNOW_CURRENCY` in the backend environment. Keep the integration key secret and clear cached config after changing it.
- Paynow callbacks require `APP_URL` to be the publicly reachable HTTPS storefront origin. `127.0.0.1` and localhost previews can render the site but cannot receive Paynow callbacks; use a public HTTPS development tunnel or deployed host for sandbox payment verification.
- Paynow test coverage uses mocked HTTP responses and official hash vectors. A real transaction requires the merchant integration credentials and public callback URL.
- Reservation emails are queued through the database queue. In a local or production environment, run a worker alongside the scheduler: `php artisan queue:work --tries=3 --timeout=30`. Delivery states are visible to authorized branch staff through the reservation API; a mail failure does not alter the reservation or payment.
- The project launcher serves TorqueERP at `http://127.0.0.1:8000`. `tms.lvh.me` currently resolves to a different local virtual host, so configure Apache deliberately before using that host for TorqueERP.

## Employees and lifecycle (Stage 6.1)
- `GET/POST /api/branches/{branch}/employees`
- `GET /api/branches/{branch}/employees/linkable-users`
- `GET/PATCH /api/branches/{branch}/employees/{employee}`
- `POST /api/branches/{branch}/employees/{employee}/transition`
- `DELETE /api/branches/{branch}/employees/{employee}` archives a terminated employee
- States: `hired`, `active`, `suspended`, `terminated`, `archived`. Invalid transitions return 422.
- Sensitive fields require `branch.hr.sensitive.view`; without it the API returns null values and `sensitive_redacted: true`.
- Terminating or archiving a linked employee deactivates that branch membership and revokes access when no other active branch membership remains. Reinstatement does not restore access automatically.
- Frontend: `/app/employees`.

## Catalogue search (Stage 7.1)
- `GET /api/branches/{branch}/catalogue/search` (permission `branch.catalogue.view`). Query params: `q` (SKU/barcode exact), `oem`, `make`, `model`, `year`, `engine`. Results include `match_type`, `match_detail`, `source`, and fitment/OEM details when applicable.
- `POST /api/branches/{branch}/catalogue/import-fitments` and `POST /api/branches/{branch}/catalogue/import-oem` (permission `branch.catalogue.manage`). Require header `Idempotency-Key`, body `source`, `source_ref`, `format` (`json`), and `rows[]`. Imports are idempotent by branch+type+idempotency key and audited. No compatibility is invented; all imported rows must include `source` + `source_ref` (stored per row as provided by import payload context).
- Typo tolerance is deterministic: for search tokens of length >= 5, Levenshtein distance <= 1 is allowed only in the bounded fallback paths described in code; tests cover false positives/no-result cases.
- Frontend: `/app/catalogue-search` (search form, results with match type/source, import form for managers).

## Service catalogue and job cards (Stage 7.2)
- Permissions: `branch.services.view`, `branch.services.manage`, `branch.jobs.view`, `branch.jobs.manage`.
- `GET/POST /api/branches/{branch}/services`, `PATCH /api/branches/{branch}/services/{workshopService}` for the branch service catalogue.
- `GET/POST /api/branches/{branch}/jobs`, `GET/PATCH /api/branches/{branch}/jobs/{job}`, `POST /jobs/{job}/labour|parts|estimate|approve|start|complete|cancel|reopen|invoice`.
- State machine: `draft → estimated → approved → in_progress → completed`; `cancel` stores the previous status and `reopen` restores it. Invalid transitions return 422.
- Labour and part lines are editable only in `draft`; `estimate` snapshots totals into `estimate_minor`; `start` requires approval and technician; `complete` requires diagnosis.
- `POST /jobs/{job}/parts` requires header `Idempotency-Key`; one part line and one canonical `StockMovement` (`movement_type=job_consumption`, `source=job`) are created. Same key + payload replays the original line; same key + different payload returns 409.
- Cancel and reopen never post reversal movements and never remove or create invoices. Invoice links use `POST /jobs/{job}/invoice` with an existing branch `sale_id`; no invoice is auto-generated.
- Frontend: `/app/jobs` (queue, create form, detail, labour/part entry, estimate/approval/start/completion/cancel/reopen/invoice-link controls).
- Browser runtime verification was NOT RUN; rely on focused feature tests and the frontend typecheck/lint/build commands.

## Stock transfers and stocktakes (Stage 7.3)
- Permissions: `branch.transfers.view`, `branch.transfers.manage`, `branch.stocktakes.view`, `branch.stocktakes.manage`, `branch.stocktakes.approve`.
- `GET/POST /api/branches/{branch}/stock-transfers`
- `GET /api/branches/{branch}/stock-transfers/{stockTransfer}`
- `POST /api/branches/{branch}/stock-transfers/{stockTransfer}/dispatch|in-transit|receive|cancel`
- `GET/POST /api/branches/{branch}/stocktakes`
- `GET /api/branches/{branch}/stocktakes/{stocktake}`
- `POST /api/branches/{branch}/stocktakes/{stocktake}/review|approve`
- Transfer state machine: `draft → dispatched → in_transit → received`; `cancel` is allowed before receipt and from draft/dispatched/in_transit.
- A transfer requires an explicit source product/unit and destination product/unit pair. Only base units are supported; no conversion factor is invented.
- Dispatch posts one `transfer_out` source movement. Receipt posts one `transfer_in` destination movement. Cancellation posts one equal-and-opposite source movement and never reverses destination receipt.
- Both branches must be active and the actor must hold `branch.transfers.manage` in both branches when dispatching, receiving or cancelling.
- Duplicate receive and duplicate approval return 409 without posting additional movements.
- Stocktakes lock expected quantities from `stock_levels`, require a reason for each nonzero variance, and post one canonical `stocktake_adjustment` movement per nonzero line at approval.
- Frontend: `/app/transfers` has transfer and stocktake tabs with list, create, detail and permission-aware action controls; source branch selection is available to multi-branch users.
- Browser runtime verification was NOT RUN; rely on focused feature tests and the frontend typecheck/lint/build commands.

## Leave, attendance and disciplinary records (Stage 6.2)
- `GET/POST /api/branches/{branch}/leave-types` and `PATCH /api/branches/{branch}/leave-types/{leaveType}`
- `GET/POST /api/branches/{branch}/leave-requests`; `GET /leave-requests/{leaveRequest}`
- `POST /api/branches/{branch}/leave-requests/{leaveRequest}/approve|reject|cancel`
- `GET/POST /api/branches/{branch}/attendance`
- `GET/POST /api/branches/{branch}/disciplinary-records`; `GET/PATCH /disciplinary-records/{disciplinaryRecord}`; `POST /disciplinary-records/{disciplinaryRecord}/transition`
- `POST /api/branches/{branch}/hr/attachments`; `GET /api/branches/{branch}/hr/attachments/{hrAttachment}/download`
- Leave days exclude weekends unless the leave type has `counts_weekends=true`. Public holidays are not configured yet; approval is the manual check for holiday overlap.
- Overlapping `submitted`/`approved` requests are rejected. Annual limits count approved days whose start date falls in the request's start year and are rechecked at approval.
- Leave types with `requires_approval=false` are auto-approved on submission. Rejection requires a decision note.
- Attendance is an upsert keyed by branch, employee and work date; posting the same pair updates the existing record. Future work dates are rejected.
- Disciplinary mutations require both `branch.hr.manage` and `branch.hr.sensitive.view`. Transitions are `open → resolved|closed`, `resolved → appealed|closed`, `appealed → resolved|closed`; closed records cannot be edited.
- HR attachments are stored on the private `local` disk under `hr/{branch}`. Accepted types are PDF, JPG/JPEG, PNG and WebP up to 5 MB. Disciplinary attachments require sensitive HR permission to upload and download.
- Frontend: `/app/hr`. Leave approval uses `branch.hr.leave.approve`; all other HR administration uses `branch.hr.manage`.


## Payroll, salary grades and loans (Stage 6.3)
- `GET/POST /api/branches/{branch}/payroll-employees`
- `GET/POST /api/branches/{branch}/salary-grades`
- `GET/POST /api/branches/{branch}/employee-salary-assignments`
- `GET/POST /api/branches/{branch}/employee-pay-components`
- `GET/POST /api/branches/{branch}/payroll-statutory-rules`
- `GET/POST /api/branches/{branch}/employee-loans`
- `GET/POST /api/branches/{branch}/payroll-periods`
- `POST /api/branches/{branch}/payroll-periods/{payrollPeriod}/run`
- `GET /api/branches/{branch}/payroll-runs/{payrollRun}`
- `POST /api/branches/{branch}/payroll-runs/{payrollRun}/approve`
- `GET /api/branches/{branch}/payslips/{payslip}`
- `POST /api/branches/{branch}/payslips/{payslip}/adjustments`
- `GET /api/branches/{branch}/payroll-adjustments`
- `POST /api/branches/{branch}/payroll-adjustments/{payrollAdjustment}/reverse`
- Permissions: `branch.payroll.view`, `branch.payroll.manage`, `branch.payroll.approve`.
- Money is stored in integer minor units and displayed through the payroll UI; statutory percentages and fixed amounts are branch configuration, not hardcoded rates.
- Generating a run for an open period replaces the previous draft. Approval locks the period and applies loans and applied adjustments once.
- Corrections to approved payslips create pending adjustments for a later open period; reversals create an opposite pending adjustment.
- Frontend: `/app/payroll`.


## Payroll payment and reconciliation (Stage 6.4)
- `GET/POST /api/branches/{branch}/payroll-runs/{payrollRun}/payments`
- `GET /api/branches/{branch}/payroll-runs/{payrollRun}/reconciliation`
- `GET /api/branches/{branch}/payroll-runs/{payrollRun}/payslips/export`
- Approving a run creates one accrued payroll expense for `gross + employer statutory` and links it from `payroll_accruals`.
- Payment allocation records a `payroll_payments` row and posts an `adjustment_out` money movement with `source=payroll`; allocations cannot exceed approved net pay.
- Reconciliation compares run totals with payslip sums, payment totals, money movements and accrual existence; export requires `branch.payroll.export`.
- Frontend: `/app/payroll` run detail shows reconciliation, payment allocation and export controls.


## Tasks and notifications (Stage 6.5)
- `GET/POST /api/branches/{branch}/tasks`, `GET/PATCH /api/branches/{branch}/tasks/{task}`
- `POST /api/branches/{branch}/tasks/{task}/complete`, `POST /api/branches/{branch}/tasks/{task}/cancel`
- `GET /api/branches/{branch}/notifications`, `POST /api/branches/{branch}/notifications/{notification}/read`
- `GET/PUT /api/branches/{branch}/notification-preferences`
- Task state changes create distinct `task_events`; completions are append-only records.
- `SendTaskNotification` queues in-app and email deliveries; failures are visible on the notification and never mutate task state.
- Frontend: `/app/tasks`.


## HR/payroll acceptance (Stage 6.6)
- Run `php8.4 vendor/bin/phpunit tests/Feature/Hr/HrPayrollAcceptanceTest.php`.
- Fixtures prove leave approval, payroll generation with loan deduction, approval separation, lock enforcement, pending correction, partial payment, reconciliation, audit and money movement recording.
- `config/torqueerp.php` records statutory, tax and leave-pay assumptions as `BLOCKED` for production confirmation.
- Access revocation and other-branch access are covered by negative API tests.


## Performance optimization (Stage 8.2)
- `GET /api/branches/{branch}/sales` returns summary rows plus `meta`; heavy item/payment/void relations are available on the single-sale endpoint.
- `GET /api/branches/{branch}/stock/levels` and `GET /api/branches/{branch}/stock/movements` cap `per_page` at 100 and return pagination `meta`.
- Frontend routes are code-split and intelligence charts load lazily; search inputs use deferred values.
- Benchmark fixture commands and results are in `docs/CHANGELOG.md` Stage 8.2.

## Tests
- Backend: `cd backend && php artisan test` (requires PHP with `pdo_sqlite`)
- If the local PHP 8.4 CLI lacks `pdo_sqlite`, run the suite with a temporary extracted extension:
  ```bash
  php -d extension=/path/to/php8.4/pdo_sqlite.so vendor/bin/phpunit
  ```
  Use `vendor/bin/phpunit` for this case: `php artisan test` may spawn a PHP process that does not inherit the `-d extension` flag. Do not commit machine-specific extension paths.
- Frontend: `cd frontend && npm run typecheck && npm run lint && npm run build`

### MariaDB test database
Use a dedicated database, never the development/production database, because the suite migrates fresh and wraps tests in transactions:
```bash
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS torqueerp_stage12_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cd backend
DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php vendor/bin/phpunit
```
Stage 1.2 suite baseline: 87 tests, 310 assertions.

## Lint / Format / Type
- Backend: `./vendor/bin/pint` (format), `php artisan test` + static analysis as configured
- Frontend: `npm run lint`, `npm run format`, `npm run typecheck`

## Notes
- No secrets in repo. Use local `.env` files.
- Branch isolation enforced server-side (see docs/BUSINESS_RULES.md).
- Public pages must remain indexable policy-compliant; authenticated pages not indexed.

## Loyalty and win-back (Stage 7.4)
- Backend: `cd backend && php8.4 artisan migrate --force` applies `loyalty_configs`, `loyalty_sale_events`, `loyalty_qualifications` and the customer marketing-consent columns.
- Focused test: `cd backend && php8.4 vendor/bin/phpunit tests/Feature/Loyalty/LoyaltyTest.php --colors=never`.
- Full MariaDB suite: `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit --colors=never`.
- Frontend: `cd frontend && npm run typecheck && npm run lint && npm run build`.
- API surface (branch-scoped, permission middleware):
  - `GET/PUT /api/branches/{branch}/loyalty/config`
  - `GET /api/branches/{branch}/loyalty/qualifications`
  - `POST /api/branches/{branch}/loyalty/recalculate`
  - `POST /api/branches/{branch}/loyalty/sale-events`
  - `GET /api/branches/{branch}/loyalty/winback/candidates`
  - `POST /api/branches/{branch}/loyalty/winback/export`
  - `POST /api/branches/{branch}/loyalty/discount/quote`
- Permissions: `branch.loyalty.view`, `branch.loyalty.manage`, `branch.loyalty.approve`. Approve permission is checked in the discount quote service when the combined discount exceeds the configured threshold.
- Defaults are in `backend/config/loyalty.php`; override them per branch via the config endpoint rather than editing code.
- Record consent with `PATCH /api/branches/{branch}/customers/{customer}` using `marketing_consent_at`, `marketing_channel` (`sms`, `email`, `whatsapp`) and `marketing_channel_active`; changes are audited.
- No message transport is configured. The win-back export endpoint is a stub and marks delivery `NOT_RUN`.

## Assets and maintenance (Stage 7.5)
- Backend: `cd backend && php8.4 artisan migrate --force` applies `2026_11_20_224853_create_assets_table` and `2026_11_20_224854_create_asset_maintenances_table`.
- Focused test: `cd backend && php8.4 vendor/bin/phpunit tests/Feature/Assets/AssetManagementTest.php --colors=never` (6 tests / 71 assertions).
- Full MariaDB suite: `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit --colors=never` (293 tests / 1705 assertions).
- Format/check: `cd backend && vendor/bin/pint --dirty --format agent`; frontend `cd frontend && npm run typecheck && npm run lint && npm run build` (2 pre-existing lint warnings, existing large-chunk warning).
- API surface (branch-scoped, permission middleware):
  - `GET/POST /api/branches/{branch}/assets`
  - `GET /api/branches/{branch}/assets/depreciation-estimate` (ESTIMATE report, no writes)
  - `GET /api/branches/{branch}/assets/{asset}`
  - `GET /api/branches/{branch}/assets/{asset}/depreciation-estimate` (ESTIMATE, no writes)
  - `POST /api/branches/{branch}/assets/{asset}/maintenances`
  - `POST /api/branches/{branch}/assets/{asset}/dispose`
- Permissions: `branch.assets.view`, `branch.assets.manage`, `branch.assets.dispose`. Admin has all three; manager view/manage; accountant view/dispose; storekeeper and viewer view.
- Acquisition/source links and maintenance links must reference an existing record in the same branch; they never create an `Expense`, `SupplierBill` or `MoneyMovement`. Duplicate source links return 409.
- Estimate endpoints require `useful_life_years` and accept optional `as_of` and `residual_minor`. Both are caller assumptions; results are labelled ESTIMATE / BLOCKED and no ledger row is written.
- Disposal requires `disposal_date`, `proceeds_minor`, `accumulated_estimate_minor` and optional `notes`; it writes an `asset.disposed` audit row and returns the financial effect summary without posting entries.
- Browser runtime verification: NOT RUN (per constraints). See `docs/CHANGELOG.md` Stage 7.5 for the full acceptance report and rollback notes.

## Inventory intelligence and dashboard v2 (Stage 7.6)
- Backend: no migration is required. Run `cd backend && php8.4 artisan migrate --force` to confirm (`Nothing to migrate`), then `php8.4 vendor/bin/pint --dirty --format agent`.
- Focused test: `cd backend && php8.4 vendor/bin/phpunit tests/Feature/Reports/DashboardV2Test.php --colors=never` (6 tests / 96 assertions).
- Full MariaDB suite: `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit --colors=never` (299 tests / 1801 assertions).
- Frontend: `cd frontend && npm run typecheck && npm run lint && npm run build` (2 pre-existing lint warnings, existing large-chunk warning).
- API surface (branch-scoped, inside `branch.context`): `GET /api/branches/{branch}/dashboard/v2?as_of=YYYY-MM-DD`. There is no route-level single-permission middleware: the controller allows `branch.inventory.view` (inventory sections) or `branch.finance.view` (finance metrics and cash forecast) and returns 403 if neither is present.
- Response sections: `finance_metrics` (existing `FinanceMetricsService` output), `inventory_intelligence.reorder`, `inventory_intelligence.ageing`, `inventory_intelligence.abc`, `cash_forecast`, `capabilities`, `windows` and a merged `data_quality` warning list. Null sections mean the caller lacks that permission.
- Defaults live in `backend/config/inventory_intelligence.php`: reorder sales window 30 days / lead time 7 / cover 14; ageing slow 30 days / dead 90 days; ABC sales window 90 days / A 70% / B 90%; cash history 30 days / horizon 30 / minimum 3 cash events. Config is cached in production; run `php8.4 artisan config:clear` after changing it.
- Frontend pages: `/app` keeps the finance cards and adds the shared v2 panels plus a link; `/app/inventory-intelligence` renders the same `IntelligenceCharts` component. Charts use captioned tables, header scopes, text alternatives for bars and visible warnings/assumptions.
- The cash forecast is always labelled `FORECAST` and is not an authoritative finance statement. Browser runtime verification: NOT RUN (per constraints). See `docs/CHANGELOG.md` Stage 7.6 for the full acceptance report and rollback notes.

## Phase 7 integration acceptance (Stage 7.7)
- Backend: no migration is required. Run `cd backend && php8.4 artisan migrate --force` to confirm (`Nothing to migrate`), then `vendor/bin/pint --dirty --format agent`.
- Focused test: `cd backend && php8.4 vendor/bin/phpunit tests/Feature/Acceptance/PhaseSevenIntegrationAcceptanceTest.php --colors=never` (4 tests / 180 assertions).
- Full MariaDB suite: `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit --colors=never` (303 tests / 1981 assertions).
- Frontend: NOT RUN because Stage 7.7 changed no frontend files. Browser runtime verification: NOT RUN (per constraints).
- Coverage: job labour/parts/manual invoice, transfer dispatch/receipt, stocktake approval, loyalty qualification/stacking/floor/approval, return effects, sales/stock reports, dashboard v2, tenant boundaries, idempotency and no duplicate postings.
- Known limits: exact COGS is BLOCKED because no cost basis/cost layers exist; external catalogue/OEM source, automated win-back, authoritative depreciation/forecast use, base-unit equivalence and statutory/tax assumptions remain unconfirmed. See `docs/CHANGELOG.md` Stage 7.7 for the full acceptance report and rollback notes.

## Security threat review and fixes (Stage 8.1)
- Backend: no migration is required. Run `cd backend && php8.4 artisan migrate --force` to confirm (`Nothing to migrate`), then `vendor/bin/pint --dirty --format agent`.
- Focused test: `cd backend && php8.4 vendor/bin/phpunit tests/Feature/Security/SecurityRegressionTest.php` (14 tests / 54 assertions).
- Full MariaDB suite: `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit` (312 tests / 2025 assertions).
- Frontend: NOT RUN because no frontend files changed. Browser runtime verification: NOT RUN (per constraints).
- Security control reviewed: `backend/bootstrap/app.php` no longer exempts `login` or `logout` from `validateCsrfTokens`; the SPA already calls `/sanctum/csrf-cookie` before login and sends `X-XSRF-TOKEN` through `frontend/src/lib/api.ts`.
- Negative coverage lives in `backend/tests/Feature/Security/SecurityRegressionTest.php`. Existing protections verified include Laravel `abort(401/403)` middleware, Sanctum token revocation, branch-scoped route model binding, validated-request allow-lists, Blade/`JSON_HEX_*` escaping, hashed webhook signatures, audit redaction/immutability and login throttling.
- Residual risks: upstream Composer advisories are BLOCKED until dependency changes are approved. No destructive production test, no real secrets and no new custom crypto were introduced. See `docs/CHANGELOG.md` Stage 8.1 for the full acceptance report and rollback notes.

## SEO verification (Stage 8.3)
- Backend: no migration is required. Run `cd backend && php8.4 artisan migrate --force` to confirm (`Nothing to migrate`), then `vendor/bin/pint --dirty --format agent`.
- Focused test: `cd backend && php8.4 vendor/bin/phpunit tests/Feature/PublicSeoTest.php` (14 tests / 273 assertions). Adjacent regression check: `php8.4 vendor/bin/phpunit tests/Feature/PublicCatalogueTest.php tests/Feature/CmsContentTest.php tests/Feature/PublicReservationTest.php` (20 tests / 111 assertions).
- Full MariaDB suite: `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit` (327 tests / 2342 assertions).
- Frontend: `cd frontend && npm run typecheck`, `npm run lint` (2 pre-existing warnings, 0 errors) and `npm run build` (PASS) because `public/TMS_Homepage.html` and `public/favicon.svg` changed.
- Verification method: DOMXPath parses rendered Blade HTML for head metadata, canonical, H1/heading order, alt text, JSON-LD, internal links, slugs, structural validity and private-URL absence. Sitemap/robots routes are asserted directly. No browser runtime, external HTML validator or crawler-console data was used (NOT RUN).
- Bounded fixes: `'@'.'context'` avoids Laravel 13's `@context` Blade directive mangling JSON-LD; `PublicCatalogueController` supplies unique page descriptions; `public/page.blade.php` emits a title H1 when a published page has no hero section; the shadowing static `public/robots.txt` was removed; the SPA iframe mockup is noindex and `frontend/public/favicon.svg` was added.
- Rollback: restore the removed static robots file and original Blade/controller/frontend/test/doc changes; no migration or data rollback is required. See `docs/CHANGELOG.md` Stage 8.3 for the full acceptance report and risks.

## Queues, external services and monitoring (Stage 8.4)
- Backend: `cd backend && php8.4 artisan migrate --force --no-interaction` applies `2026_11_21_033105_create_webhook_events_table`; then run `vendor/bin/pint --dirty --format agent`.
- Focused tests: `php8.4 vendor/bin/phpunit tests/Feature/Queues/QueueReliabilityTest.php tests/Feature/Externals/HealthCheckTest.php tests/Feature/Externals/PaynowWebhookReliabilityTest.php tests/Feature/PublicReservationTest.php tests/Feature/Tasks/TaskNotificationTest.php tests/Feature/ReservationNotificationTest.php --colors=never` (final PASS, 31 tests / 171 assertions).
- Full MariaDB suite: `DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit --colors=never` (PASS, 338 tests / 2419 assertions).
- Correlation IDs: send `X-Correlation-Id` and `X-Request-Id` (or omit them to let the app generate UUIDs); the values are echoed on every response, shared with logs and copied into queued payloads for worker-side logs.
- Observability config: `OBSERVABILITY_LOG_CHANNEL`, `OBSERVABILITY_LOG_LEVEL`, `OBSERVABILITY_LOG_MAX_FILES` and `FAILED_JOB_LOG_CHANNEL` live in `backend/config/observability.php` and `backend/config/logging.php`. Set `FAILED_JOB_LOG_CHANNEL=slack` with `LOG_SLACK_WEBHOOK_URL` to page on `queue.job.failed`; without that, failures only reach `storage/logs/observability.log` (BLOCKED).
- Paynow bounds: `PAYNOW_CONNECT_TIMEOUT_SECONDS` (1-10, default 3), `PAYNOW_TIMEOUT_SECONDS` (1-30, default 8) and `PAYNOW_POLL_RETRIES` (0-2, default 2) are clamped in `backend/config/paynow.php`; each webhook polls at most once (`max_polls_per_callback=1`, three total HTTP attempts).
- Webhook dedupe: `webhook_events` stores a unique `(provider, event_key)` per signed Paynow callback. Replays are ignored before any provider poll. Operate retention separately; no pruning scheduler was added in this stage.
- Health check: `GET /api/health` returns database/queue/failed-job checks and correlation IDs; 503 means degraded. It never returns raw exception text.
- Frontend checks and browser runtime: NOT RUN because no frontend file changed and browser verification is out of scope. No real Paynow, SMTP, Slack or browser call was made.
- Rollback: revert the queue/job/health/Paynow/logging/observability/test/doc changes and run `php8.4 artisan migrate:rollback --step=1` to drop `webhook_events` after draining callbacks; clear cached config. See `docs/CHANGELOG.md` Stage 8.4 for the full acceptance report, assumptions and risks.

## Backup, restore and retention drill (Stage 8.5)
- Full runbook: `docs/BACKUP_RESTORE.md`. No new schema, permission, route or application code was added; scripts live in `backend/scripts/backup/`.
- Run the whole bounded drill from the repo root: `set -a; . backend/.env; set +a; bash backend/scripts/backup/run_drill.sh`. It uses only `torqueerp_stage85_source_test`, `torqueerp_stage85_restore_test` and the migrated `torqueerp_stage12_test` template; it drops/recreates only the two drill databases.
- Individual steps: `BACKUP_SOURCE_DB=<db> BACKUP_DIR=<dir> bash backend/scripts/backup/backup.sh` and `RESTORE_TARGET_DB=<db> BACKUP_MANIFEST=<manifest> bash backend/scripts/backup/restore.sh`. Add `RESTORE_REPLACE=1` only when deliberately replacing an existing non-production target; add `RESTORE_VERIFY_ONLY=1` to re-run read-only checks.
- The manifest is `torqueerp-backup-manifest/v1`; validate it with `php8.4 backend/scripts/backup/manifest.php validate <manifest.json>`. It records UTC creation time, source DB, tool versions, key-table counts, artifact sizes/SHA-256 and encryption/off-site state, never credentials.
- Optional encryption at rest: set `GPG_RECIPIENT=<key-id>` for `backup.sh`; the artifact is GPG-encrypted and the plaintext removed. The demonstrated drill used `encryption_at_rest=none` and the GPG path is NOT RUN.
- Restore verification is read-only SQL: 14 key-table counts plus stock-ledger projection, no negative/over-reserved stock, posted sale receipt coverage, paid/payment totals, sale/item totals, stock idempotency and product/branch integrity.
- Safety guards refuse non-`torqueerp_*_test` names, `torqueerp_test` / `torqueerp_stage12_test` as targets, and any target equal to the manifest source.
- Verification: `cd backend && vendor/bin/pint --dirty --format agent` (PASSED), `php8.4 artisan migrate --force --no-interaction` (`Nothing to migrate`), full MariaDB suite `DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit` (PASS, 338 tests / 2419 assertions). Frontend and browser checks: NOT RUN because no frontend file changed.
- Proposed nightly cron, retention, off-site storage, 15-minute RPO and secret rotation are documented in `docs/BACKUP_RESTORE.md`; they are not installed controls in this environment.
- Rollback: delete the new script directory and doc changes; optionally `DROP DATABASE torqueerp_stage85_source_test; DROP DATABASE torqueerp_stage85_restore_test;` after capturing evidence. No application migration, permission, ledger row or configuration rollback is required. See `docs/CHANGELOG.md` Stage 8.5 for the full acceptance report.

## Deployment and operations runbook (Stage 8.6)
- Full runbook: `docs/DEPLOYMENT.md`. It records the actual host observed here and uses placeholders for the unknown production host instead of assuming VPS/cPanel details.
- Verified host: PHP 8.4.27 CLI, Node 22.17.1, npm 10.9.2, Composer 2.7.1, Laravel 13.39.0, nginx 1.24.0 (active on port 80 with an unrelated default root), XAMPP Apache 2.4.58 installed but not running, MariaDB server 8.4.11 via `pdo_mysql`. HTTPS is not configured.
- `php8.4 artisan --version` from the repo root FAILS with `Could not open input file: artisan`; run `cd backend && php8.4 artisan --version` instead.
- Local dev commands: `cd backend && composer install --no-interaction --prefer-dist`, `php8.4 artisan key:generate --no-interaction` (first setup only), `php8.4 artisan migrate --force --no-interaction`, `php8.4 artisan optimize:clear --no-interaction`, `php8.4 artisan serve --host=127.0.0.1 --port=8000`; frontend `cd frontend && npm ci && npm run dev -- --host=127.0.0.1 --port=5173`.
- Health check: `curl -fsS -D - http://127.0.0.1:8000/api/health` returned HTTP 200 with `status=ok`, database/queue checks OK, zero pending and zero failed jobs. Production URL is `https://<public-host>/api/health` (placeholder).
- Queue: `php8.4 artisan queue:work --queue=default --tries=3 --timeout=30 --sleep=3 --no-interaction`; scheduler: `php8.4 artisan schedule:list --no-interaction` shows `reservations:expire` every minute and local `php8.4 artisan schedule:work --no-interaction`. No TMS worker or scheduler is installed locally.
- Production config/asset commands (not run here): `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`, `cd frontend && npm ci && npm run build`, `php8.4 artisan config:clear && php8.4 artisan config:cache && php8.4 artisan optimize`, then restart workers/PHP-FPM and recheck health.
- Storage permissions: local `storage` and `bootstrap/cache` are `775 wayne:wayne`; production commands use `<web-user>:<web-group>` placeholders and avoid `777`. Private files live in `backend/storage/app/private`.
- Logs: `backend/storage/logs/laravel.log` (single) and `backend/storage/logs/observability-YYYY-MM-DD.log` (daily, 14 files by default). Log rotation/shipping and external uptime checks are BLOCKED.
- No migration/code/frontend change: PHPUnit, Pint and frontend checks NOT RUN this stage. Last known full MariaDB suite from Stage 8.5: PASS, 338 tests / 2419 assertions. See `docs/CHANGELOG.md` Stage 8.6 for the full criterion-by-criterion report and rollback notes.

## Translations, accessibility and mobile (Stage 8.7)
- Translation work is BLOCKED until the owner supplies the language list and approved copy. Only `en`/`sn` (11 keys each) exist in `frontend/src/i18n/index.ts`; `LocaleProvider` is not mounted in `frontend/src/main.tsx`, no UI selects a locale and zero components call `t()`. Do not describe six languages as complete.
- Static a11y audit method: scan TSX for `<label>/<Label>` association, `<th scope>`, ARIA roles/names, focus-visible styling, heading levels, table overflow wrappers and POS target sizes. There is no a11y test framework and no dependency was added.
- Verification commands: `cd frontend && npm run typecheck` (PASS), `npm run lint` (PASS, 2 pre-existing context warnings), `npm run build` (PASS). Backend PHPUnit/Pint NOT RUN: no backend file changed.
- Browser runtime, screen-reader and real-device checks are NOT RUN. Manual follow-up: keyboard walks of login/POS/tasks/payroll/HR, visible focus, announcements, 375 px reflow and POS touch interaction.
- Rollback: revert the Stage 8.7 frontend files listed in `docs/CHANGELOG.md`, then `cd frontend && npm run build`. No migration, data or configuration rollback is needed.

## Release-candidate acceptance (Stage 8.8)
- Overall: PARTIAL / BLOCKED for production; NOT production-ready. Release gates and owners are in `docs/RELEASE_CHECKLIST.md`; the full criterion report is in `docs/CHANGELOG.md` (Stage 8.8).
- Dependency validity (no changes): `cd backend && composer validate --no-check-publish --no-interaction` and `composer install --dry-run --no-interaction`; `cd frontend && npm ci --dry-run --ignore-scripts`. Do not edit manifests or lockfiles without approval.
- Clean DB acceptance: create a NEW `torqueerp_*_test` database from the ignored `backend/.env` credentials (never production or a shared template), then `cd backend && DB_DATABASE=<new-db> php8.4 artisan migrate:fresh --force --no-interaction` (69 migrations), `DB_DATABASE=<new-db> php8.4 artisan migrate:status --no-interaction`, and check `SELECT COUNT(*) FROM <new-db>.migrations` = 69.
- Full MariaDB suite: `cd backend && DB_CONNECTION=mariadb DB_DATABASE=<new-db> php8.4 vendor/bin/phpunit --colors=never` (PASS, 338 tests / 2419 assertions / 159361 ms in this run).
- Focused suites (each with `cd backend && DB_CONNECTION=mariadb DB_DATABASE=<new-db> php8.4 vendor/bin/phpunit ... --colors=never`):
  - Tenant/security: `tests/Feature/BranchIsolationTest.php tests/Feature/Tenancy/BranchContextTest.php tests/Feature/AccessControl/BranchAccessTest.php tests/Feature/AccessControl/BranchUserManagementTest.php tests/Feature/Security/SecurityRegressionTest.php` (44 tests / 136 assertions).
  - Finance: `tests/Feature/Acceptance/FinancialReconciliationTest.php tests/Feature/Finance/FinanceMetricsTest.php tests/Feature/Finance/ExpenseBillTest.php tests/Feature/Finance/MoneyMovementTest.php tests/Feature/Sales/ReceivableTest.php tests/Feature/Reports/ReportTest.php` (23 / 129).
  - SEO: `tests/Feature/PublicSeoTest.php tests/Feature/PublicCatalogueTest.php tests/Feature/CmsContentTest.php tests/Feature/PublicReservationTest.php` (34 / 385).
  - Offline recovery: `tests/Feature/Sync/SyncRecoveryTest.php tests/Feature/Sync/SyncTest.php` (5 / 16).
  - Performance: `tests/Feature/Performance/PerformanceOptimizationTest.php tests/Feature/Sales` (23 / 176).
- Frontend/build: `cd frontend && npm run typecheck && npm run lint && npm run build` (PASS; 2 pre-existing lint warnings; main chunk 337.31 kB / gzip 111.38 kB).
- Restore verification (artifacts from Stage 8.5): with env loaded, `php8.4 backend/scripts/backup/manifest.php validate <manifest>` then `RESTORE_TARGET_DB=torqueerp_stage85_restore_test BACKUP_MANIFEST=<manifest> RESTORE_VERIFY_ONLY=1 bash backend/scripts/backup/restore.sh` (14 counts and 7 invariants PASS). Verify-only never writes; create a fresh target for a full drill.
- Static a11y/mobile checks: read-only TSX scan for label association, `<th scope>`, POS `min-h-11`, `focus-visible`, i18n keys and `t()` calls. Browser, screen-reader and device checks remain NOT RUN; do not convert static PASS into runtime acceptance.
- Performance evidence: Stage 8.2 figures remain authoritative (`sales.index` 80.98→24.89 ms, 34→4 queries, 13,185→6,150 bytes; bundle 615.37→243.25 kB). No production traffic test was run.
- Residual gates: production host/TLS/secrets, worker/scheduler/uptime/pager, off-site encrypted backups/PITR, `composer audit` advisories, owner language list, legal/tax/payroll sign-off and browser QA. No dependency, schema, code or test was changed in this stage.
- Rollback: docs-only for Stage 8.8 (delete `docs/RELEASE_CHECKLIST.md`, revert the seven updated docs, drop only the acceptance DB after archiving evidence). For a real deployment follow `docs/DEPLOYMENT.md` section 5 and the rollback plan in `docs/RELEASE_CHECKLIST.md`.
