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
