# TorqueERP

Multi-branch ERP, POS, inventory, finance, public marketing website, online store/reservations, HR/payroll, service/workshop and reporting system.

Built for Taziva Motor Spares (TMS), Rusape, Zimbabwe, with configurable terminology/catalogues for other trades.

## Delivery status
Phase 0: DONE
- 0.1 Audit and plan: DONE
- 0.2 Architecture decisions: DONE
- 0.3 Development foundation: DONE
- 0.4 Schema and migration baseline: DONE (MariaDB tested)
- 0.5 Auth and tenant skeleton: DONE
- 0.6 App shell, UI system and language: DONE
- 0.7 Live skeleton acceptance: DONE

Phase 1
- 1.1 Users, roles and permissions: DONE
- 1.2 Products, categories, units and stock foundation: DONE (SQLite and MariaDB tested)
- 1.3 Canonical stock ledger: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 1.4 Customer records: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 1.5 POS cart and price calculation: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 1.6 Checkout, payments and receipt: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 1.7 Sales history and correction foundation: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 1.8 End-to-end acceptance: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- Phase 1 complete pending owner approval
- 2.2 Payment accounts and cash movements: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 2.3 Expenses and bills to pay: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 2.4 Receivables and collections: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 2.5 Finance metrics and FX history: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)

## Quick start (development)
See `docs/DEVELOPMENT.md` for setup, run, test, lint/format/type commands.

## Local URLs (verified)
- Frontend dev: `http://localhost:5173/` (Vite)
- Backend API: `http://localhost:8000/api/` (Laravel)
- Health: `http://localhost:8000/api/health`
- Routes: `/`, `/login`, `/app`, `/app/products`, `/app/customers`, `/app/stock`, `/app/pos`, `/app/users`, `/platform`

## Tech stack
- Backend: Laravel 13 + MariaDB
- Frontend: React 18 + TypeScript + Vite + Tailwind CSS + shadcn/ui patterns
- Auth: Laravel Breeze API + Sanctum (public registration disabled)
- Data: TanStack Query, typed API client ready
