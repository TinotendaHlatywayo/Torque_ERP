
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
