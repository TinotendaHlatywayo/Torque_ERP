
## [Unreleased]
### Added
- Stage 0.4: Safe schema and migration baseline
  - Laravel backend scaffolded (13.39.0)
  - Foundation migrations: platforms, branches, branch_users, audit_logs, document_sequences
  - Schema conventions documented (docs/SCHEMA_CONVENTIONS.md)
  - Conventions: ULID primary keys, UTC timestamps, soft deletes where appropriate, FK constraints, unique constraints, document numbering per branch+type

## [Unreleased]
### Added
- Stage 0.5: Authentication and tenant skeleton
  - Laravel Breeze API scaffolding installed with Sanctum
  - HasApiTokens added to User model
  - ResolveBranchContext middleware (server-side branch resolution, never trusts client branch_id)
  - BranchOwnedPolicy base class with default-deny
  - Health endpoint at /api/health
  - Session security settings in .env
  - Negative test for cross-branch access (BranchIsolationTest)

## [Unreleased]
### Added
- Stage 0.6: App shell, UI system and language foundation
  - Tailwind CSS v3 with design tokens (CSS variables), dark mode support
  - shadcn/ui-style components: Button, Input, Label, Card
  - Layout shells: PublicShell, AppShell, AuthShell with responsive design, skip link, ARIA labels
  - Feedback states: LoadingState (with spinner), Skeleton, EmptyState, ErrorState, SuccessState, PermissionDeniedState
  - i18n infrastructure (English, Shona) with LocaleContext
  - ThemeContext for light/dark mode
  - TanStack Query, axios API client, react-router-dom installed
  - Keyboard focus styles, responsive breakpoints, RTL-ready structure

## [Unreleased]
### Added
- Stage 0.7: Live skeleton acceptance
  - React Router wiring for public (/), auth (/login), app (/app), platform (/platform)
  - Real page components: HomePage, LoginPage, DashboardPage, PlatformLandingPage
  - TanStack Query provider configured
  - Migrations verified (nothing pending)
  - Full test suite passed (11 tests, 22 assertions)

## [Unreleased]
### Added
- Stage 1.1: Users, roles and permissions
  - Added `users.is_active` and `users.is_platform_admin` with safe migration
  - Added code-defined branch/platform permission catalogue and fixed branch roles
  - Added default-deny branch/platform authorization middleware
  - Added branch user list/create/update/remove API with audit logging
  - Added role catalogue API and current-user membership/permission response
  - Added immediate token/session revocation when a user loses their last branch membership
  - Added branch users and roles administration page with permission-aware states
  - Disabled public self-registration; disabled users cannot authenticate
  - Added allowed/forbidden, cross-branch, last-admin and revocation tests
### Changed
- Branch context no longer falls back to another branch when an explicit branch is requested and membership is absent
- Login page no longer prefills or displays default credentials

## [Unreleased]
### Added
- Stage 1.2: Products, categories, units and stock foundation
  - Added branch-owned `categories`, `units`, `products`, `product_units` and `stock_levels` tables with ULIDs, composite branch-safe FKs and search/uniqueness indexes
  - Added product CRUD, archive/activate, search, category/status filters and pagination API
  - Added category management with per-branch slug uniqueness, parent validation and cycle prevention
  - Added unit management with uppercase codes, decimal policy and per-branch uniqueness
  - Added explicit product alternate units with conversion factors and prices; conversion through the base unit is supported
  - Added integer minor-unit price handling with ISO 4217 currency exponents
  - Added stock-level foundation rows defaulting to zero; stock movements remain deferred to Stage 1.3
  - Added `/app/products` with product/category/unit management, search/filter/pagination, alternate units and a conversion tool
  - Added audit entries for catalogue create/update/archive actions
### Changed
- Catalogue permissions reuse `branch.inventory.view` and `branch.inventory.manage`
- Branch route bindings now use scoped model binding for catalogue resources
- Local seeded administrator defaults are now `admin@tms.com` / `admin123` (override in non-local environments)

## [Unreleased]
### Added
- Stage 1.3: Canonical stock ledger
  - Added `stock_movements` table with ULIDs, branch/product/unit FKs, movement metadata, idempotency key, request hash, opening marker and reversal link
  - Added `StockMovement`, `StockLedger`, `StockPostingResult` and stock level/movement API endpoints
  - Added durable opening, correction, sale-ready and reversal posting with transaction-safe balance updates
  - Added negative-stock rejection, duplicate idempotency handling, one-opening-per-product and one-reversal-per-movement guards
  - Added row-locked balance updates and MariaDB concurrent sale/oversell tests
  - Added 15 stock ledger tests covering idempotency, conversion, reversal, rollback, permissions, tenant isolation and concurrency
### Fixed
- Local login now works from both `localhost:5173` and `127.0.0.1:5173`; CORS allows both and the API base follows the browser host so session cookies remain same-site

## [Unreleased]
### Added
- Stage 1.4: Customer records
### Added
- Stage 1.5: POS cart and price calculation
### Added
- Stage 1.6: Checkout, payments and receipt
### Added
- Stage 1.7: Sales history and correction foundation
### Added
- Stage 1.8: End-to-end acceptance
### Added
- Stage 2.2: Payment accounts and cash movements
### Added
- Stage 2.3: Expenses and bills to pay
### Added
- Stage 2.4: Receivables and collections
### Added
- Stage 2.5: Finance metrics and FX history
### Added
- Stage 2.6: Documents and templates
  - Added `document_templates` table with versioning
  - Added template validation, preview and missing-placeholder checks
  - Added public document verification endpoint
  - Added receipt QR code and print CSS
  - Added 6 document template tests
  - Added `fx_rates` table and historical conversion service
  - Added finance metrics endpoint with documented formulas
  - Added configurable default tax rate
  - Added 3 finance tests
  - Added credit sale support and collection payments
  - Added receivables list and ageing endpoints
  - Added idempotent collection handling
  - Added 4 receivable tests
  - Added `expenses`, `supplier_bills` and `supplier_bill_payments` tables
  - Added expense incurred/paid lifecycle and payment
  - Added supplier bill payment allocation and ageing
  - Added 4 finance tests
  - Added `payment_accounts` and `money_movements` tables
  - Added account, movement, transfer and reversal APIs
  - Added immutable money movement and paired transfer handling
  - Added 6 finance tests for transfer, reversal, duplicate and multi-currency
  - Added `EndToEndTest` covering product, stock, customer, sale, payment, receipt and history
  - Added duplicate checkout, invalid discount, unauthorized override, tenant isolation and oversell tests
  - Verified migrations and frontend build
  - Added sales history/detail API and frontend page
  - Added authorized sale void with stock reversal
  - Added sale return request foundation with duplicate protection
  - Added 7 sales history/correction tests
  - Added `sales`, `sale_items`, `payments` and `receipts` tables
  - Added transactional checkout API with idempotency and stock movement posting
  - Added separate sale/payment states, split payments and unique receipts
  - Added sale detail and receipt print view
  - Added 7 checkout tests
  - Added `PriceCalculator` domain service for quantity, discount, floor price, tax and cart totals
  - Added immutable price line/cart result DTOs with approval reasons
  - Added configurable POS discount threshold via `config/pricing.php`
  - Added audit logging for approved price overrides
  - Added 10 price calculation tests
  - Added `customers` table with branch ownership, contact details, address, notes and active/archive state
  - Added customer list/create/show/update/archive API with branch-scoped search and pagination
  - Added `branch.customers.view` and `branch.customers.manage` permissions
  - Added duplicate warnings for similar names, phone numbers or emails without automatic merging
  - Added 7 customer tests covering creation, duplicates, update/archive, search, tenant isolation and permissions

## [Unreleased]
### Added
- Stage 2.7: Reports and dashboard v1
  - Added report endpoints and dashboard metrics
  - Added metric dictionary
- Stage 2.8: Financial reconciliation acceptance
  - Added `FinancialReconciliationTest`
  - Verified no double-counting of receiving, purchases, expenses and COGS

## [Unreleased]
### Added
- Stage 3.1: Branch registration and lifecycle
  - Added branch lifecycle fields and status transitions
  - Added platform branch registration/approval/suspension/activation/archive API
  - Added suspended-branch write protection
  - Added 3 lifecycle tests

## [Unreleased]
### Added
- Stage 5.6: Notifications and public verification
  - Added durable per-recipient reservation notification delivery records with queue status, attempts and terminal failure visibility
  - Added bounded, idempotent queued email delivery for customer reservation and authorized branch-manager notifications
  - Added delivery status to the staff reservation API without exposing recipient addresses
  - Added notification idempotency, permission, delivery-failure and email-escaping regression coverage
  - Verified the server-rendered public home page in a real browser at desktop and 390px mobile with no console errors

## [Unreleased]
### Added
- Stage 3.2: Membership and context switching
  - Added host validation to branch context resolution
  - Added tenancy context tests

## [Unreleased]
### Added
- Stage 3.3: Platform/branch settings
  - Added `settings` table with scoped keys
  - Added setting validation, precedence and audit
  - Added 4 settings tests

## [Unreleased]
### Added
- Stage 3.4: Cross-branch reports and exports
  - Added platform branch comparison endpoint
  - Added private scoped expiring CSV export
  - Added 3 platform report tests

## [Unreleased]
### Added
- Stage 3.5: Audit and platform acceptance
  - Added audit redaction and immutability
  - Added login/logout audit entries
  - Added audit retention configuration
  - Added security regression tests

## [Unreleased]
### Added
- Stage 4.6: Interruption/recovery acceptance
  - Added `SyncRecoveryTest`
  - Documented offline availability limits

## [Unreleased]
### Added
- Stage 5.1: Public rendering and SEO foundation
  - Added a server-rendered Taziva Motor Spares homepage with responsive accessible layout
  - Added canonical, Open Graph, description, language, favicon and accurate AutoPartsStore JSON-LD metadata
  - Added environment-specific robots.txt, public sitemap.xml and a noindex React workspace shell
  - Added four public SEO and crawler policy feature tests

## [Unreleased]
### Added
- Stage 5.2: CMS and media library
  - Added platform-admin CMS editor for draft pages, responsive sections, previews, publish and revision restore
  - Added immutable version snapshots; draft changes do not replace published content until explicit publish
  - Added private media upload with image-only validation, size/dimension limits and conditional responsive WebP/AVIF conversion
  - Added authorized preview and public-media routes that expose images only through published page revisions
  - Added CMS permission, publication, rollback, draft-leak and unsafe-upload tests

## [Unreleased]
### Added
- Stage 5.3: Public catalogue and product pages
  - Added product publication controls for a customer-facing slug and description
  - Added responsive, server-rendered catalogue, category, search and product pages with pagination and internal links
  - Added field allow-list behavior: private product notes and stock levels do not appear publicly
  - Added canonical/OG metadata and accurate product/category structured data; search results are noindex
  - Added configured active-branch scope, published product sitemap entries and 410 responses for archived products
  - Added product publication validation and public catalogue privacy/SEO tests

## [Unreleased]
### Added
- Stage 5.4: Stock reservations
  - Added public reservation request and private token-based status/cancellation pages
  - Added encrypted customer contact details, idempotent request handling and quantity/price snapshots
  - Added branch stock holds that release on cancellation or scheduled expiry
  - Prevented stock ledger postings from consuming inventory already held for reservations
  - Added a minute-scheduled expiry command and an authorized branch reservations screen
  - Added reservation privacy, idempotency, cancellation, expiry and stock validation tests

## [Unreleased]
### Added
- Stage 5.5: Paynow payment integration boundary
  - Added Paynow hosted checkout initiation with SHA-512 request and response hash validation
  - Added signed callback verification, server-to-server status polling and bounded network timeouts
  - Added idempotent payment state transitions for paid, cancelled, refunded, disputed and late events
  - Kept hosted payment details encrypted and card/payment secrets out of TorqueERP
  - Added Paynow state to customer reservation status and authorized branch reservation views
  - Added provider-mocked coverage for official hash vectors, callbacks, duplicate events, tampering and out-of-order results

## [Unreleased]
### Added
- Stage 6.1: Employee records and lifecycle
  - Added branch-owned `employees` table with generated employee numbers and an optional explicit link to a login account
  - Added hired/active/suspended/terminated/archived lifecycle with enforced transitions and archival instead of deletion
  - Added sensitive HR field permission (`branch.hr.sensitive.view`) with server-side redaction
  - Added automatic branch-access revocation on termination/archival, including token/session revocation when no branch access remains
  - Added `/app/employees` list, search, create/edit and lifecycle actions
  - Added employee lifecycle, privacy, uniqueness, permission and branch-isolation tests

## [Unreleased]
### Added
- Stage 6.2: Leave, attendance and disciplinary records
  - Added branch-scoped leave types with paid/unpaid, approval policy, annual limits, weekend handling and active state
  - Added leave requests with working-day calculation, overlap prevention, annual-limit enforcement, approval/rejection/cancellation and auto-approval
  - Added separate `branch.hr.leave.approve` permission so leave approval does not require full HR administration
  - Added attendance upsert per employee and date, with worked/scheduled/overtime minutes and audit history
  - Added sensitive disciplinary records with enforced lifecycle transitions and read-only closure
  - Added private HR attachments for leave and disciplinary records with type/size validation and sensitive-download gating
  - Added `/app/hr` leave, attendance and disciplinary workspace
  - Added leave, attendance, disciplinary, attachment, permission and branch-isolation tests (7 tests / 67 assertions)

## [Unreleased]
### Added
- Stage 6.3: Salary grades, payroll and loans
  - Added branch-scoped salary grades with monthly/hourly bases, overtime multiplier/rate and minor-unit currency amounts
  - Added employee salary assignment windows, recurring allowances/deductions and configurable statutory rules
  - Added employee loans with principal, installment, balance and immutable repayment ledger on approved runs
  - Added deterministic payroll engine with working-day proration, attendance-based hourly pay, overtime, statutory items and rounding rules
  - Added payroll periods, draft regeneration, approval locking, payslips with line-level breakdown and adjustment/reversal workflow
  - Added payroll permissions and branch/permission isolation tests
  - Added `/app/payroll` workspace for setup, periods/runs, loans and adjustments

## [Unreleased]
### Added
- Stage 6.4: Payroll payment and reconciliation
  - Added approved-run payroll accrual linked to a unique incurred expense
  - Added partial payroll payment allocation with idempotency, canonical money movements and overpayment protection
  - Added run reconciliation checks comparing payslips, payments, cash movements and accruals
  - Added payroll export permission and restricted payslip CSV export
  - Added payment/reconciliation UI to the payroll workspace

### Stage 6.5
- Added operational tasks with lifecycle events, completion history, due dates, priorities and assignment
- Added queued in-app/email notification deliveries with preferences, delivery attempts, failures and read state
- Added task centre UI with notifications and preference management at `/app/tasks`

### Stage 6.6
- Added `HrPayrollAcceptanceTest` covering leave approval, loan deduction, payroll lock/correction, partial payment, access revocation, branch isolation, finance and audit regressions
- Added explicit production blockers for unverified statutory rates, tax filing and leave-pay effects
- Acceptance: PASS for testable criteria; BLOCKED for legal/tax assumptions; NOT RUN for browser runtime verification

### Stage 7.1 Acceptance Report (Vehicle fitment, OEM and catalogue search)
- Criteria (PASS/FAIL/BLOCKED/NOT RUN):
  - Implement vehicle make/model/year/engine fitment and OEM/cross-reference search: PASS (APIs + UI). Commands: `php8.4 vendor/bin/phpunit --filter CatalogueFitmentSearchTest` (passed). Outcome: 8 tests, 36 assertions passed.
  - Keep core product data reusable for other trades (fitment/OEM additive, no product semantic change): PASS. Files: `backend/app/Models/Product.php` (relations added), migrations `product_fitments`, `product_oem_refs`; no changes to `products` required fields/behavior beyond relations.
  - Define fitment data source/import formats; do not invent compatibility: PASS (documented in API requires `source`,`source_ref`,`format=json`; UI notes; results show source). BLOCKED: no external catalogue source provided (imports are manual JSON).
  - Exact SKU/barcode match must remain reliable: PASS (search prioritizes exact SKU/barcode). Test: `test_exact_sku_match_returns_result`, `test_exact_barcode_match_returns_result`.
  - Add typo-tolerant search only if testable; cover false-positive/no-result cases: PASS (deterministic Levenshtein<=1 for tokens length>=5 in fallback paths). Tests: `test_typo_tolerance_is_testable_and_does_not_create_false_positives`, `test_no_result_case_returns_empty_list`.
  - Branch isolation: PASS. Test: `test_branch_isolation_for_catalogue_search_and_imports`.

- Exact commands run (from repo root / relevant dirs):
  - Backend migrate: `cd backend && php8.4 artisan migrate --force` (ran; created `2026_11_20_211924_create_product_fitments_table`, `..._create_product_oem_refs_table`, `..._create_catalogue_import_logs_table`). Outcome: OK.
  - Pint (dirty): `cd backend && vendor/bin/pint --dirty --format agent` (fixed files listed above). Outcome: fixed.
  - Focused tests: `cd backend && php8.4 vendor/bin/phpunit --filter CatalogueFitmentSearchTest` -> PASS (8 tests, 36 assertions).
  - Full backend suite (MariaDB): `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit` -> PASS (263 tests, 1359 assertions).
  - Frontend: `cd frontend && npm run typecheck` -> PASS; `npm run lint` -> PASS (2 pre-existing warnings in Theme/Locale contexts); `npm run build` -> PASS.

- Files changed (key): backend `app/Support/Permissions.php`, `app/Support/Roles.php`, `routes/api.php`, new models/controllers/service (`ProductFitment`, `ProductOemRef`, `CatalogueImportLog`, `CatalogueService`, `CatalogueSearchController`, `CatalogueImportController`), migrations (3 new), tests (`tests/Feature/Catalogue/CatalogueFitmentSearchTest.php`), frontend `src/router.tsx`, `src/components/layout/AppShell.tsx`, new `src/pages/app/CatalogueSearchPage.tsx`, docs (`README.md`, `docs/CHANGELOG.md`, `docs/DEVELOPMENT.md`, `docs/DECISIONS.md`, `docs/BUSINESS_RULES.md`, `docs/REQUIREMENTS_TRACEABILITY.md`).

- Migrations: `2026_11_20_211924_create_product_fitments_table.php`, `2026_11_20_211925_create_product_oem_refs_table.php`, `2026_11_20_211926_create_catalogue_import_logs_table.php` (all applied via `migrate --force`).

- Assumptions: imports are JSON and provided by trusted staff; `verified` defaults to `declared`; no external catalogue API integrated (manual import). Typo tolerance is intentionally narrow to reduce false positives.

- Risks/blockers: no real catalogue source data supplied (BLOCKED for “do not invent compatibility” operationally beyond schema/process). Browser runtime verification NOT RUN (per constraints). Large frontend bundle warning remains (pre-existing/non-blocking).

- Rollback notes: revert code changes and drop tables in reverse order (`catalogue_import_logs`, `product_oem_refs`, `product_fitments`) if rolling back Stage 7.1; remove added permissions/route/UI entries. Remove migrations only if not yet deployed to shared envs (prefer forward fixes).

### Stage 7.2 Acceptance Report (Service catalogue and job cards)
- Criteria (PASS/FAIL/BLOCKED/NOT RUN):
  - Implement job state machine with customer/vehicle, complaint/diagnosis, technician, labour, parts, customer approval, completion and invoice links: PASS. Tests: `test_job_state_transitions_from_estimate_to_approval_to_progress_to_completion`, `test_labour_lines_are_recorded_and_total_is_calculated`, `test_customer_approval_is_required_before_start`, `test_no_invoice_is_generated_automatically`.
  - Parts consumption posts through the canonical stock ledger exactly once: PASS. Test: `test_parts_consumption_posts_exactly_one_canonical_movement_and_is_idempotent_on_retry`; one `job_consumption` movement with `source=job` per part line, replay returns the original line without another movement.
  - Cancelling/reopening jobs must not silently reverse stock or invoice: PASS. Test: `test_cancel_and_reopen_do_not_reverse_stock_or_invoice`; no reversal movements, stock balance unchanged, existing invoice link preserved.
  - Warranty references only scoped; scope as NOT RUN/unsupported unless explicitly needed: NOT RUN (unsupported in this stage).
  - Branch tenancy and permissions: PASS. New permissions `branch.services.view/manage`, `branch.jobs.view/manage`; tests `test_branch_isolation_for_jobs_services_and_invoices`, `test_unauthorized_transition_rejection`.
  - Frontend `/app/jobs` list/create/detail, state actions, labour/parts entry, estimate/approval/completion: PASS (typecheck/lint/build); browser runtime verification NOT RUN.
- Exact commands run (from repo root / relevant dirs):
  - `cd backend && composer show --direct`: Laravel `v13.39.0`, PHPUnit `12.5.42`, Pint `v1.32.1`.
  - `cd backend && vendor/bin/pint --dirty --format agent`: PASS (fixed, then passed).
  - `cd backend && php8.4 artisan migrate --force`: first run FAILED at `2026_11_20_213403_create_jobs_table` with `SQLSTATE[42S01] ... Table 'jobs' already exists` in `torqueerp_test` (Laravel queue table). Domain model/table renamed to `ServiceJob`/`service_jobs`; rerun PASS: `2026_11_20_213403_create_service_jobs_table`, `..._create_job_labour_lines_table`, `..._create_job_part_lines_table` DONE.
  - `cd backend && php8.4 vendor/bin/phpunit --filter JobCardTest`: PASS (8 tests, 101 assertions).
  - `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit`: PASS (271 tests, 1460 assertions).
  - `cd frontend && npm run typecheck`: PASS; `npm run lint`: PASS (2 pre-existing react-refresh warnings in Theme/Locale contexts); `npm run build`: PASS (pre-existing large-chunk warning).
  - Browser runtime verification: NOT RUN (per constraints).
- Files changed (key): backend `app/Support/Permissions.php`, `app/Support/Roles.php`, `routes/api.php`, `app/Support/JobService.php`, `app/Support/StockLedger.php`, `app/Models/StockMovement.php`, new models `WorkshopService`, `ServiceJob`, `JobLabourLine`, `JobPartLine`, new controllers `Api/WorkshopServiceController`, `Api/JobController`, 8 form requests, 4 migrations, `tests/Feature/Workshop/JobCardTest.php`; frontend new `src/lib/jobs.ts`, `src/pages/app/JobsPage.tsx`, `src/router.tsx`, `src/components/layout/AppShell.tsx`; docs updates in the six required files.
- Migrations: `2026_11_20_213402_create_workshop_services_table.php`, `2026_11_20_213403_create_service_jobs_table.php`, `2026_11_20_213404_create_job_labour_lines_table.php`, `2026_11_20_213405_create_job_part_lines_table.php`. The `service_jobs` name avoids collision with Laravel's queue `jobs` table.
- Assumptions: invoice links point to an existing branch `Sale`; no invoice is generated automatically. Labour/parts can only be added in `draft`. Estimate snapshots the line totals. `start` needs approval and technician; `complete` needs diagnosis. `Idempotency-Key` is required for safe part retries; replay with the same key and payload returns the original line, a different payload returns 409.
- Risks/blockers: no external customer approval signature/document capture in this stage (reference/channel only); warranty is unsupported/NOT RUN; browser runtime verification NOT RUN; Laravel queue `jobs` table made the domain table rename necessary.
- Rollback notes: revert code/routes/permissions/UI, then drop tables in reverse order (`job_part_lines`, `job_labour_lines`, `service_jobs`, `workshop_services`) if rolling back Stage 7.2. Do not drop the framework `jobs` queue table. Remove migrations only if not deployed to shared environments (prefer forward fixes).

### Stage 7.3 Acceptance Report (Stock transfers and stocktakes)
- Criteria (PASS/FAIL/BLOCKED/NOT RUN):
  - Transfer state machine with draft, dispatched, in_transit, received, cancelled and explicit cross-branch destination selection: PASS. Source stock moves once at dispatch; destination stock moves once at receipt; in-transit is an explicit status change.
  - Cross-branch authorization: PASS. Both source and destination require active membership plus `branch.transfers.manage`; destination must be active. Tests: `test_dispatch_moves_source_stock_and_receipt_moves_destination_stock`, `test_cross_branch_authorization_is_required`, `test_stock_transfer_routes_are_branch_scoped`.
  - Duplicate receipt and cancellation behavior: PASS. Duplicate receive returns 409 without a second destination movement; cancellation before receipt posts one equal-and-opposite source movement only. Tests: `test_duplicate_receive_is_rejected`, `test_cancellation_reverses_only_the_source_dispatch`, `test_draft_cancellation_posts_no_stock_movement`.
  - No invented unit conversion: PASS. Transfers require explicit source and destination product/base unit IDs and only post base units. Operational base-unit equivalence is an assumption, documented as BLOCKED until master-data mapping is confirmed.
  - Stocktake count snapshot, review and approval: PASS. Expected quantity is locked from `stock_levels`; nonzero variance requires a reason; approval posts exactly one canonical movement per nonzero variance line. Tests: `test_approval_posts_one_movement_per_variance_line_and_rejects_duplicate_approval`, `test_variance_requires_reason`, `test_stocktake_permissions_and_branch_scope`.
  - Permissions and branch tenancy: PASS. New permissions are `branch.transfers.view/manage` and `branch.stocktakes.view/manage/approve`; routes are branch-scoped and default-deny.
  - Frontend `/app/transfers` list/create/detail/actions for transfers and stocktakes: PASS for implementation and static checks; browser runtime verification NOT RUN.
- Exact commands run (from repo root / relevant dirs):
  - `cd backend && composer show laravel/framework --no-ansi`: Laravel `v13.39.0`.
  - `cd backend && composer show phpunit/phpunit --no-ansi`: PHPUnit `12.5.42`.
  - `cd backend && composer show laravel/pint --no-ansi`: Pint `v1.32.1`. Composer emitted PHP 8.4 deprecation notices from its own packages, but version output was captured.
  - `cd backend && vendor/bin/pint --dirty --format agent`: PASS.
  - `cd backend && php8.4 artisan migrate --force`: first attempt FAILED with `Identifier name 'stock_transfers_destination_product_id_destination_branch_id_foreign' is too long`; second attempt FAILED with `near 'st_source_branch_fk) not null` because `foreignUlid` length was mistaken for a constraint name. Final migration with short `constrained(..., indexName)` values PASSED: `2026_11_20_220050_create_stock_transfers_table`, `..._create_stocktakes_table`, `..._create_stocktake_lines_table` DONE.
  - `cd backend && php8.4 vendor/bin/phpunit tests/Feature/Inventory/StockTransferTest.php tests/Feature/Inventory/StocktakeTest.php --colors=never`: PASS (9 tests, 83 assertions).
  - `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit --colors=never`: PASS (280 tests, 1543 assertions).
  - `cd frontend && npm run typecheck`: PASS.
  - `cd frontend && npm run lint`: PASS with 2 pre-existing `react-refresh/only-export-components` warnings in Theme/Locale contexts; 0 errors.
  - `cd frontend && npm run build`: PASS with the existing >500 kB bundle warning.
  - Browser runtime verification: NOT RUN (per constraints).
- Files changed (key): backend `app/Support/Permissions.php`, `app/Support/Roles.php`, `routes/api.php`, `app/Support/StockLedger.php`, new `StockTransferService` and `StocktakeService`, new models `StockTransfer`, `Stocktake`, `StocktakeLine`, `Branch` relations, `StockMovement` constants, two form requests, two controllers, three migrations, and tests `tests/Feature/Inventory/StockTransferTest.php` / `StocktakeTest.php`; frontend new `src/lib/transfers.ts`, `src/pages/app/TransfersPage.tsx`, updated `src/router.tsx` and `src/components/layout/AppShell.tsx`; docs updates in the six required files.
- Migrations: `2026_11_20_220050_create_stock_transfers_table.php`, `2026_11_20_220051_create_stocktakes_table.php`, `2026_11_20_220052_create_stocktake_lines_table.php` (all applied via `migrate --force`).
- Assumptions: source and destination base units are treated as compatible; no conversion factor is invented. Stocktakes count on-hand balance only; reserved/hold stock is not separately counted in this stage. Approval reference is optional metadata. Transfer dispatch/cancellation/receipt use deterministic internal idempotency keys rather than a client header.
- Risks/blockers: operational equivalence of cross-branch base units is BLOCKED until master data is confirmed; browser runtime verification NOT RUN; pre-existing frontend lint warnings and large-chunk warning remain non-blocking.
- Rollback notes: revert code/routes/permissions/UI, then drop tables in reverse order (`stocktake_lines`, `stocktakes`, `stock_transfers`) if rolling back Stage 7.3. Ledger rows created by Stage 7.3 remain in `stock_movements`; do not drop or rewrite that table. Remove migrations only if not deployed to shared environments (prefer forward fixes).

### Stage 7.4 Acceptance Report (Loyalty and win-back)
- Criteria (PASS/FAIL/BLOCKED/NOT RUN):
  - Qualification from documented sales/returns: PASS. Non-void `status=posted` sales are ingested; linked `sale_returns` reduce the net qualifying amount; voided sales are excluded. Tests: `test_qualification_uses_committed_sales_and_configurable_inactivity_window`, `test_voided_sales_are_excluded_and_documented_returns_reduce_qualification`.
  - Idempotency for duplicate sales/imports and explicit recalculation: PASS. Events are unique by branch + `event_key` and branch + business dedupe hash; replay returns the original event, same key with different facts returns 409, repeated recalculation is stable. Test: `test_duplicate_sale_events_and_recalculation_do_not_double_count`.
  - Configurable per-branch inactivity window: PASS. Stored in `loyalty_configs`, exposed through `GET/PUT /api/branches/{branch}/loyalty/config`, with explicit defaults in `config/loyalty.php`. Test: inactivity window changed from 30 to 365 days changes `is_inactive` on recalculation.
  - Stacking with manual discounts and floor price: PASS. Loyalty percent + manual percent are combined, capped at 100, passed through the existing `PriceCalculator`, and the net price is clamped to the floor. Test: floor-price case returns `net_minor=1900` with `floor_price_applied=true`.
  - Approval threshold: PASS. Combined discount above the configured threshold, or one that breaches the floor, requires `branch.loyalty.approve` and a reason; missing permission/reason returns 422 and approval is audited via `price.override.approved`. Test: `test_stacking_respects_floor_price_and_requires_authorized_approval` (includes a below-threshold floor-breach rejection).
  - Privacy and marketing consent: PASS for gating, recording and stub. Candidates require explicit `marketing_consent_at`, supported `marketing_channel`, and `marketing_channel_active`; consent is settable via `PATCH /api/branches/{branch}/customers/{customer}` and audited; invalid channels are rejected. Export is redacted with `delivery_status=NOT_RUN`, `channel_integration=not_configured`. No notification/message row is created. Tests: `test_winback_requires_consent_and_active_channel_and_export_does_not_send`, `test_marketing_consent_can_be_recorded_through_customer_api`.
  - Actual automated win-back delivery: NOT RUN / BLOCKED. No channel integration exists and no message transport is configured; only the export/queue stub is implemented.
  - Branch isolation and permissions: PASS. New permissions are `branch.loyalty.view`, `branch.loyalty.manage`, `branch.loyalty.approve`; cross-branch reads are forbidden and manage actions require the manage permission. Test: `test_loyalty_data_and_permissions_are_branch_scoped`.
  - Frontend `/app/loyalty` with qualification list, config, win-back candidates and recalculation action: PASS for implementation and static checks; browser runtime verification NOT RUN.
  - Documentation and acceptance report: PASS (six required docs updated; no new doc files created).
- Exact commands run (from repo root / relevant dirs):
  - `cd backend && composer show --direct`: Laravel `v13.39.0`, PHPUnit `12.5.42`, Pint `v1.32.1`; PHP `8.4.27`.
  - `cd backend && php8.4 artisan migrate --force`: PASS. Applied `2026_11_20_222410_create_loyalty_configs_table`, `2026_11_20_222411_create_loyalty_sale_events_table`, `2026_11_20_222412_create_loyalty_qualifications_table`, `2026_11_20_222413_add_marketing_consent_to_customers_table`. Re-run: `Nothing to migrate`.
  - `cd backend && vendor/bin/pint --dirty --format agent`: first run FIXED 2 new files (`LoyaltyQualificationService.php`, `LoyaltyTest.php`); re-run PASS with no changes.
  - `cd backend && php8.4 vendor/bin/phpunit tests/Feature/Loyalty/LoyaltyTest.php --colors=never`: PASS (7 tests, 91 assertions).
  - `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit --colors=never`: PASS (287 tests, 1634 assertions; baseline before this stage was 280 tests / 1543 assertions).
  - `cd frontend && npm run typecheck`: PASS.
  - `cd frontend && npm run lint`: PASS with 2 pre-existing `react-refresh/only-export-components` warnings in Theme/Locale contexts; 0 errors.
  - `cd frontend && npm run build`: PASS (existing >500 kB bundle warning).
  - Browser runtime verification: NOT RUN (per constraints).
- Files changed (key): backend `app/Support/Permissions.php`, `app/Support/Roles.php`, `routes/api.php`, `config/loyalty.php`, new `app/Models/LoyaltyConfig.php`, `app/Models/LoyaltySaleEvent.php`, `app/Models/LoyaltyQualification.php`, updated `app/Models/Branch.php`, `app/Models/Customer.php`, `app/Http/Controllers/Api/CustomerController.php` and `app/Http/Requests/UpdateCustomerRequest.php`, new `app/Support/LoyaltyConfigService.php`, `app/Support/LoyaltyQualificationService.php`, `app/Support/LoyaltyStackingService.php`, new `app/Http/Controllers/Api/LoyaltyController.php`, three new loyalty form requests, and `tests/Feature/Loyalty/LoyaltyTest.php`; frontend new `src/lib/loyalty.ts`, `src/pages/app/LoyaltyPage.tsx`, updated `src/router.tsx` and `src/components/layout/AppShell.tsx`; docs updates in the six required files.
- Migrations: `2026_11_20_222410_create_loyalty_configs_table.php`, `2026_11_20_222411_create_loyalty_sale_events_table.php`, `2026_11_20_222412_create_loyalty_qualifications_table.php`, `2026_11_20_222413_add_marketing_consent_to_customers_table.php`. All index/FK names are short (`lc_`, `lse_`, `lq_`, `cust_` prefixes) and below 64 characters.
- Assumptions: documented returns are `sale_returns` rows for an included sale other than `cancelled`/`rejected` (the workflow currently only creates `requested`); sale event dedupe uses branch + customer + source reference + amount + occurred-at second; amount thresholds are integer minor units; one config row per branch materialised on first access; domain models follow the repository convention of no dedicated factories/seeders; `config/loyalty.php` defaults apply only until a branch row is created and are visible through the config API.
- Risks/blockers: win-back delivery is BLOCKED until explicit consent proof and a channel integration are configured; customer consent fields are self-declared metadata, not a verified opt-in record; recipients are redacted in the export stub; no browser runtime verification; pre-existing frontend lint and large-chunk warnings remain non-blocking.
- Rollback notes: revert code, routes, permissions, config file, frontend page/navigation and docs, then drop tables in reverse order (`loyalty_qualifications`, `loyalty_sale_events`, `loyalty_configs`) and drop the added `customers` columns (`marketing_channel_active`, `marketing_channel`, `marketing_consent_at`) plus index `cust_marketing_consent_idx`. Do not drop or rewrite `sales`, `sale_returns`, `customers`, `stock_movements` or other ledger tables. Remove migrations only if they were never deployed to a shared environment; prefer forward fixes.

### Stage 7.5 Acceptance Report (Assets and maintenance)
- Criteria (PASS/FAIL/BLOCKED/NOT RUN):
  - Asset acquisition stores date, cost_minor, currency, location, custodian, status and source type/id, with an audited `asset.acquired` log: PASS. Test `test_acquisition_links_existing_purchase_without_creating_duplicate_expense`.
  - Link to an existing purchase/payment without a duplicate expense: PASS. `source_type` is limited to `expense`, `supplier_bill` or `money_movement`; the linked row must exist in the same branch and share the currency. The service stores the link only and creates no `Expense`, `SupplierBill`, `PaymentAccount` or `MoneyMovement`. Test `test_acquisition_links_existing_purchase_without_creating_duplicate_expense` asserts the expense count and money movement count are unchanged.
  - Duplicate acquisition link rejection: PASS. Service pre-check under lock returns 409; unique key `as_source_uniq` on (branch_id, source_type, source_id) is the database backstop. Test `test_duplicate_acquisition_link_is_rejected`.
  - Maintenance records store date, cost, provider, description and optional expense/payment link: PASS. Links are branch-checked; no new expense or money movement is posted. Tests `test_maintenance_is_recorded_with_optional_links_without_new_postings`.
  - Disposal is audited and returns an explicit financial effect summary (cost, accumulated estimate, proceeds, estimated gain/loss) with no automatic accounting entries: PASS. `asset.disposed` audit row contains before/after snapshots plus the full financial effect; repeated disposal returns 409. Test `test_disposal_is_audited_and_returns_estimated_financial_effect_without_posting`.
  - Depreciation estimate is non-posting and clearly labelled ESTIMATE / BLOCKED: PASS. Both estimate endpoints return `label=ESTIMATE`, `accounting_use=BLOCKED`, `policy_status=unconfirmed`, `posted=false` and `financial_movement_posted=false`; no ledger, expense or audit row is written. Test `test_depreciation_estimate_is_labelled_blocked_and_posts_nothing`.
  - Depreciation used for accounting, tax or ledger postings: BLOCKED until a documented policy and professional rules are confirmed. No useful life or residual default is asserted by the system; callers must supply their assumptions.
  - Branch isolation and permissions: PASS. New permissions are `branch.assets.view`, `branch.assets.manage` and `branch.assets.dispose`; routes are inside the branch-scoped group with `branch.context` and `branch.permission` middleware; cross-branch reads/links are rejected. Test `test_assets_are_branch_scoped_and_permission_gated`.
  - Frontend `/app/assets` with list/create/detail, maintenance form, disposal action and estimate display: PASS for implementation and static checks; browser runtime verification NOT RUN.
  - Documentation and acceptance report: PASS (six required docs updated; no new doc files created).
- Exact commands run (from repo root / relevant dirs):
  - `cd backend && composer show --direct`: Laravel `v13.39.0`, PHPUnit `12.5.42`, Pint `v1.32.1`; `php8.4 -v`: PHP `8.4.27`.
  - `cd backend && php8.4 artisan make:model Asset -m --no-interaction`, `php8.4 artisan make:model AssetMaintenance -m --no-interaction`, `php8.4 artisan make:controller Api/AssetController --no-interaction`, `php8.4 artisan make:class Support/AssetService --no-interaction`, `php8.4 artisan make:class Support/DepreciationEstimateService --no-interaction`, `php8.4 artisan make:test Assets/AssetManagementTest --phpunit --no-interaction`: all DONE.
  - `cd backend && vendor/bin/pint --dirty --format agent`: PASS (no changes); re-run after the test adjustment: PASS.
  - `cd backend && php8.4 vendor/bin/phpunit tests/Feature/Assets/AssetManagementTest.php --colors=never`: first run FAILED 1/6 (SQLite returned `disposal_date` as `2026-06-30 00:00:00`; the assertion was changed to compare the model date cast). Final run PASS: 6 tests / 71 assertions.
  - `cd backend && php8.4 artisan migrate --force`: PASS. Applied `2026_11_20_224853_create_assets_table` and `2026_11_20_224854_create_asset_maintenances_table` (MariaDB).
  - `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit --colors=never`: PASS (293 tests, 1705 assertions; Stage 7.4 baseline was 287 tests / 1634 assertions).
  - `cd frontend && npm run typecheck`: PASS. `cd frontend && npm run lint`: PASS with 2 pre-existing `react-refresh/only-export-components` warnings in Theme/Locale contexts, 0 errors. `cd frontend && npm run build`: PASS with the existing >500 kB chunk warning.
  - Browser runtime verification: NOT RUN (per constraints).
- Files changed (key): backend updated `app/Support/Permissions.php`, `app/Support/Roles.php`, `app/Models/Branch.php`, `routes/api.php`; new `app/Models/Asset.php`, `app/Models/AssetMaintenance.php`, `app/Support/AssetService.php`, `app/Support/DepreciationEstimateService.php`, `app/Http/Controllers/Api/AssetController.php`, `tests/Feature/Assets/AssetManagementTest.php`; new migrations listed below. Frontend new `src/lib/assets.ts`, `src/pages/app/AssetsPage.tsx`; updated `src/router.tsx`, `src/components/layout/AppShell.tsx`; docs updates in the six required files.
- Migrations: `2026_11_20_224853_create_assets_table.php`, `2026_11_20_224854_create_asset_maintenances_table.php`. Index/FK names use short `as_` and `amt_` prefixes and stay below 64 characters (`as_source_uniq`, `as_branch_status_idx`, `amt_asset_date_idx`, etc.).
- Assumptions: an asset link is informational — the purchase/payment/expense remains the financial record and no second expense is created. Cost and currency are supplied explicitly; currency must match the linked source, but cost need not equal the source amount (a source may cover multiple assets). Maintenance links are likewise informational. Depreciation uses caller-supplied straight-line useful life and residual values only; months are whole calendar months and accumulated estimate is capped at cost. Disposal accumulated estimate is supplied by the caller (normally from the ESTIMATE report), capped at cost; estimated gain/loss is proceeds minus (cost minus accumulated estimate). Proceeds are recorded as metadata only. Domain models follow the repository convention of no dedicated factories/seeders; frontend amounts are entered in integer minor units.
- Risks/blockers: accounting/tax use of depreciation and disposal effects is BLOCKED until policy/professional rules are confirmed; proceeds receipts and gain/loss are not posted to any ledger, so a professional must record them separately; no document attachment or disposal approval workflow is included in this stage; browser runtime verification NOT RUN; pre-existing frontend lint and bundle-size warnings remain non-blocking.
- Rollback notes: revert code, routes, permissions, frontend page/navigation and docs, then drop tables in reverse order (`asset_maintenances`, `assets`) if rolling back Stage 7.5. Do not drop or rewrite `expenses`, `supplier_bills`, `money_movements`, `payment_accounts` or `audit_logs`; audit rows written by this stage are append-only and would need a deliberate, separately authorised purge. Remove migrations only if they were never deployed to a shared environment; prefer forward fixes.

### Stage 7.6 Acceptance Report (Inventory intelligence and dashboard v2)
- Criteria (PASS/FAIL/BLOCKED/NOT RUN):
  - Reorder suggestions from explicit formulas/windows: PASS. `config/inventory_intelligence.php` defines `sales_window_days`, `lead_time_days` and `days_of_cover`; `DashboardMetricsService::reorder()` computes average daily sales from non-void posted `sale_items` over the window, reorder point = average x (lead time + cover), and suggestion = reorder point minus available (on hand - reserved), rounded up to whole base units. Known-fixture test `test_reorder_suggestions_use_configurable_windows_on_known_fixtures` asserts 40 units / 10 days = 4 per day, point 20, available 5, suggestion 15; widening the window to 20 days gives 2 per day, point 10, suggestion 5.
  - Stock ageing/dead-stock classification: PASS. Last movement comes from the canonical `stock_movements` ledger; slow/dead thresholds are configurable. Test `test_ageing_and_dead_stock_classification_use_last_movement_and_thresholds` asserts active (5 days), slow (45), dead (100), unknown/null classification without movement history, and no-stock buckets with stock values.
  - ABC analysis: PASS. Revenue contribution uses posted `sale_items.total_minor` over a configurable sales window; cumulative-share thresholds are inclusive. Test `test_abc_classification_uses_configurable_contribution_and_window` asserts 7000/2000/1000 minor units map to A/B/C at 70%/90% cumulative, excludes out-of-window sales, and reclassifies when the A threshold is lowered.
  - Cash forecast and targets with documented assumptions and warnings: PASS for forecast generation; BLOCKED for authoritative finance use. `FORECAST` label, `authoritative_finance_statement=false`, explicit assumption strings, null projections with `insufficient_cash_history` when cash events are missing, mixed-currency and future-as-of warnings, and an illustrative target (all unpaid bills plus incurred expenses with no paid date) are asserted in `test_cash_forecast_is_labelled_forecast_with_assumptions_and_known_commitments` and `test_insufficient_data_returns_warnings_and_null_or_zero_values`. Straight-line extrapolation, trailing-window averages and bills due inside the horizon are the only model; no seasonality, FX, growth or payment timing is invented.
  - Shared metrics endpoint, branch scope and permissions: PASS. `GET /api/branches/{branch}/dashboard/v2` sits inside `branch.context`; inventory analysis requires `branch.inventory.view`, finance metrics/cash forecast require `branch.finance.view`, and a user with neither gets 403. No new permission was needed. Test `test_dashboard_is_permission_aware_and_branch_scoped` asserts admin/storekeeper/accountant section differences, restricted denial, cross-branch denial and exclusion of branch B products from branch A results.
  - Charts accessible, permission-aware and derived from shared metrics: PASS for implementation and static checks. `IntelligenceCharts` renders accessible tables with captions and `scope` headers, `role="img"` bars with text labels, visible data-quality warnings, documented forecast assumptions and high-contrast text. Both `/app` and `/app/inventory-intelligence` consume the one shared endpoint. Browser runtime verification: NOT RUN (per constraints).
  - Tests and documentation: PASS. Six focused tests cover the formulas, known fixtures, warnings and permissions; the seven required docs were updated and no new doc files were created.
- Exact commands run (from repo root / relevant dirs):
  - `cd backend && php8.4 artisan make:class Support/DashboardMetricsService --no-interaction`, `php8.4 artisan make:controller Api/DashboardMetricsController --no-interaction`, `php8.4 artisan make:test Reports/DashboardV2Test --phpunit --no-interaction`: DONE.
  - `cd backend && php8.4 artisan migrate --force`: PASS, `Nothing to migrate` (no new migrations; this stage reads existing tables only).
  - `cd backend && vendor/bin/pint --dirty --format agent`: first run FIXED `app/Support/DashboardMetricsService.php` (imports/braces/style); re-run PASS with no changes.
  - `cd backend && php8.4 vendor/bin/phpunit tests/Feature/Reports/DashboardV2Test.php --colors=never`: final run PASS (6 tests, 96 assertions). Earlier intermediate runs failed on float JSON encoding and signed Carbon diffs; both were fixed in code/tests before the final pass.
  - `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit --colors=never`: PASS (299 tests, 1801 assertions; Stage 7.5 baseline was 293 tests / 1705 assertions).
  - `cd frontend && npm run typecheck`: PASS. `cd frontend && npm run lint`: PASS with 2 pre-existing `react-refresh/only-export-components` warnings in Theme/Locale contexts, 0 errors. `cd frontend && npm run build`: PASS with the existing >500 kB chunk warning.
  - Browser runtime verification: NOT RUN (per constraints).
- Files changed (key): backend new `config/inventory_intelligence.php`, `app/Support/DashboardMetricsService.php`, `app/Http/Controllers/Api/DashboardMetricsController.php`, `tests/Feature/Reports/DashboardV2Test.php`; updated `routes/api.php`. Frontend new `src/lib/dashboard.ts`, `src/components/ui/IntelligenceCharts.tsx`, `src/pages/app/InventoryIntelligencePage.tsx`; updated `src/pages/app/DashboardPage.tsx`, `src/router.tsx`, `src/components/layout/AppShell.tsx`. Docs updated in the seven required files.
- Migrations: none. Existing `sales`, `sale_items`, `stock_levels`, `stock_movements`, `money_movements`, `expenses`, `supplier_bills` and `products` tables are read only; no schema or permission migration was added.
- Assumptions: windows/thresholds default in config and are explicitly returned in the API response. Reorder uses `status=posted` non-void sales and excludes reserved stock from available quantity; products without sales in the window receive a warning and no invented suggestion. Ageing uses `created_at` of any canonical stock movement (including reversals), and zero/negative stock is `no_stock`. ABC uses line revenue (not cost or margin) and inclusive cumulative thresholds. Cash forecast averages sale `paid_minor` by sale date plus manual money-in excluding `receivable_collection`, and money-out excluding transfers; bills due inside the horizon are subtracted once, and the target is not a budget. Amounts stay in branch currency minor units with no FX conversion.
- Risks/blockers: authoritative finance use of the forecast/target remains BLOCKED; no seasonality, growth, FX, payment timing or supplier payment behaviour is modelled. Zero/negative stock and missing movement history are visible as warnings rather than guessed values. Browser runtime verification is NOT RUN; pre-existing frontend lint and large-chunk warnings remain non-blocking.
- Rollback notes: revert the new config/service/controller/route/test, the frontend lib/component/page plus router/nav/dashboard changes and the seven doc updates. No tables, permissions, ledger rows or migrations need removal. After rollback, `/app` returns to the previous `/finance/metrics` card query; clear any cached config (`php8.4 artisan config:clear`) if the environment cached the new config file.

### Stage 7.7 Acceptance Report (Phase 7 integration acceptance)
- Criteria (PASS/FAIL/BLOCKED/NOT RUN):
  - Job card with labour, parts, estimate, approval, progress, completion, manual invoice link: PASS. Test `test_job_labour_parts_invoice_and_retries_post_once_without_duplicate_stock_or_expense` asserts labour `2500`, parts `2000`, estimate/manual invoice `4500`, stock `10 → 8`, one job stock movement, and zero expenses/money movements. Duplicate part key replays the original line; same key with changed payload returns 409.
  - Transfer dispatch/receipt and stocktake approval: PASS. Test `test_transfer_receipt_and_stocktake_approval_move_stock_once_and_reject_duplicate_retries` asserts source `10 → 7 → 6`, destination `0 → 3`, one transfer-out, one transfer-in, one correction, and 409 for duplicate receipt/approval.
  - Loyalty qualification, stacking, floor/approval, duplicate event and explicit recalculation: PASS. Test `test_loyalty_discount_return_reports_and_dashboard_do_not_double_count_amounts` asserts checkout `2000` subtotal / `100` discount / `1900` paid, net qualifying `1900`, quote `7%`/`1860`, approved `13%` quote, floor quote `1900`, duplicate sale event replay, and no new sale/expense/money movement from quotes.
  - Return effect on loyalty and reports: PASS. The same test asserts return `950`, recalculated net qualifying `950` with `qualifies=false`, subsequent quote rejection, sales report revenue `1900`/discount `100`/cash `1900` and stock report quantity `8`/value `8000` (return does not restock or net the original sale report).
  - Dashboard v2 metrics from the same fixtures: PASS. Asserts finance revenue/money-in/profit basis `1900`, cash position `0`, ABC revenue `1900`, no needs-reorder products, active stock value `8000`, and labelled cash forecast `190` in / `0` out / `950` forecast / `0` target. Reorder suggestions follow the existing contract: non-need rows remain with `needs_reorder=false` and `suggested_order_units=0`.
  - Tenant boundaries and cross-branch references: PASS. Test `test_cross_branch_reads_actions_and_references_are_rejected` asserts other-branch job reads are 404/403 as scoped, other-branch service/customer/product/sale references are rejected, cross-branch report/dashboard reads are 403, and no cross-branch job part/stock movement/stocktake rows are created.
  - Idempotency and no double counting: PASS. Duplicate checkout, job part, loyalty event and return replays return the original record; conflicting same-key job part/loyalty event returns 409; duplicate transfer receipt and stocktake approval return 409. No duplicate stock movements, expenses, money movements, sales, discounts or qualifications were observed in the fixtures.
  - COGS amount verification: BLOCKED. The schema has no product/sale-item cost basis or cost layers; no COGS amount can be asserted without inventing data. The acceptance test instead asserts no duplicate expense/stock/discount postings and the limitation is documented.
  - Source and operational limitations: BLOCKED where external truth is required: no external catalogue/OEM source, no automated marketing channel, depreciation/cash forecast remain estimates, job invoices remain manual, cross-branch base-unit equivalence is unconfirmed, statutory/tax assumptions remain unconfirmed.
  - Browser runtime verification: NOT RUN (per constraints); frontend typecheck/lint/build also NOT RUN because no frontend files changed in Stage 7.7.
  - Documentation and acceptance report: PASS. Seven existing docs updated with no new doc files created.
- Exact commands run (from repo root / relevant dirs):
  - `cd backend && php8.4 artisan make:test --phpunit Acceptance/PhaseSevenIntegrationAcceptanceTest --no-interaction`: DONE.
  - `cd backend && php8.4 -l tests/Feature/Acceptance/PhaseSevenIntegrationAcceptanceTest.php`: PASS (no syntax errors).
  - `cd backend && php8.4 vendor/bin/phpunit tests/Feature/Acceptance/PhaseSevenIntegrationAcceptanceTest.php --colors=never`: first run FAILED 2/4 (reorder suggestions included a `plan_reorder` row and the cross-branch transfer actor lacked membership in the destination branch); assertions/actor corrected. Final run PASS: 4 tests, 180 assertions.
  - `cd backend && vendor/bin/pint --dirty --format agent`: FIXED import ordering and removed unused imports in the new test; focused suite re-run PASS after formatting.
  - `cd backend && php8.4 artisan migrate --force`: PASS, `Nothing to migrate` (no new migrations).
  - `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit --colors=never`: PASS (303 tests, 1981 assertions; Stage 7.6 baseline was 299 tests / 1801 assertions).
  - Frontend npm commands: NOT RUN (no frontend changes in Stage 7.7). Browser runtime verification: NOT RUN (per constraints).
- Files changed (key): backend `tests/Feature/Acceptance/PhaseSevenIntegrationAcceptanceTest.php`; docs `README.md`, `docs/CHANGELOG.md`, `docs/DEVELOPMENT.md`, `docs/DECISIONS.md`, `docs/BUSINESS_RULES.md`, `docs/METRIC_DICTIONARY.md`, `docs/REQUIREMENTS_TRACEABILITY.md`.
- Migrations: none. Existing `sales`, `sale_items`, `sale_returns`, `stock_levels`, `stock_movements`, `money_movements`, `expenses`, `products`, `service_jobs`, `stock_transfers`, `stocktakes`, `loyalty_sale_events` and `loyalty_qualifications` tables are read/written only through their existing services; no schema, permission or ledger migration was added.
- Assumptions: fixtures use frozen time `2026-06-30 12:00:00` and explicit config overrides for reorder/ABC/cash-forecast windows; minor-unit amounts are exact integer assertions. The reorder endpoint intentionally returns planning rows for products with history even when no reorder is needed, so acceptance asserts `needs_reorder_count=0` rather than an empty list. Cross-branch transfer setup uses a user with membership in both branches so service-level cross-branch reference validation can be reached.
- Risks/blockers: exact COGS remains BLOCKED until cost layers exist; external catalogue/OEM truth, automated win-back delivery, authoritative depreciation/forecast use, base-unit equivalence and statutory/tax assumptions remain unconfirmed. Returns are documented as requests affecting loyalty, not physical restock/refund events; sales reports are not netted for returns. No browser runtime verification was performed.
- Rollback notes: delete the new acceptance test and revert the seven doc updates. No production code, routes, permissions, frontend files, migrations or ledger data were added in Stage 7.7; do not alter existing ledger rows when rolling back.

### Stage 8.1 Acceptance Report (Security threat review and fixes)
- Criteria (PASS/FAIL/BLOCKED/NOT RUN):
  - Auth/session and CSRF: PASS. Stateful `login`/`logout` no longer bypass Laravel `validateCsrfTokens`; the SPA already calls `/sanctum/csrf-cookie` before login and sends `X-XSRF-TOKEN` through `frontend/src/lib/api.ts`. No custom CSRF/token logic was added. File changed: `backend/bootstrap/app.php`.
  - Inactive and revoked access: PASS. Tests `test_inactive_user_with_valid_token_is_rejected` and `test_revoked_token_is_rejected` assert 401 on `/api/user` after `is_active=false` and after token deletion. Existing `EnsureUserIsActive` and `AccessRevocation` behavior retained.
  - Authorization and tenant isolation: PASS. Existing pre-existing tests `test_platform_admin_without_membership_cannot_read_branch_data` and `test_branch_user_cannot_access_another_branch` retained and passing; new `test_user_without_branch_permission_cannot_update_product` asserts a cashier gets 403 and cannot mutate a product.
  - Mass assignment and unknown fields: PASS. `test_customer_update_ignores_branch_id_mass_assignment_attempt` sends a foreign `branch_id` and asserts the valid name changes while the branch stays unchanged. `test_product_update_ignores_unknown_price_field` sends `selling_price_minor` and asserts it is ignored while the allowed name changes. Representative endpoints use validated-request allow-lists.
  - XSS/escaping: PASS. `test_public_product_escapes_script_payloads` renders a public product with `<script>alert(1)</script>` and asserts escaped HTML, `JSON_HEX_*` Unicode escaping and no raw payload. Existing mail-escaping coverage in `ReservationNotificationTest` remains passing.
  - Exports and audit: PASS. `test_export_requires_platform_permission_and_records_audit` asserts a branch admin gets 403 with no `report_exports` row, then a platform admin export creates an export plus a `report.exported` audit row with actor and null branch. Existing audit immutability and redaction tests retained.
  - Webhooks and rate limits: PASS. `test_paynow_result_rejects_callback_with_invalid_signature` signs a callback with the wrong key, asserts 400/text response, zero payment writes and no outbound HTTP via `Http::preventStrayRequests()`. `test_login_is_rate_limited_after_repeated_failures` asserts throttling after five failed attempts. Existing `PaynowHash`/`hash_equals` and Laravel throttling were reused; no custom crypto was introduced.
  - SQL injection, uploads, jobs, errors and secrets review: PASS for reviewed controls. SQL uses Eloquent/query bindings and column allow-lists; uploads use Laravel file validation and bounded image processing; queued notifications use existing Laravel queue serialization; errors return generic API responses; `.env.example` contains blank placeholders only and no real secret was printed or committed.
  - Dependency advisories: BLOCKED. `composer audit --locked --format=json` reported `laravel/framework: 1 advisory(ies): CVE-2026-128083 low`, `symfony/http-foundation: 4 advisory(ies): CVE-2026-121619 unknown, CVE-2026-118514 unknown, NO-CVE unknown, NO-CVE unknown`, `symfony/http-kernel: 1 advisory(ies): CVE-2026-123339 unknown`, `symfony/routing: 1 advisory(ies): CVE-2026-118516 unknown`. Project rules require approval before dependency changes, so no versions were changed.
  - Browser runtime verification: NOT RUN (per constraints). Frontend typecheck/lint/build: NOT RUN because Stage 8.1 changed no frontend file.
  - Documentation and acceptance report: PASS. Six required docs updated; no new doc file created. Pre-existing modified/untracked files from earlier stages were left untouched except the files listed below.
- Exact commands run (from repo root / relevant dirs):
  - `cd backend && composer show --direct --no-interaction`: Laravel `v13.39.0`, Pint `v1.32.1`, Sanctum `v4.3.3`, PHPUnit `12.5.42`. Composer emitted PHP 8.4 deprecation notices from its own packages; version output was captured.
  - `cd backend && composer audit --locked --format=json`: PASS as a command, advisories listed above as residual risk/BLOCKED.
  - `cd backend && php -l bootstrap/app.php && php -l tests/Feature/Security/SecurityRegressionTest.php`: PASS, no syntax errors.
  - `cd backend && vendor/bin/pint --dirty --format agent`: first run FIXED `tests/Feature/Security/SecurityRegressionTest.php` (`no_unused_imports`); re-run PASS.
  - `cd backend && php8.4 vendor/bin/phpunit tests/Feature/Security/SecurityRegressionTest.php`: first run FAILED 1/14 (`test_public_product_escapes_script_payloads` got 404 because the test branch had no ACTIVE status); after setting `Branch::STATUS_ACTIVE` in the test, final run PASS: 14 tests, 54 assertions.
  - `cd backend && php8.4 artisan migrate --force`: PASS, `Nothing to migrate` (no schema change in Stage 8.1).
  - `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit`: PASS (312 tests, 2025 assertions; Stage 7.7 baseline was 303 tests / 1981 assertions).
  - Frontend npm commands: NOT RUN (no frontend changes). Browser runtime verification: NOT RUN (per constraints).
- Files changed (key): backend `bootstrap/app.php` and `tests/Feature/Security/SecurityRegressionTest.php`; docs `README.md`, `docs/CHANGELOG.md`, `docs/DEVELOPMENT.md`, `docs/DECISIONS.md`, `docs/BUSINESS_RULES.md`, `docs/REQUIREMENTS_TRACEABILITY.md`.
- Migrations: none. No table, column, permission, ledger row or seeder was added or changed.
- Assumptions: the SPA is the only stateful client for `/login`/`logout` and already performs the Sanctum CSRF-cookie handshake; API clients using Bearer tokens are unaffected because Sanctum token auth does not use web CSRF. Paynow outbound integration keys are configured outside version control. The public catalogue test sets the configured public branch to ACTIVE because the shared test helper does not set a branch status.
- Risks/blockers: upstream Composer advisories remain BLOCKED pending dependency-approval; browser runtime verification NOT RUN; no destructive production test was used; pre-existing frontend lint/bundle warnings were not rechecked because no frontend file changed. The CSRF change tightens stateful login/logout and should be verified against any non-SPA client before deployment if such a client exists.
- Verification: PASS for focused security suite and full MariaDB backend suite. No frontend or browser verification was performed. Rollback notes: restore the two `except` entries in `backend/bootstrap/app.php` (`'login'`, `'logout'`) and revert the test/doc changes. No migration, permission, data or frontend rollback is required.

## Stage 8.2 - Measured performance optimization
### Acceptance report
- PASS baseline/after measurement: `sales.index` 80.98 ms / 34 queries / 13,185 bytes before, 24.89 ms / 4 queries / 6,150 bytes after using a test-harness MariaDB fixture and query log.
- PASS frontend bundle measurement: production build 615.37 kB single chunk (gzip 167.23 kB) before, 243.25 kB main chunk (gzip 78.39 kB) after route-level splitting and lazy below-fold charts.
- PASS pagination/list payload: stock levels and stock movements cap `per_page` at 100 and return `meta`; sales list returns summary rows with `items_count` and omits heavy relations.
- PASS N+1 reduction: sales list eager-loads customer/receipt and uses `withCount`; bounded query count asserted in `PerformanceOptimizationTest`.
- PASS POS flow: bounded-query checkout test included in focused suite; no semantic change to totals.
- PASS supported compression hints: `backend/public/.htaccess` enables deflate/expires hints where Apache serves the backend.
- NOT RUN browser runtime performance profiling (Chrome DevTools MCP unavailable).
- BLOCKED authoritative production latency claims without production-like traffic; no PgBouncer/replica/CQRS/partitioning/sharding introduced without evidence.

### Commands and outcomes
- `vendor/bin/pint --dirty --format agent` PASS.
- `php8.4 artisan migrate --force` PASS (`Nothing to migrate`).
- `php8.4 vendor/bin/phpunit tests/Feature/Performance/PerformanceOptimizationTest.php` PASS: 5 tests / 66 assertions.
- `php8.4 vendor/bin/phpunit tests/Feature/Sales` PASS: 18 tests / 110 assertions.
- `DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit` PASS: 317 tests / 2091 assertions.
- `npm run typecheck`, `npm run lint`, `npm run build` PASS (2 pre-existing lint warnings; chunk-size warning reduced but still reported).

### Files
- Backend: `app/Http/Controllers/Api/SaleController.php`, `app/Http/Controllers/Api/ReservationController.php`, `app/Http/Controllers/Api/StockMovementController.php`, `public/.htaccess`, `tests/Feature/Performance/PerformanceOptimizationTest.php`.
- Frontend: `src/router.tsx`, `src/lib/sales.ts`, `src/pages/app/SalesHistoryPage.tsx`, `src/pages/app/DashboardPage.tsx`, `src/pages/app/InventoryIntelligencePage.tsx`, `src/pages/app/CustomersPage.tsx`, `src/pages/app/StockPage.tsx`, related chart components.
- No migrations added: measured bottleneck was query shape/payload/bundle size, not missing indexes.

## Stage 8.3 - SEO verification
### Acceptance report
- PASS unique titles/descriptions: all five indexable rendered pages (home, catalogue, category, product, published CMS page) expose one non-empty `<title>` and one non-empty `name="description"`; all descriptions are <= 160 characters and all five values are unique. The catalogue index/category variants previously shared one hard-coded description; bounded fix passes `$description` from `PublicCatalogueController` and renders it in both `description` and `og:description`.
- PASS canonicals: each indexable page has exactly one self-referencing `rel="canonical"` matching the requested public URL, including the published CMS page. Search and reservation pages intentionally carry `noindex` and are not in the sitemap.
- PASS sitemap: `/sitemap.xml` returns `application/xml; charset=UTF-8`, lists the home, shop, published product, active category and published CMS URLs exactly once, and contains no `/app`, `/platform`, `/api`, `/reservation`, query-string search or reservation-form URL.
- PASS robots: `/robots.txt` is environment-aware in `routes/web.php`. Non-production returns `User-agent: * / Disallow: /`; production returns `Allow: /`, disallows `/app`, `/platform`, `/login`, `/api` and advertises the sitemap. The stale tracked `backend/public/robots.txt` (Laravel default `Disallow:`) shadowed the route under Apache's `!-f` rewrite rule and was removed; a test asserts the physical file is absent.
- PASS production noindex config: the private React workspace `frontend/index.html` is always `noindex, nofollow`; its embedded `TMS_Homepage.html` mockup now carries the same noindex tag so it cannot be indexed as a duplicate of the server-rendered homepage. Search results, reservation request and token-based reservation status pages render `noindex`; public indexable pages render `index, follow`. Non-production is fully disallowed via robots.
- PASS clear H1/logical headings: every rendered public page has exactly one `<h1>` and it is the first heading. Published CMS pages built only from text/image sections previously had no H1; bounded fix renders the existing `cms-hero` section with the page title when no hero section is present, so no new CSS or redesign was introduced.
- PASS alt text: every rendered `<img>` has a non-empty `alt`; CMS save validation already requires descriptive alt for published images and product publication validation requires `public_image_alt`. Tests assert the product and CMS image alt values in rendered HTML.
- PASS accurate JSON-LD: every indexable page emits parseable JSON-LD with `https://schema.org` context and an accurate `@type` (AutoPartsStore, ItemList, Product, WebPage) plus a `url` matching the canonical. A real defect was found and fixed: Laravel 13 compiles the literal `@context` key in Blade as the new `@context` directive, which injected a PHP fragment as the JSON key in all four public views. Keys are now written as `'@'.'context'`. The catalogue ItemList also gained its `url`. Product JSON-LD price/currency matches rendered output and no private product note leaks into structured data.
- PASS OG image: all indexable pages declare `og:image` with `og:image:width=1200` and `og:image:height=630`; the referenced `backend/public/images/torqueerp-social.svg` declares `width="1200" height="630"`, matching the metadata.
- PASS favicon: all public pages emit `rel="icon"` pointing at `favicon.svg` and the backend asset exists. The frontend's pre-existing `/favicon.svg` reference was broken; the same SVG was added to `frontend/public/favicon.svg`.
- PASS language: public pages emit `lang="en-ZW"`; the private SPA shell emits `lang="en"`.
- PASS clean slugs and internal links: product, category and CMS slugs match `^[a-z0-9]+(?:-[a-z0-9]+)*$` (also enforced by request validation and the CMS slug route constraint). Rendered internal anchors contain no `/app`, `/platform`, `/api/` or `javascript:` target.
- PASS private URLs/data not exposed/indexed: negative tests confirm unpublished CMS pages return 404 and stay out of the sitemap, unreferenced media returns 404 on the public media route, other-branch public products are hidden from catalogue/product/sitemap and the internal product description never appears in public HTML or JSON-LD.
- PASS rendered HTML validation: DOMXPath structural checks on every indexable page assert one `html`, one `head`, one `body`, one `main`, one H1-first heading order and no duplicate `id` attributes. W3C Nu / external HTML validator: NOT RUN (no external validator invoked).
- NOT RUN browser runtime verification (per constraints); no live browser, Search Console or production crawler data was used. BLOCKED authoritative production indexation/coverage claims until a deployed production URL and crawler console access exist.
- Documentation and acceptance report: PASS. Six required docs updated; no new docs file created.

### Commands and outcomes
- `cd backend && vendor/bin/pint --dirty --format agent`: FIXED `tests/Feature/PublicSeoTest.php` (`new_with_parentheses`); re-run clean.
- `cd backend && php8.4 artisan migrate --force`: PASS, `Nothing to migrate` (no schema change in Stage 8.3).
- `cd backend && php8.4 vendor/bin/phpunit tests/Feature/PublicSeoTest.php`: first run FAILED 3 tests / 1 error (duplicate catalogue description, physical `public/robots.txt` shadowing the route, CMS page without H1, JSON-LD `@context` mangled by the Blade directive). After bounded fixes and adding `url` to the ItemList JSON-LD, final run PASS: 14 tests / 273 assertions.
- `cd backend && php8.4 vendor/bin/phpunit tests/Feature/PublicCatalogueTest.php tests/Feature/CmsContentTest.php tests/Feature/PublicReservationTest.php`: PASS, 20 tests / 111 assertions.
- `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit`: PASS, 327 tests / 2342 assertions (Stage 8.2 baseline was 317 tests / 2091 assertions; +10 SEO tests).
- `cd frontend && npm run typecheck`: PASS.
- `cd frontend && npm run lint`: PASS with 2 pre-existing warnings (`src/contexts/LocaleContext.tsx:36`, `src/contexts/ThemeContext.tsx:37`, `react-refresh/only-export-components`); 0 errors.
- `cd frontend && npm run build`: PASS, built in 5.59s; main chunk 337.20 kB / gzip 111.34 kB; no new errors.
- Frontend/browser runtime verification: NOT RUN (per constraints).

### Files
- Backend: `tests/Feature/PublicSeoTest.php` (extended from 4 to 14 tests), `app/Http/Controllers/PublicCatalogueController.php`, `resources/views/public/home.blade.php`, `resources/views/public/catalogue.blade.php`, `resources/views/public/product.blade.php`, `resources/views/public/page.blade.php`; removed stale `public/robots.txt`.
- Frontend: `public/TMS_Homepage.html` (noindex), `public/favicon.svg` (added).
- Docs: `README.md`, `docs/CHANGELOG.md`, `docs/DEVELOPMENT.md`, `docs/DECISIONS.md`, `docs/BUSINESS_RULES.md`, `docs/REQUIREMENTS_TRACEABILITY.md`.
- Migrations: none. No table, column, permission or seeder was added or changed.

### Assumptions, risks, rollback
- Assumptions: the Laravel server-rendered pages are the canonical public site and the React SPA is the private workspace; `APP_URL`/`FRONTEND_URL` are set to the real public origins in deployment. The fixture uses `https://tazivamotorspares.example` and `en-ZW`/`en` as the intended languages. Robots policy is driven by `app.env`, so production deployments must set `APP_ENV=production`.
- Risks: the `@context` fix is a Blade-compiler workaround for Laravel 13 and must be retained until the templates move JSON-LD generation out of Blade; the removed static robots file means deployments that serve `backend/public` without rewriting to `index.php` would return 404 for `/robots.txt` instead of the env-aware route. Duplicate-content risk from the SPA iframe mockup is mitigated by noindex but it remains a non-canonical artifact. Production indexation is unverified without crawler-console access. The 160-character description limit is a guideline, not a ranking rule, and is asserted as a bounded hygiene check.
- Verification: PASS for focused SEO/public/catalogue/CMS/reservation tests, full MariaDB suite, Pint, frontend typecheck/lint/build. Browser runtime and external HTML validation NOT RUN.
- Rollback notes: restore `backend/public/robots.txt` with the original `User-agent: * / Disallow:` content, revert `'@'.'context'` to `'@context'` in the four public Blade views (which restores the malformed JSON-LD defect), remove the CMS H1 fallback, revert the catalogue description pass-through, remove `frontend/public/favicon.svg` and the iframe noindex tag, and revert the test and six doc changes. No migration, permission, ledger data or database rollback is required.

## Stage 8.4 - Queues, external services and monitoring
### Acceptance report
- PASS bounded queue retries/backoff metadata: `SendTaskNotification` and `SendReservationNotification` keep `tries=3`, `timeout=30`, `backoff=[15,60]` and add `ShouldBeUnique` with `uniqueFor=300`; `uniqueId()` is the delivery ID, so duplicate dispatches collapse to one queued row.
- PASS duplicate job safety: a test dispatches the same task notification twice against the database queue and asserts exactly one `jobs` row with `SendTaskNotification` as `commandName`.
- PASS notification failure visibility: mail transport failure is logged as `notification.delivery.retry_scheduled`, the explicit `failed()` handler marks the `notification_deliveries` row `failed` with only the exception class, emits `notification.delivery.failed`, and leaves the task itself unchanged; no recipient or SMTP secret is logged.
- PASS webhook replay idempotency: signed Paynow callbacks produce a SHA-256 event key from reference, provider reference, amount, status and poll URL, stored in the new unique `webhook_events(provider, event_key)` row. Replays log `paynow.webhook.replay_ignored`, skip the provider poll and do not apply payment state twice; concurrent unique-key collisions are logged as `paynow.webhook.concurrent_replay_ignored` and ignored. Existing terminal-status guards remain in force.
- PASS webhook signature failure: invalid or disabled-provider callbacks are rejected before payment lookup or provider call with `paynow.webhook.rejected` structured log and `ConflictHttpException`; the webhook route still returns a generic 400 to the provider.
- PASS external API timeout/cost caps: initiate uses one HTTP attempt; status polling uses clamped connect timeout (1-10s, default 3), read timeout (1-30s, default 8) and at most three total attempts (initial plus two retries, `PAYNOW_POLL_RETRIES` clamped 0-2). Each callback polls at most once (`max_polls_per_callback=1`). Tests use `Http::fake` + `preventStrayRequests`; no real Paynow call or secret was used.
- PASS health endpoint payload: `GET /api/health` returns `status`, `checks.database.ok`, `checks.queue.ok/pending`, `failed_jobs.count` and `correlation_id`; it returns 200 when checks pass, 503 with `status=degraded` when the database is unavailable, echoes correlation headers and never exposes exception details.
- PASS structured redacted logs: `StructuredLogger` writes `event`, correlation/request IDs and recursively redacted context to the `observability` daily channel; configurable denylist covers passwords, tokens, keys, cookies, card/bank and PII fields, and `Throwable` values log only the class with a `[redacted]` message. Tests assert secrets and recipient data are absent from the log file.
- PASS correlation ID propagation: inbound `X-Correlation-Id` / `X-Request-Id` are validated (or generated), shared with Laravel logs, echoed on responses and copied into queue payloads. A simulated `JobProcessing` event restores the same IDs in the worker's `CorrelationContext`.
- PASS failed-job alert hook: `Queue::failing` logs `queue.job.failed` with job name, connection, queue and redacted exception class to `FAILED_JOB_LOG_CHANNEL` (default `observability`). A test fires `JobFailed` with a secret in the exception message and asserts the secret is absent.
- BLOCKED external pager delivery: no Slack/webhook channel or `LOG_SLACK_WEBHOOK_URL` is configured in this environment, so the hook proves logging only. Production paging requires setting `FAILED_JOB_LOG_CHANNEL=slack` and a real webhook; no real notification was sent.
- NOT APPLICABLE circuit breaker: only one external payment provider and mail notifications exist, already bounded by timeouts/retries and webhook dedupe, with existing route throttling. Persistent breaker state, a new dependency or shared cache infrastructure is not justified by this stage's evidence.
- NOT RUN frontend checks: Stage 8.4 changed no frontend file.
- NOT RUN browser runtime verification (per constraints); no browser, live Paynow account, SMTP server or deployed health dashboard was used.
- Documentation and acceptance report: PASS. Six required docs updated; no new docs file created.

### Commands and outcomes
- `cd backend && composer show --direct --no-interaction`: Laravel `v13.39.0`, PHPUnit `12.5.42`, Pint `v1.32.1`; PHP `8.4.27`.
- `cd backend && php8.4 artisan make:middleware AssignCorrelationId --no-interaction`, `php8.4 artisan make:class Support/CorrelationContext --no-interaction`, `php8.4 artisan make:class Support/StructuredLogger --no-interaction`, `php8.4 artisan make:model WebhookEvent -m --no-interaction`, `php8.4 artisan make:test Queues/QueueReliabilityTest --phpunit --no-interaction`, `php8.4 artisan make:test Externals/PaynowWebhookReliabilityTest --phpunit --no-interaction`, `php8.4 artisan make:test Externals/HealthCheckTest --phpunit --no-interaction`: DONE. The three generated tests were moved from `tests/Feature/Feature/...` into the existing `tests/Feature/Queues` / `tests/Feature/Externals` areas; no new base folder was added.
- `cd backend && vendor/bin/pint --dirty --format agent`: first run FIXED `tests/Feature/Externals/PaynowWebhookReliabilityTest.php` and `tests/Feature/PublicReservationTest.php` (import ordering and spacing). Re-run after fixes: PASS, clean.
- `cd backend && php8.4 artisan migrate --force --no-interaction`: PASS. Applied `2026_11_21_033105_create_webhook_events_table` (108.82ms).
- `cd backend && php8.4 vendor/bin/phpunit tests/Feature/Queues/QueueReliabilityTest.php --colors=never`: intermediate run FAILED (1 failure, 1 error): the failed-job log assertion matched the unescaped FQCN while JSON escaped backslashes, and `Queue::createPayloadUsing` had been cleared by Laravel 13's `InteractsWithTestCaseLifecycle::tearDown()` (`Queue::createPayloadUsing(null)`), so a static one-time registration guard lost the correlation hook. Fixed by asserting the job basename and registering the payload hook on every app boot. Final focused run PASS.
- `cd backend && php8.4 vendor/bin/phpunit tests/Feature/Externals/HealthCheckTest.php --colors=never`: intermediate run ERRORED with `Database connection [stage84_missing_connection] not configured.` because the degraded-DB test changed `database.default` without restoring it before `RefreshDatabase` teardown. Fixed with `try/finally` restoring the original connection; final focused run PASS.
- `cd backend && php8.4 vendor/bin/phpunit tests/Feature/Externals/PaynowWebhookReliabilityTest.php --colors=never`: intermediate runs FAILED on a helper returning a positional array instead of named `reservation`/`token`, then on `retry(2)` yielding only two total HTTP attempts. Fixed by returning the service array unchanged and configuring `1 + PAYNOW_POLL_RETRIES` total attempts; final focused run PASS.
- `cd backend && php8.4 vendor/bin/phpunit tests/Feature/Queues/QueueReliabilityTest.php tests/Feature/Externals/HealthCheckTest.php tests/Feature/Externals/PaynowWebhookReliabilityTest.php tests/Feature/PublicReservationTest.php tests/Feature/Tasks/TaskNotificationTest.php tests/Feature/ReservationNotificationTest.php --colors=never`: final PASS, 31 tests / 171 assertions. New Stage 8.4 tests account for 11 tests (6 queue, 2 health, 3 webhook); 20 adjacent reservation/task tests also passed.
- `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit --colors=never`: PASS, 338 tests / 2419 assertions (Stage 8.3 baseline was 327 tests / 2342 assertions; +11 tests, +77 assertions).
- `cd frontend && npm run typecheck`, `npm run lint`, `npm run build`: NOT RUN because no frontend file changed.
- Browser runtime verification: NOT RUN (per constraints).

### Files
- Backend new: `app/Http/Middleware/AssignCorrelationId.php`, `app/Support/CorrelationContext.php`, `app/Support/StructuredLogger.php`, `app/Models/WebhookEvent.php`, `database/migrations/2026_11_21_033105_create_webhook_events_table.php`, `tests/Feature/Queues/QueueReliabilityTest.php`, `tests/Feature/Externals/HealthCheckTest.php`, `tests/Feature/Externals/PaynowWebhookReliabilityTest.php`.
- Backend modified: `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `app/Jobs/SendTaskNotification.php`, `app/Jobs/SendReservationNotification.php`, `app/Support/PaynowGateway.php`, `app/Http/Controllers/Api/HealthController.php`, `config/observability.php`, `config/logging.php`, `config/paynow.php`, `.env.example`, `tests/Feature/PublicReservationTest.php`.
- Migrations: one (`2026_11_21_033105_create_webhook_events_table`) adding a unique `(provider, event_key)` index and a `(provider, received_at)` lookup index. No permission, role, ledger or seeder change.
- Docs: `README.md`, `docs/CHANGELOG.md`, `docs/DEVELOPMENT.md`, `docs/DECISIONS.md`, `docs/BUSINESS_RULES.md`, `docs/REQUIREMENTS_TRACEABILITY.md`. No new docs file created.

### Assumptions, risks, rollback
- Assumptions: the local database queue driver is representative for bounded job dispatch; Paynow is the only external payment provider; mail is the only notification transport; config values are injected via environment in deployment; `Http::fake` and `Mail::fake`/mocks are sufficient evidence without live credentials.
- Risks: the `webhook_events` table grows with every distinct callback and has no pruning job in this bounded stage (indexed, but retention must be operated separately); the redaction denylist must be extended when new sensitive fields are logged; failed-job logging is file-only until a pager channel is configured; the circuit-breaker decision should be revisited if another provider or shared breaker store is introduced; no live provider, SMTP, browser or pager delivery was exercised.
- Verification: PASS for Pint, migration, 31 focused/adjacent tests and the full MariaDB suite; BLOCKED for pager delivery; NOT RUN for browser runtime and frontend checks.
- Rollback notes: revert the new and modified backend files listed above and the six doc updates; remove the added `.env.example` variables or restore prior values. To remove the schema, drain in-flight callbacks and run `cd backend && php8.4 artisan migrate:rollback --step=1` (drops `webhook_events`), then clear cached config with `php8.4 artisan config:clear`. No permission, ledger row or other data migration needs rollback.

## Stage 8.5 - Backup, restore and retention drill
### Acceptance report
- PASS document scheduled backup: `docs/BACKUP_RESTORE.md` records the proposed nightly `30 2 * * *` cron, what is backed up (MySQL dump plus `backend/storage/app/private`), checksum/manifest requirements and failure alerting responsibility. The cron itself is NOT RUN/not installed; only the documentation criterion is claimed.
- BLOCKED off-site storage: no off-site target, transfer mechanism or lifecycle policy is configured in this environment. The target, TLS transfer, server-side encryption and retention ownership are assigned to the infrastructure owner but cannot be demonstrated.
- BLOCKED production encryption at rest/in transit: `backup.sh` supports `GPG_RECIPIENT` encryption with plaintext removal and manifest state, but the demonstrated artifact used `encryption_at_rest=none` on a local filesystem and the GPG path was NOT RUN. No encryption key or off-site transport was available to test.
- PASS retention documentation: 14 local daily sets, 90 off-site daily plus 12 monthly sets, keep the newest successful set and newest set with a PASS restore verification. Automated pruning is BLOCKED until an off-site target exists.
- BLOCKED RPO target / PASS proposal: a nightly full dump gives up to 24 hours RPO. The 15-minute target needs binary logging/PITR, which is not configured, so it is not claimed. RTO of 2 hours is documented as an unmeasured proposal; the synthetic drill completed in seconds.
- PASS restore responsibilities: infrastructure owner (schedule, storage, keys, retention, alerting), on-call engineer (restore execution and evidence), application owner (backup selection and sign-off), security owner (rotation and access review).
- PASS backup of a non-production database: `torqueerp_stage85_source_test` was created from the migrated `torqueerp_stage12_test` template and seeded with deterministic synthetic rows by `fixture.sql`. SQL dump and private-storage archive were created with a validated manifest.
- PASS restore into a separate non-production database: restored into `torqueerp_stage85_restore_test`. The source was read only; post-restore read-only queries on the source still showed 1 branch, 1 sale, 2 stock movements and 1 receipt.
- PASS record counts: 14 key tables matched the manifest exactly (69 migrations, 1 branch, 1 user, 1 product, 1 category, 1 unit, 1 customer, 1 stock level, 2 stock movements, 1 sale, 1 sale item, 1 payment, 1 receipt, 1 audit log).
- PASS key workflows via read-only queries: stock-ledger projection equals on-hand, no negative/over-reserved stock, every posted sale has a receipt, paid total equals payment sum, sale total equals item sum, stock movement idempotency unique, every product has a branch. All seven checks returned 0 violating rows.
- PASS repeatable read-only verification: `RESTORE_VERIFY_ONLY=1` re-ran artifact checksums, all counts and all workflow checks with the same PASS outcome and no writes.
- PASS never overwrite production: production-like names, shared test databases and source-as-target are refused. Negative commands produced `Refusing to restore into the source database 'torqueerp_stage85_source_test'.` and `Refusing database 'torqueerp_prod': only non-production torqueerp_*_test databases are allowed.`
- PASS backup not accepted until restore demonstrated: the restore and verification report are mandatory parts of this stage's evidence; no backup is marked accepted by creation alone.
- PASS secret rotation documentation: DB, off-site/S3, GPG, Paynow, notification credentials and the `APP_KEY` re-encryption gate are documented in `docs/BACKUP_RESTORE.md`. No real secret was rotated; that operation is NOT RUN.
- PASS documentation: `docs/BACKUP_RESTORE.md` was added because the acceptance explicitly requires documenting scheduled backup/restore, and the six required root docs were updated.
- NOT RUN frontend checks and browser runtime: no frontend file changed and browser verification is out of scope.
- FAIL criteria: none.

### Commands and outcomes
- `set -a; . backend/.env; set +a` — loaded DB host/port/user/password into the shell; secrets were never printed or written.
- `mysqldump --version` / `mysql --version` / `SELECT VERSION()` — `Ver 10.13 Distrib 5.7.24` client, `Ver 14.14 Distrib 5.7.24` client, server `8.4.11`; `tar 1.35`, `gzip 1.12`, `php8.4 8.4.27`, `gpg` and `sha256sum` available.
- `bash -n backend/scripts/backup/*.sh` — PASS; `php8.4 -l backend/scripts/backup/manifest.php` — PASS.
- `bash backend/scripts/backup/run_drill.sh` — first run FAILED fixture load with MySQL 1064 near `before, after` because both are reserved words; `fixture.sql` was changed to quote them with backticks. Second run BLOCKED at restore because the drill pre-created the empty target and `restore.sh` correctly refused to overwrite it; `run_drill.sh` was changed so restore owns target creation.
- Final run PASS: `fixture PASS source=torqueerp_stage85_source_test template=torqueerp_stage12_test`, `PASS manifest`, `BACKUP PASS`, `restore PASS target=torqueerp_stage85_restore_test source=torqueerp_stage85_source_test`, `storage_archive PASS integrity_only`, all 14 count checks PASS, all 7 workflow checks PASS, two `RESTORE VERIFICATION PASS` results (restore plus verify-only), `DRILL PASS`.
- Final manifest: `/tmp/opencode/tms-backup-drill/torqueerp_stage85_source_test-20261121T035551Z.manifest.json`; SQL artifact 16820 bytes SHA-256 `e46f22560597e10f86d2aba3fdb0f0ebf11c2034d370086e60eb8972dca1360a`; storage artifact 3018 bytes SHA-256 `2db88e685428561a9ab6b115cf45dc902092f69c82b4dcdc1fb1990c4535ac8d`.
- `php8.4 backend/scripts/backup/manifest.php validate <manifest>` — PASS manifest (twice, plus in verify-only).
- `RESTORE_TARGET_DB=torqueerp_stage85_source_test RESTORE_VERIFY_ONLY=1 bash backend/scripts/backup/restore.sh` — PASS (guard refused, no DDL).
- `RESTORE_TARGET_DB=torqueerp_prod RESTORE_VERIFY_ONLY=1 bash backend/scripts/backup/restore.sh` — PASS (guard refused, no DDL).
- `cd backend && vendor/bin/pint --dirty --format agent` — first run FIXED `scripts/backup/manifest.php` (`not_operator_with_successor_space`); re-run PASSED with no changes.
- `cd backend && php8.4 artisan migrate --force --no-interaction` — PASS, `Nothing to migrate`.
- `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit --colors=never` — PASS, 338 tests / 2419 assertions (same as Stage 8.4 baseline; no application code changed).
- Frontend `npm run typecheck/lint/build`: NOT RUN because no frontend file changed. Browser runtime verification: NOT RUN.

### Files changed
- New: `backend/scripts/backup/common.sh`, `backend/scripts/backup/manifest.php`, `backend/scripts/backup/backup.sh`, `backend/scripts/backup/restore.sh`, `backend/scripts/backup/run_drill.sh`, `backend/scripts/backup/fixture.sql`, `docs/BACKUP_RESTORE.md`.
- Updated: `README.md`, `docs/CHANGELOG.md`, `docs/DEVELOPMENT.md`, `docs/DECISIONS.md`, `docs/BUSINESS_RULES.md`, `docs/REQUIREMENTS_TRACEABILITY.md`.
- No application PHP, route, permission, configuration or frontend file was changed. No `.env` or `.env.example` credential was added.
- No focused PHPUnit test was added: acceptance evidence is the exact shell output, validated manifest and repeatable read-only verification described above.

### Migrations
- None. `php8.4 artisan migrate --force --no-interaction` reported `Nothing to migrate`. No table, column, index, permission, seeder or ledger row in the application schema was changed; fixture rows exist only in the disposable drill databases.

### Assumptions
- `torqueerp_stage12_test` is a migrated, empty-of-domain-data test database and is a safe schema template. The two drill database names are reserved for this exercise and never contain real data.
- The local MariaDB/MySQL-compatible server root account from `backend/.env` can create/drop its own test databases. Production credentials and production databases were not used.
- A gzip-compressed SQL dump plus private-storage archive is a sufficient logical backup for this stage. No binary logging, incremental backup or storage snapshot exists.
- The 5.7 client works against the 8.4 server for this dump; this must be rechecked after any tool upgrade.
- The synthetic fixture exercises catalogue, stock, customer, POS/payment/receipt and audit workflows; it does not cover all 69 migrations' tables with data.

### Known risks and blockers
- Off-site storage, encryption at rest/in transit for production, automated retention and backup-job alerting are BLOCKED until infrastructure is configured and a key is available.
- The 15-minute RPO target is BLOCKED without binary logging/PITR; the demonstrated achievable RPO is up to 24 hours and the 2-hour RTO is unmeasured at production scale.
- No production-sized restore, no GPG-encrypted restore, no real off-site transfer and no real secret rotation were exercised.
- Restoring into a fresh target is deliberate; `RESTORE_REPLACE=1` can drop an existing non-production target and must be used only with owner approval.
- The manifest does not include per-table row counts for every table, only the 14 key tables listed in the runbook.
- No browser runtime verification was performed.

### Verification steps
- Repeat the full drill with `set -a; . backend/.env; set +a; bash backend/scripts/backup/run_drill.sh` and confirm `DRILL PASS`.
- Independently validate the manifest with `php8.4 backend/scripts/backup/manifest.php validate <manifest>` and re-run `RESTORE_VERIFY_ONLY=1` against the restored target.
- Inspect `verify-torqueerp_stage85_restore_test-*.tsv` in the backup directory for per-check actual/expected/outcome rows.
- Run the backend suite with `DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit` after any change to the scripts or docs.

### Rollback and recovery notes
- Delete `backend/scripts/backup/` and revert the doc changes; no application code, schema, permission or configuration needs rollback.
- Drop the disposable test databases with `DROP DATABASE torqueerp_stage85_source_test; DROP DATABASE torqueerp_stage85_restore_test;` after retaining evidence. Never drop `torqueerp_test` or `torqueerp_stage12_test`.
- Remove `/tmp/opencode/tms-backup-drill` only after the manifest, artifacts and verification reports are no longer needed as stage evidence.
- If a restore verification fails, leave the target database in place for investigation, restore into a new target name and do not promote the failed target. Never repair production from an unverified backup.

## Stage 8.6 - Deployment and operations runbook
### Acceptance report
- PASS actual PHP/Node/host tooling: `php -v` = PHP 8.4.27 CLI, `node -v` = v22.17.1, `npm -v` = 10.9.2, `composer --version` = 2.7.1, `cd backend && php8.4 artisan --version` = Laravel Framework 13.39.0. The required root-level `php8.4 artisan --version` was run and FAILED with `Could not open input file: artisan`; the correct Artisan path is `backend/`.
- PASS actual web server observation: nginx 1.24.0 is active on port 80 with `root /var/www/html` and a commented PHP block; `curl http://127.0.0.1/` returned the default nginx page and `curl http://127.0.0.1/TMS_ERP/backend/public/` returned 404. XAMPP Apache 2.4.58 is installed, and `/opt/lampp/lampp status` reported Apache/MySQL not running. No TMS vhost was assumed.
- PASS actual database observation: `pdo_mysql` is present; `DB::connection()->getDriverName()` + server version + database name returned `mariadb 8.4.11 torqueerp_test`. `/opt/lampp/bin/mysql --version` is MariaDB client 10.4.32. No credential was printed.
- PASS local/dev deployment documentation: exact local commands for Composer, key generation (first setup only), migrations, cache clear, `artisan serve`, Vite, queue worker and scheduler are in `docs/DEPLOYMENT.md` and `docs/DEVELOPMENT.md`.
- PASS production checklist with placeholders: public/workspace origins, release dir, web root, web user, PHP-FPM, DB host/user/secret ref, TLS paths, queue/scheduler units and monitor/pager refs are explicitly `<placeholder>` values; no VPS/cPanel assumption was made.
- BLOCKED HTTPS: no `:443` listener exists and `curl -k https://127.0.0.1:8000/api/health` failed with `SSL_ERROR_SYSCALL` (HTTP code 000). Production certificate/domain/redirect cannot be verified here.
- PASS dependency commands: `composer validate --no-check-publish --no-interaction` = valid; `composer install --dry-run --no-interaction` = nothing to install; `composer install --dry-run --no-dev --no-interaction` = platform OK, 39 dev packages would be removed; `composer check-platform-reqs --no-interaction` = all success; `cd frontend && npm ci --dry-run --ignore-scripts` = PASS.
- PASS migrations: `cd backend && php8.4 artisan migrate --force --no-interaction` = `Nothing to migrate`; `migrate:status --no-interaction` listed migrations as Ran. No migration was added by this stage.
- PASS cache/config where safe: `php8.4 artisan optimize:clear --no-interaction` cleared config, cache, compiled, events, routes and views. Production `config:cache`/`optimize` commands are documented but NOT RUN here.
- PASS asset build documentation / NOT RUN execution: `npm run build` was not executed because no frontend file changed; last known PASS is Stage 8.3. Built `frontend/dist` exists with `index.html`, assets and favicon.
- PASS queue worker documentation / NOT RUN installation: exact bounded command `queue:work --queue=default --tries=3 --timeout=30 --sleep=3 --no-interaction` and drain command are recorded. `pgrep` found no TMS worker and `crontab -l` has only unrelated KairoCORE entries.
- PASS scheduler documentation / NOT RUN installation: `php8.4 artisan schedule:list --no-interaction` shows `reservations:expire` every minute with `withoutOverlapping()`. No TMS cron/unit is installed.
- PASS storage permission documentation / PARTIAL observation: `stat` showed all relevant storage/bootstrap-cache directories as `775 wayne:wayne`; production commands use `<web-user>:<web-group>` and avoid `777`.
- PASS environment secrets handling: `.env.example` and docs expose key names/blank placeholders only; no real password, key, token, webhook URL or private value was printed or added. Production secret store details are placeholders/BLOCKED.
- PASS health checks: `curl -fsS -D - http://127.0.0.1:8000/api/health` returned HTTP 200 with `status=ok`, database/queue OK, pending 0, failed_jobs 0 and correlation headers; `GET|HEAD api/health` route confirmed by `route:list`. `GET /api/health` does not verify scheduler/worker/TLS/storage.
- PASS logs documentation: `backend/storage/logs/laravel.log` (single) and `backend/storage/logs/observability-2026-11-21.log` (daily) were observed; `logging.php` defines the observability daily channel with default 14 files. Rotation/shipping is BLOCKED.
- PASS rollback and forward recovery documentation: section 5 of `docs/DEPLOYMENT.md` covers code/config/database/storage/frontend rollback, migration gates, worker draining and health rechecks. No production rollback was executed (NOT RUN).
- BLOCKED uptime/pager: no external monitor, pager webhook, TLS expiry check or scheduler heartbeat check is installed in this environment.
- PASS no production secrets in docs: all seven touched/new docs reviewed; no secret value included.
- FAIL criteria: none.

### Commands and outcomes
- `php -v`: PHP 8.4.27 (cli), NTS, Zend Engine v4.4.27, OPcache 8.4.27.
- `node -v`: `v22.17.1`; `npm -v`: `10.9.2`.
- `php8.4 artisan --version` (repo root, as requested): FAIL, `Could not open input file: artisan`; then `cd backend && php8.4 artisan --version`: `Laravel Framework 13.39.0`.
- `composer --version`: `2.7.1 2024-02-09 15:26:28` with PHP 8.4 deprecation notices from system Composer.
- `cd backend && php8.4 -m`: `pdo_mysql`, `pdo_sqlite`, `curl`, `dom`, `gd`, `intl`, `mbstring`, `openssl`, `zip`, `bcmath`, OPcache and other required extensions present.
- `cd backend && php8.4 artisan about --only=environment --json`: `environment=local`, `debug_mode=true`, `url=localhost:8000`, `timezone=UTC`, Laravel 13.39.0, PHP 8.4.27.
- `/opt/lampp/bin/apachectl -v`: Apache/2.4.58 (Unix); `/opt/lampp/lampp status`: XAMPP 8.2.12-0, Apache/MySQL/ProFTPD not running, some PID cleanup permission-denied.
- `nginx -v`: nginx/1.24.0 (Ubuntu); `systemctl is-active nginx`: `active`; enabled default vhost root `/var/www/html`; `curl http://127.0.0.1/` = 200 default page; `curl http://127.0.0.1/TMS_ERP/backend/public/` = 404.
- `ss -ltnp`: `:80` nginx, `0.0.0.0:8000` php8.4, `0.0.0.0:5173` node, `:3306` mysqld, no `:443`.
- `curl -sS -D - -o /dev/null http://127.0.0.1:8000/api/health`: HTTP 200; body `{"status":"ok","checks":{"database":{"ok":true},"queue":{"ok":true,"pending":0}},"failed_jobs":{"count":0},"correlation_id":"..."}` with correlation headers. UUIDs are per-request.
- `curl -k https://127.0.0.1:8000/api/health`: `SSL_ERROR_SYSCALL`, code `000`.
- `cd backend && php8.4 artisan tinker --execute 'echo DB::connection()->getDriverName()." ".DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION)." ".DB::connection()->getDatabaseName();'`: `mariadb 8.4.11 torqueerp_test`.
- `cd backend && composer validate --no-check-publish --no-interaction`: `./composer.json is valid`; `composer install --dry-run --no-interaction`: `Nothing to install, update or remove`; `composer install --dry-run --no-dev --no-interaction`: platform verified, 39 dev-package removals proposed; `composer check-platform-reqs`: all success.
- `cd frontend && npm ci --dry-run --ignore-scripts`: PASS, `added 47 packages in 1s`, no change made.
- `cd backend && php8.4 artisan migrate --force --no-interaction`: `Nothing to migrate`; `migrate:status --no-interaction`: Ran.
- `cd backend && php8.4 artisan route:list --path=api/health --no-interaction`: `GET|HEAD api/health .. Api\HealthController@index`.
- `cd backend && php8.4 artisan schedule:list --no-interaction`: `* * * * * php artisan reservations:expire .. Next Due: ...`.
- `cd backend && php8.4 artisan queue:failed --no-interaction`: `No failed jobs found.`
- `cd backend && php8.4 artisan optimize:clear --no-interaction`: config/cache/compiled/events/routes/views cleared.
- `stat -c '%a %U:%G %n' backend/storage ... frontend/dist`: all observed directories `775 wayne:wayne`.
- `ls -la backend/storage/logs`: `laravel.log` and `observability-2026-11-21.log` present; `frontend/dist` contains built static assets.
- `pgrep -af ...` and `crontab -l`: running nginx/php-fpm/mysqld, `artisan serve`, Vite; no TMS `queue:work`/`schedule:work`; only unrelated KairoCORE cron entries.
- `cd frontend && npm run typecheck`, `npm run lint`, `npm run build`: NOT RUN because no frontend file changed. Browser runtime: NOT RUN.

### Files changed
- New: `docs/DEPLOYMENT.md`.
- Updated: `README.md`, `docs/CHANGELOG.md`, `docs/DEVELOPMENT.md`, `docs/DECISIONS.md`, `docs/BUSINESS_RULES.md`, `docs/REQUIREMENTS_TRACEABILITY.md`.
- No application PHP, route, permission, configuration, seeder, frontend file, `.env` or `.env.example` secret value was changed.

### Migrations
- None. `php8.4 artisan migrate --force --no-interaction` reported `Nothing to migrate`. No schema, permission, ledger row or seeder was touched.

### Assumptions
- The inspected host is a development host; production domain, document root, web user, TLS paths, DB host/credentials, service units and monitor details are supplied by the infrastructure owner and remain placeholders.
- Laravel serves the indexable public pages from Blade; the React build is the private workspace. Production must not swap one for the other without a separate SEO/architecture decision.
- `APP_URL` is the canonical HTTPS public origin and `FRONTEND_URL` the workspace origin in production; local values are non-production.
- Existing Stage 8.5 backup/restore scripts and rotation guidance remain the backup authority for this runbook.

### Known risks and blockers
- HTTPS, production web-server configuration, queue-worker supervision, scheduler installation, log shipping, external uptime/pager and secret-store integration are BLOCKED until the real host is configured.
- `artisan serve` and Vite are not production servers; XAMPP is installed but not serving this project.
- No TMS queue worker or scheduler is installed; reservation expiry and notification delivery would not run automatically in this observed state.
- Production web user/group is unknown; incorrect permissions could cause runtime failures or expose private files. Use the placeholder procedure, not `777`.
- `laravel.log` uses the single channel and is not rotated by the application; production rotation must be configured.
- Upstream Composer advisories from Stage 8.1 remain BLOCKED; no dependency was changed here.
- No production deployment, rollback, secret rotation, TLS renewal, pager delivery or browser runtime verification was performed.

### Verification steps
- Re-run the host inventory, dependency dry-runs, `migrate:status`, `schedule:list`, `queue:failed`, `optimize:clear` and local health check exactly as listed above.
- On the real host, repeat with production URLs: `curl -fsS https://<public-host>/api/health`, TLS expiry check, web/queue/scheduler unit status, log tails and one business smoke test.
- Verify no secret was added by reviewing the seven docs and `.env.example`; real values must stay only in the ignored `.env`/secret store.

### Rollback and recovery notes
- Documentation-only change: delete `docs/DEPLOYMENT.md` and revert the six updated docs; no code, schema, permission, cache artifact or data rollback is required.
- For a real deployment, follow `docs/DEPLOYMENT.md` section 5: verify backup, stop/drain queue worker, switch to the previous immutable release, clear/rebuild config caches, restart worker/PHP-FPM, re-run health/log/smoke checks.
- Database rollback is gated on a verified backup and reversible migration after draining callbacks; do not roll back `2026_11_21_033105_create_webhook_events_table` while webhooks may be in flight.
- Forward recovery is fix root cause → restore verified backup if data changed → `php8.4 artisan migrate --force --no-interaction` → `migrate:status` → health/smoke checks.

## Stage 8.7 - Translations, accessibility and mobile
### Acceptance report
- BLOCKED translation completeness / owner-selected languages: no owner-approved language list exists in the repo. `frontend/src/i18n/index.ts` implements exactly two locales (`en`, `sn`) with 11 keys each, `LocaleContext.tsx` is never mounted (`frontend/src/main.tsx` has no `LocaleProvider`), and zero application components call `t()`. No screen is translated. A claim of six complete languages would be false; this stage does not make that claim.
- PASS honest language reporting: docs now state only the implemented, partial i18n foundation; public Blade pages still emit `lang="en-ZW"` and the SPA shell `lang="en"` from Stage 8.3.
- PASS form labels (static DOM source): 63 previously unlabelled controls in POS, tasks, payroll and HR records now have their `<Label>` wrapping the single `Input`/`select`; case-insensitive balanced scan reports 0 unlabelled `<label>/<Label>` elements. This is source-level verification, not an assistive-technology run.
- PASS error association (static): login errors have `role="alert"` + `id="login-error"` and both inputs use `aria-describedby`; ProductForm and ten page-level feedback banners now switch between `role="alert"` (error) and `role="status"`; shared success state announces via `role="status"`.
- PASS screen-reader names (static): `aria-pressed` added to payroll, HR and task tab buttons; 55 table header cells gained `scope="col"` (99 total, 0 missing); POS approval warning uses `role="alert"`.
- PASS focus (static): global `:focus-visible` outline using the existing `--ring` design token covers anchors, buttons, inputs, selects, textareas, summaries and programmatic focus targets; shared Button/Input ring classes are unchanged.
- PASS semantic headings (static): shared `CardTitle` is now `<h2>` with the correct ref type; state component headings are `<h2>` instead of skipping from `<h1>` to `<h3>`.
- PASS contrast-safe classes (static): success state, dashboard link and dashboard seed button use theme tokens (`text-foreground`, default `Button` primary) instead of low-contrast `text-green-600`/`text-slate-900`/`bg-emerald-600`; intelligence charts use `text-foreground`/`text-muted-foreground`, with the fixed white notice box kept as explicit dark text on white.
- PASS responsive tables (static): all five IntelligenceCharts tables are inside `overflow-x-auto` wrappers, matching the existing page tables.
- PASS POS touch targets (static): eight POS cart controls use `min-h-11` (44 px) for product/unit selects, quantity/discount/floor/tax inputs, Remove and Add line.
- PASS no new dependencies: no `package.json`, `package-lock.json`, Composer manifest or lockfile was changed.
- PASS no backend change: no PHP, route, config or migration was touched, so focused PHPUnit, Pint and the full MariaDB suite are NOT RUN for this stage.
- NOT RUN browser runtime, screen-reader and real-device verification: no browser/DevTools tooling, screen reader or POS device is available in this environment. Keyboard tab order, visible focus, announcements, zoom/reflow and 44 px touch comfort are unverified at runtime.
- PARTIAL/remaining gaps (reported, not claimed as fixed): raw tab labels (`tasks`, `notifications`, `preferences`); inline `text-green-600`/`text-emerald-*` success text in other banners; `grid-cols-2`/`grid-cols-3` detail blocks on very narrow screens; aria-live on shared feedback components; LocaleProvider/locale switcher and translated screens; Blade emails are untranslated.
- FAIL criteria: none (BLOCKED/NOT RUN items are reported above, not hidden).

### Commands and outcomes
- `cd frontend && npm run typecheck`: PASS, no output/errors.
- `cd frontend && npm run lint`: PASS with 0 errors and the 2 pre-existing `react-refresh/only-export-components` warnings in `src/contexts/LocaleContext.tsx:36` and `src/contexts/ThemeContext.tsx:37`.
- `cd frontend && npm run build`: PASS, 258 modules transformed, `dist/assets/index-De_ODqIN.js` 337.31 kB (111.38 kB gzip); no new chunk warning.
- Static audit (`python3` scans from `frontend/`): i18n `en` keys 11, `sn` keys 11, `t()` calls in application components 0, `LocaleProvider` mounted false; unlabelled `<label>/<Label>` elements 0; `<th>` elements 99, missing `scope` 0; POS `min-h-11` targets 8; focus-visible ring present true.
- `git status --short -- frontend/src frontend/tsconfig.app.tsbuildinfo frontend/dist README.md docs`: reviewed; prior-stage uncommitted work remains and was not reverted.
- `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage12_test php8.4 vendor/bin/phpunit`: NOT RUN (no backend change); last known PASS from Stage 8.5/8.4 was 338 tests / 2419 assertions.

### Files changed
- Shared UI/styles: `frontend/src/index.css`, `frontend/src/components/ui/card.tsx`, `frontend/src/components/ui/IntelligenceCharts.tsx`, `frontend/src/components/feedback/StateComponents.tsx`, `frontend/src/components/catalogue/ProductForm.tsx`.
- Labelled forms / announced state: `frontend/src/pages/app/PosPage.tsx`, `frontend/src/pages/app/TasksPage.tsx`, `frontend/src/pages/app/PayrollPage.tsx`, `frontend/src/pages/app/HrRecordsPage.tsx`.
- Error roles / contrast / table scopes: `frontend/src/pages/auth/LoginPage.tsx`, `frontend/src/pages/app/DashboardPage.tsx`, `frontend/src/pages/app/InventoryIntelligencePage.tsx`, `frontend/src/pages/app/UsersPage.tsx`, `frontend/src/pages/app/ProductsPage.tsx`, `frontend/src/pages/app/EmployeesPage.tsx`, `frontend/src/pages/app/CustomersPage.tsx`, `frontend/src/pages/app/StockPage.tsx`, `frontend/src/pages/app/LoyaltyPage.tsx`, `frontend/src/pages/app/ReceiptPage.tsx`, `frontend/src/pages/app/ReservationsPage.tsx`, `frontend/src/pages/app/SyncCenterPage.tsx`, `frontend/src/pages/app/CatalogueSearchPage.tsx`, `frontend/src/components/catalogue/CategoryManager.tsx`, `frontend/src/components/catalogue/ProductUnitsPanel.tsx`, `frontend/src/components/catalogue/UnitManager.tsx`.
- Docs: `README.md`, `docs/CHANGELOG.md`, `docs/DEVELOPMENT.md`, `docs/DECISIONS.md`, `docs/BUSINESS_RULES.md`, `docs/REQUIREMENTS_TRACEABILITY.md`. No new doc file created.

### Migrations
- None. No migration, schema, seeder, permission or configuration file was changed; no `artisan migrate` was needed.

### Assumptions
- Owner-selected languages are unknown because no product requirement, settings screen or configuration names them; en/sn in `i18n/index.ts` are treated as a partial technical foundation, not an approved language set.
- Static source inspection is the available verification method; it does not prove rendered contrast ratios, tab order or screen-reader output.
- Uncommitted files from Stages 4.x-8.6 were already present; this stage changed only the files listed above.

### Known risks and blockers
- Translation completeness is BLOCKED until the owner names the languages and approved copy; wiring more locales without approved translations would produce mixed-language screens.
- Browser runtime, screen-reader and real-device checks are NOT RUN, so the listed static fixes need a manual pass before accessibility sign-off.
- Wrapping labels around controls changes DOM structure; low risk of CSS layout drift in dense grids, most visible on narrow viewports.
- Global focus outline is a fallback; components that intentionally suppress `outline` and add a ring still rely on their existing ring utility.
- Upstream Composer advisories from Stage 8.1 remain BLOCKED; no dependency was changed here.

### Verification steps
- Re-run `cd frontend && npm run typecheck && npm run lint && npm run build` after any further edit.
- In a browser: Tab/Shift+Tab through login, POS, tasks, payroll, HR records and catalogue forms; confirm focus is visible, labels announce, errors are announced and no control is a keyboard trap.
- Inspect POS at 375 px width and on a touch device; confirm 44 px targets, horizontal table scrolling and no clipped controls.
- With the owner's language list and approved copy, extend `i18n/index.ts`, mount `LocaleProvider` in `main.tsx`, add a locale switcher and repeat this audit before claiming any language complete.

### Rollback and recovery notes
- Revert the listed frontend files to their pre-Stage-8.7 content; no database, migration, queue, cache or configuration rollback is required.
- If only the focus ring is undesirable, remove the `:focus-visible` rule from `frontend/src/index.css` and rebuild.
- If a wrapped label causes layout drift, restore `htmlFor`/`id` pairs on that control instead of removing the accessible name.
- Rebuild with `cd frontend && npm run build` after rollback so `frontend/dist` matches the source.

## Stage 8.8 - Release-candidate acceptance
### Overall status
**PARTIAL / BLOCKED for production release. NOT production-ready.** No executed criterion failed, but critical host/TLS, backup off-site/encryption/PITR, language-list, pager, legal/tax/payroll, browser-runtime and dependency-advisory criteria remain BLOCKED or NOT RUN. Release gates and owners are in the new `docs/RELEASE_CHECKLIST.md`.

### Criteria (PASS / FAIL / BLOCKED / NOT RUN)
- Clean setup and migration: PASS. A new non-production `torqueerp_stage88_acceptance_test` database was created; `migrate:fresh --force` ran 69 migrations (all DONE), `migrate:status` PASS, `SELECT COUNT(*) ... migrations` = 69. No shared/test-template or production database was migrated.
- Composer/npm install validity, no dependency changes: PASS. `composer validate` = `./composer.json is valid`; `composer install --dry-run` = `Nothing to install, update or remove`; `npm ci --dry-run --ignore-scripts` = `added 47 packages in 1s`. No manifest or lockfile was changed by this stage; pre-existing uncommitted dependency changes from earlier stages were left untouched.
- Frontend typecheck/lint/build: PASS. `npm run typecheck` exit 0; `npm run lint` 0 errors with the 2 pre-existing `react-refresh/only-export-components` warnings; `npm run build` exit 0, 258 modules, main chunk 337.31 kB (gzip 111.38 kB), built in 6.11 s.
- Full PHPUnit with MariaDB: PASS. `DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage88_acceptance_test php8.4 vendor/bin/phpunit --colors=never` = 338 tests / 2419 assertions / 159361 ms, exit 0.
- Focused tenant/security: PASS. Branch isolation, branch context, access control, branch user management and security regression suites = 44 tests / 136 assertions.
- Financial fixture reconciliation: PASS. `FinancialReconciliationTest` plus finance metrics, expenses/bills, money movements, receivables and report suites = 23 tests / 129 assertions; no double-counting regression.
- Public SEO: PASS. `PublicSeoTest` plus public catalogue, CMS content and public reservation suites = 34 tests / 385 assertions.
- Offline recovery (implemented): PASS. `SyncRecoveryTest` and `SyncTest` = 5 tests / 16 assertions covering retry/replay, invalid payload and expired session behavior. Offline availability limits remain documented in `docs/OFFLINE_WORKFLOW_CLASSIFICATION.md`.
- Performance: PASS for local measurements / BLOCKED for production claims. Stage 8.2 remains authoritative: `sales.index` 80.98→24.89 ms, 34→4 queries, 13,185→6,150 bytes; bundle 615.37→243.25 kB main. Stage 8.8 re-ran the bounded performance and sales suites (23 tests / 176 assertions) and re-measured the current build above. No production-like traffic test was performed.
- Backup restore verification: PASS for the existing Stage 8.5 artifacts. The final manifest validated, both artifact checksums/sizes passed, 14 record counts and 7 read-only workflow invariants passed in `RESTORE_VERIFY_ONLY=1` mode, ending with `RESTORE VERIFICATION PASS`. Off-site storage, GPG encryption, PITR, production-scale RTO and live scheduling remain BLOCKED.
- Mobile/accessibility static checks: PASS for source-level checks; NOT RUN at runtime. Read-only TSX scan reported 142 `htmlFor` labels, 75 labels wrapping a control, 0 unlabelled labels, 99 table headers with scope (0 missing), 8 POS `min-h-11` targets and a `focus-visible` rule. Browser, screen-reader, keyboard-walk and 375 px/touch-device checks remain NOT RUN.
- Translation completeness: BLOCKED. No owner-approved language list exists; `en`/`sn` have 11 keys each, `LocaleProvider` is unmounted and there are 0 `t()` calls in application components. No language is claimed complete.
- Production host/TLS/secrets: BLOCKED. Local `/api/health` returned HTTP 200, but the HTTPS probe returned `000` with `SSL_ERROR_SYSCALL`; no `:443`, production vhost, real origin or secret store was supplied.
- Queue/scheduler/uptime/pager: BLOCKED / NOT RUN. Bounded worker and scheduler commands are documented in `docs/DEPLOYMENT.md`; no TMS worker/cron, external monitor or Slack/webhook pager is configured. Failed-job paging remains file-only.
- Dependency advisories: BLOCKED. `composer audit --locked --format=json` exited 4 with laravel/framework 1, symfony/http-foundation 4, symfony/http-kernel 1 and symfony/routing 1 advisories. Dependency changes require approval, so none were made.
- Legal/tax/payroll and accounting assumptions: BLOCKED. Statutory rates, tax filing, leave-pay effects, depreciation policy, COGS cost layers and authoritative cash forecasts remain unconfirmed by the owner/professional advisers.
- Documentation, release checklist and rollback plan: PASS. New `docs/RELEASE_CHECKLIST.md` plus the seven required root docs were updated with measured evidence, gates, owners, verification steps and a rollback plan.
- FAIL criteria: none. BLOCKED/NOT RUN criteria are reported, not hidden.

### Exact commands and outcomes
All commands were run from `/opt/lampp/htdocs/TMS_ERP` unless a directory is shown. DB credentials were sourced from the ignored `backend/.env` via `set -a; . backend/.env; set +a` and were never printed.
- `cd backend && composer validate --no-check-publish --no-interaction`: `./composer.json is valid`, exit 0 (system Composer emitted PHP 8.4 deprecation notices).
- `cd backend && composer install --dry-run --no-interaction`: `Nothing to install, update or remove`, exit 0.
- `cd frontend && npm ci --dry-run --ignore-scripts`: `added 47 packages in 1s`, exit 0.
- `mysql -e "DROP DATABASE IF EXISTS torqueerp_stage88_acceptance_test; CREATE DATABASE torqueerp_stage88_acceptance_test ..."`: new empty non-production database.
- `cd backend && DB_DATABASE=torqueerp_stage88_acceptance_test php8.4 artisan migrate:fresh --force --no-interaction`: 69 migrations DONE; `migrate:status` PASS; `migrations` count 69.
- `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage88_acceptance_test php8.4 vendor/bin/phpunit --colors=never`: PASS, 338 tests / 2419 assertions / 159361 ms.
- `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage88_acceptance_test php8.4 vendor/bin/phpunit tests/Feature/BranchIsolationTest.php tests/Feature/Tenancy/BranchContextTest.php tests/Feature/AccessControl/BranchAccessTest.php tests/Feature/AccessControl/BranchUserManagementTest.php tests/Feature/Security/SecurityRegressionTest.php --colors=never`: PASS, 44 tests / 136 assertions.
- `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage88_acceptance_test php8.4 vendor/bin/phpunit tests/Feature/Acceptance/FinancialReconciliationTest.php tests/Feature/Finance/FinanceMetricsTest.php tests/Feature/Finance/ExpenseBillTest.php tests/Feature/Finance/MoneyMovementTest.php tests/Feature/Sales/ReceivableTest.php tests/Feature/Reports/ReportTest.php --colors=never`: PASS, 23 tests / 129 assertions.
- `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage88_acceptance_test php8.4 vendor/bin/phpunit tests/Feature/PublicSeoTest.php tests/Feature/PublicCatalogueTest.php tests/Feature/CmsContentTest.php tests/Feature/PublicReservationTest.php --colors=never`: PASS, 34 tests / 385 assertions.
- `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage88_acceptance_test php8.4 vendor/bin/phpunit tests/Feature/Sync/SyncRecoveryTest.php tests/Feature/Sync/SyncTest.php --colors=never`: PASS, 5 tests / 16 assertions.
- `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage88_acceptance_test php8.4 vendor/bin/phpunit tests/Feature/Performance/PerformanceOptimizationTest.php tests/Feature/Sales --colors=never`: PASS, 23 tests / 176 assertions.
- `cd frontend && npm run typecheck`: exit 0. `cd frontend && npm run lint`: exit 0, 2 pre-existing warnings. `cd frontend && npm run build`: exit 0, 337.31 kB main chunk (111.38 kB gzip), 6.11 s.
- Read-only `python3` TSX scan from `cd frontend`: `labels_htmlfor=142 labels_wrapping=75 unlabelled=0`; `th_elements=99 th_missing_scope=0`; `pos_min_h_11=8`; `focus_visible_rule=True`; `i18n_en_keys=11 i18n_sn_keys=11`; `locale_provider_mounted=False`; `t_calls_in_components=0`.
- `php8.4 backend/scripts/backup/manifest.php validate /tmp/opencode/tms-backup-drill/torqueerp_stage85_source_test-20261121T035551Z.manifest.json`: `PASS manifest`.
- `RESTORE_TARGET_DB=torqueerp_stage85_restore_test BACKUP_MANIFEST=/tmp/opencode/tms-backup-drill/torqueerp_stage85_source_test-20261121T035551Z.manifest.json RESTORE_VERIFY_ONLY=1 bash backend/scripts/backup/restore.sh`: 2 artifacts PASS checksum/size, 14 counts PASS, 7 workflow invariants PASS, `RESTORE VERIFICATION PASS`; report `verify-torqueerp_stage85_restore_test-20261121T053152Z.tsv`.
- `cd backend && composer audit --locked --format=json`: exit 4; 4 packages with advisories (1+4+1+1 as listed above).
- `curl -sS -o /dev/null -w '%{http_code}\n' --max-time 5 http://127.0.0.1:8000/api/health`: `200`.
- `curl -k -sS -o /dev/null -w '%{http_code}\n' --max-time 5 https://127.0.0.1:8000/api/health`: `000`, `SSL_ERROR_SYSCALL`.
- Pint: NOT RUN because no PHP file was changed in this stage. No browser/DevTools, screen-reader, live provider or mobile-device command was run.

### Files changed
- New: `docs/RELEASE_CHECKLIST.md` (explicitly required release checklist and rollback plan).
- Updated: `README.md`, `docs/CHANGELOG.md`, `docs/DEVELOPMENT.md`, `docs/DECISIONS.md`, `docs/BUSINESS_RULES.md`, `docs/REQUIREMENTS_TRACEABILITY.md`, `docs/DEPLOYMENT.md`.
- No application PHP, route, permission, configuration, seeder, test, frontend source, `.env`, `.env.example` or dependency file was changed. Pre-existing uncommitted work from Stages 4.x-8.7 was left untouched and was not reverted.

### Migrations
- None added by Stage 8.8. The acceptance database was built from the existing 69 migrations via `migrate:fresh`; no table, column, index, permission, ledger row or seeder was changed. The existing Stage 8.4 `2026_11_21_033105_create_webhook_events_table` remains applied in the schema.

### Assumptions
- The inspected host is a local development topology; production host, domain, document root, web user, TLS paths, service units, monitor and secret store are supplied by the infrastructure owner and remain placeholders.
- Stage 8.2 test-harness measurements and Stage 8.5 manifest/artifacts are the authoritative local performance and backup evidence; Stage 8.8 re-executed their bounded tests and verification rather than inventing new production claims.
- PASS / FAIL / BLOCKED / NOT RUN are used literally; a command passing locally does not close an owner-dependent production gate.
- The acceptance database and drill databases are disposable and contain synthetic data only; no production data or credential was used.

### Known risks and blockers
- No production deployment can be approved while production TLS/host, worker/scheduler, uptime/pager, off-site encrypted backups/PITR, owner language list, legal/tax/payroll sign-off and Composer advisories remain open.
- Browser runtime, screen reader, keyboard order, rendered contrast, 375 px reflow and touch comfort are NOT RUN; static source checks only prove the listed defects are absent from source.
- Backup verification covers a synthetic 14-table fixture and `encryption_at_rest=none`; GPG, off-site transfer, production-volume RTO and the 15-minute RPO are unproven.
- Performance figures are not production traffic measurements; the main bundle remains 337.31 kB and the two pre-existing lint warnings and advisory list persist.
- Backup acceptance is valid only for the recorded manifest; repeat the restore drill after any schema change and never promote an unverified restore target.

### Verification steps
- Re-run C1-C8 on the exact release commit and attach outputs; require zero failures and the same 69 migrations.
- Re-run the Stage 8.5 restore drill with a fresh manifest and target, then repeat `RESTORE_VERIFY_ONLY=1`; require `RESTORE VERIFICATION PASS`.
- Complete G5-G12 in `docs/RELEASE_CHECKLIST.md` with owner evidence: TLS/health, worker/scheduler/pager, encrypted off-site backup, dependency decision, language copy, professional assumptions and browser/device QA.
- On the real host run `curl -fsS https://<public-host>/api/health`, `queue:failed`, `schedule:list`, log tails and one sales/reservation smoke test before declaring recovery or release complete.

### Rollback and recovery notes
- Stage 8.8-specific rollback: delete `docs/RELEASE_CHECKLIST.md`, revert the seven updated docs and drop only `torqueerp_stage88_acceptance_test` after archiving evidence. No code, schema, cache or data rollback is required.
- Operational rollback follows `docs/DEPLOYMENT.md` section 5: verify backup, stop/drain the queue worker, optionally enable maintenance mode with approval, switch to the previous immutable release, clear/rebuild caches, restart worker/PHP-FPM, then re-run health, queue, scheduler, log and smoke checks.
- Database forward recovery is preferred: fix root cause, restore a verified backup into a separate non-production target if data changed, then `migrate --force` and `migrate:status`. Gated rollback is `migrate:rollback --step=1 --force` only for a confirmed reversible migration; do not roll back `2026_11_21_033105_create_webhook_events_table` while callbacks may be in flight.
- Storage recovery uses only verified backup artifacts (`backend/storage/app/private`); frontend recovery redeploys the previous immutable `frontend/dist`. Never promote a failed or unverified restore target.
