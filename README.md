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
- 2.6 Documents and templates: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 2.7 Reports and dashboard v1: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 2.8 Financial reconciliation acceptance: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 3.1 Branch registration and lifecycle: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 3.2 Membership and context switching: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 3.3 Platform/branch settings: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 3.4 Cross-branch reports and exports: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 3.5 Audit and platform acceptance: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 4.1-4.6 Offline POS and sync: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 5.1-5.6 Public website, CMS and reservations: DONE (pending owner approval)
- 6.1 Employee records and lifecycle: DONE
- 6.2 Leave, attendance and disciplinary records: DONE
- 6.3 Payroll, salary grades and loans: DONE
- 6.4 Payroll payment and reconciliation: DONE
- 6.5 Tasks and notifications: DONE
- 6.6 HR/payroll acceptance: DONE
- 7.1 Vehicle fitment, OEM/cross-reference search (catalogue): DONE
- 7.2 Service catalogue and job cards: DONE
- 7.3 Stock transfers and stocktakes: DONE
- 7.4 Loyalty and win-back: DONE (delivery NOT RUN; no channel integration)
- 7.5 Assets and maintenance: DONE (depreciation ESTIMATE only; accounting use BLOCKED pending policy)
- 7.6 Inventory intelligence and dashboard v2: DONE (cash FORECAST only; authoritative finance use BLOCKED)
- 7.7 Phase 7 integration acceptance: DONE (testable flows PASS; COGS and external-source limitations BLOCKED; browser NOT RUN)
- 8.1 Security threat review and fixes: DONE (CSRF hardening and negative tests PASS; dependency advisories BLOCKED; browser NOT RUN)
- 8.3 SEO verification: DONE (14 public SEO tests PASS; bounded fixes for JSON-LD, H1, descriptions, robots and favicon; browser NOT RUN)
- 8.4 Queues, external services and monitoring: DONE (11 reliability tests PASS; webhook dedupe, bounded Paynow calls, health checks, redacted logs and failed-job hook; pager delivery BLOCKED; browser NOT RUN)
- 8.8 Release-candidate acceptance: PARTIAL / BLOCKED (local setup, 338 tests, focused suites, SEO, restore verification and static a11y PASS; production host/TLS, off-site encrypted backup/PITR, pager, language list, legal/tax sign-off, advisories and browser runtime BLOCKED/NOT RUN). NOT production-ready; see `docs/RELEASE_CHECKLIST.md`.

## Quick start (development)
See `docs/DEVELOPMENT.md` for setup, run, test, lint/format/type commands.

## Local URLs (verified)
- Frontend dev: `http://localhost:5173/` (Vite)
- Backend API: `http://localhost:8000/api/` (Laravel)
- Health: `http://localhost:8000/api/health`
- Routes: `/`, `/login`, `/app`, `/app/inventory-intelligence`, `/app/products`, `/app/catalogue-search`, `/app/customers`, `/app/loyalty`, `/app/assets`, `/app/stock`, `/app/transfers`, `/app/pos`, `/app/tasks`, `/app/jobs`, `/app/users`, `/app/employees`, `/app/hr`, `/app/payroll`, `/platform`

## Loyalty and win-back (Stage 7.4)
- Qualification is recalculated from non-void `sales` minus documented `sale_returns` through `POST /api/branches/{branch}/loyalty/recalculate` (requires `branch.loyalty.manage`).
- The inactivity window, qualifying thresholds, loyalty discount and combined-discount approval threshold are stored per branch and exposed through `GET/PUT /api/branches/{branch}/loyalty/config`.
- Win-back candidates require explicit customer consent and an active channel. `POST /api/branches/{branch}/loyalty/winback/export` produces a redacted stub only; no automated message is sent because no channel integration exists.

## Assets and maintenance (Stage 7.5)
- Assets record acquisition date, `cost_minor`, currency, location, custodian, status and an optional link to an existing `expense`, `supplier_bill` or `money_movement`. Linking never creates a duplicate expense or payment.
- `GET/POST /api/branches/{branch}/assets`, `GET /api/branches/{branch}/assets/{asset}`, `POST /api/branches/{branch}/assets/{asset}/maintenances`, `POST /api/branches/{branch}/assets/{asset}/dispose` (branch-scoped permissions `branch.assets.view/manage/dispose`).
- Depreciation is calculation only: `GET /api/branches/{branch}/assets/depreciation-estimate` and `GET /api/branches/{branch}/assets/{asset}/depreciation-estimate` return ESTIMATE / BLOCKED results and post nothing. Useful life and residual are supplied by the caller; no policy default is asserted.
- Disposal is audited and returns cost, accumulated estimate, proceeds and estimated gain/loss. No accounting entry or money movement is posted automatically.
- Full acceptance report, exact commands and rollback notes: `docs/CHANGELOG.md` (Stage 7.5).

## Inventory intelligence and dashboard v2 (Stage 7.6)
- One shared endpoint, `GET /api/branches/{branch}/dashboard/v2?as_of=YYYY-MM-DD`, serves the existing finance cards, reorder suggestions, stock ageing/dead-stock buckets, ABC analysis and a labelled cash forecast. It is branch-scoped; `branch.inventory.view` gates inventory sections and `branch.finance.view` gates finance/forecast sections (a response with neither permission is 403).
- Windows/assumptions are explicit in `backend/config/inventory_intelligence.php`: reorder uses average daily sales over a configurable sales window with configurable lead time and days of cover; ageing uses last stock movement and configurable slow/dead thresholds; ABC uses revenue contribution over a configurable sales window with configurable cumulative A/B thresholds; the cash forecast uses a trailing history window and horizon.
- Reorder point = average daily sales x (lead time + days of cover); suggested order = reorder point minus (on hand - reserved), rounded up to whole base units. Null suggestions and named data-quality warnings are returned when sales history is insufficient; no demand is invented.
- Cash forecast output is labelled `FORECAST`, includes documented assumptions and data-quality warnings, and is not an authoritative finance statement. Cash target is an illustrative liquidity requirement (unpaid bills plus incurred expenses without a paid date).
- Frontend `/app/inventory-intelligence` and the `/app` dashboard v2 panels render from that single shared endpoint with accessible tables, text alternatives for bars, visible warnings and permission-aware sections. Browser runtime verification is NOT RUN.
- Full acceptance report, exact commands and rollback notes: `docs/CHANGELOG.md` (Stage 7.6).

## Phase 7 integration acceptance (Stage 7.7)
- `backend/tests/Feature/Acceptance/PhaseSevenIntegrationAcceptanceTest.php` is the executable acceptance contract for job cards, transfers, stocktakes, loyalty, returns, reports and dashboard v2.
- PASS: job labour/parts/manual invoice totals; transfer dispatch/receipt; stocktake variance approval; duplicate job part/receipt/approval/loyalty event/checkout/return retries; loyalty qualification, stacking/floor/approval and non-posting quotes; return effect on loyalty; report/dashboard metrics; tenant boundaries and cross-branch reference rejection.
- No duplicate stock movements, expenses, money movements, sales, discounts or loyalty qualifications were observed in the known fixtures; returns do not restock or reverse the original sale.
- BLOCKED: exact COGS amount (no cost basis/cost layers exist); real catalogue/OEM source imports; automated win-back delivery; authoritative finance use of depreciation and cash forecasts; operational cross-branch base-unit equivalence; unverified statutory/payroll assumptions.
- NOT RUN: browser runtime verification and frontend typecheck/lint/build because this stage changed no frontend files.
- Full acceptance report, exact commands and rollback notes: `docs/CHANGELOG.md` (Stage 7.7).

## Security threat review (Stage 8.1)
- Review covered auth/session, authorization, tenant isolation, CSRF, XSS, SQL injection, mass assignment, uploads, secrets, rate limits, webhooks, errors, exports, jobs, audit and public rendering.
- Fixed: stateful login and logout no longer bypass Laravel CSRF protection; the SPA already fetches `/sanctum/csrf-cookie` before login and sends the XSRF header through the shared Axios client.
- Added negative security tests for inactive/revoked users, cross-branch access, missing permissions, mass assignment, ignored unknown fields, public XSS escaping, export permission/audit and invalid Paynow webhook signatures; login throttling is also asserted.
- BLOCKED: upstream Composer advisories in `laravel/framework` and `symfony/*` remain until dependency changes are approved. Browser runtime verification is NOT RUN.
- Full acceptance report, exact commands, residual risks and rollback notes: `docs/CHANGELOG.md` (Stage 8.1).

## SEO verification (Stage 8.3)
- Verified rendered public homepage, catalogue, category, product and published CMS pages for unique titles/descriptions, self-referencing canonicals, robots meta, sitemap, one H1 with logical headings, image alt text, valid JSON-LD, 1200x630 OG image metadata, favicon, language and clean internal links.
- Fixed a real structured-data defect: Laravel 13 compiled the literal `@context` JSON-LD key as a Blade directive, so all four public views emitted a PHP fragment as the schema context. Added an ItemList `url`, a CMS H1 fallback for text/image-only pages and unique catalogue/category descriptions.
- Removed the stale `backend/public/robots.txt` that shadowed the environment-aware `/robots.txt` route; added noindex to the SPA iframe mockup and the missing frontend favicon file.
- Private `/app`, `/platform`, `/api`, unpublished CMS content, unreferenced media and other-branch products are absent from public HTML, JSON-LD and sitemap (negative tests included).
- Full acceptance report, exact commands, assumptions, risks and rollback notes: `docs/CHANGELOG.md` (Stage 8.3). Browser runtime and external HTML validation NOT RUN.

## Queues, external services and monitoring (Stage 8.4)
- Notification jobs keep explicit bounded retry metadata (`tries=3`, `timeout=30`, `backoff=[15,60]`) and add `ShouldBeUnique` with a 300-second lock; duplicate dispatches are dropped before they reach the queue.
- Paynow callbacks are deduplicated with a unique `webhook_events(provider, event_key)` row derived from reference, provider reference, amount, status and poll URL. Replays log `paynow.webhook.replay_ignored`, skip the bounded provider poll and never re-apply payment state. Invalid signatures are rejected before any provider call.
- External HTTP calls have clamped connect/read timeouts and at most three poll attempts (initial + two retries) per callback; initiate uses one attempt. All values come from `backend/config/paynow.php` and `.env.example`, with no real external call in tests.
- `GET /api/health` now reports database, queue size and failed-job count, echoes correlation IDs in headers and JSON, and returns 503 with `status=degraded` when a check fails. It never returns exception details.
- Correlation/request IDs are accepted from `X-Correlation-Id` / `X-Request-Id` (validated, else generated), shared with Laravel logs, echoed on responses and copied into queued job payloads for worker-side continuity.
- Structured logs use a dedicated `observability` daily channel, recursively redact secrets/PII and exception messages, and attach correlation IDs. Failed jobs emit `queue.job.failed` with a redacted exception class. Actual Slack/webhook paging is BLOCKED until a pager channel and webhook URL are configured.
- Circuit breaker: NOT APPLICABLE for this stage. One bounded external integration plus idempotent webhook dedupe and existing route throttling do not justify persistent breaker state or new infrastructure.
- Full acceptance report, exact commands, files, assumptions, risks, rollback notes and browser/browser-pager limitations: `docs/CHANGELOG.md` (Stage 8.4). Browser runtime verification NOT RUN.

## Backup, restore and retention drill (Stage 8.5)
- Full runbook, proposed schedule, retention, RPO/RTO, responsibilities, secret rotation and drill evidence: `docs/BACKUP_RESTORE.md`.
- Bounded scripts live in `backend/scripts/backup/`: `backup.sh`, `restore.sh`, `run_drill.sh`, `manifest.php`, `fixture.sql` and `common.sh`.
- Demonstrated backup of `torqueerp_stage85_source_test` and restore into separate `torqueerp_stage85_restore_test`; 14 key-table counts and 7 read-only workflow invariants matched, and a second read-only verification passed.
- Guardrails refuse production-like names, shared test databases and restores into the source database. No production data or credentials were used.
- Nightly cron, off-site storage, pruning and 15-minute RPO remain proposals/BLOCKED until infrastructure is configured; the achieved RPO of a nightly dump is up to 24 hours.
- Full acceptance report with exact commands, outcomes, assumptions, risks and rollback notes: `docs/CHANGELOG.md` (Stage 8.5).

## Deployment and operations runbook (Stage 8.6)
- Full runbook: `docs/DEPLOYMENT.md`. It documents the actually observed host (PHP 8.4.27, Node 22.17.1, npm 10.9.2, Composer 2.7.1, Laravel 13.39.0, nginx 1.24.0 plus XAMPP Apache 2.4.58, MariaDB 8.4.11), the local dev topology and a production checklist with explicit placeholders.
- Verified local topology: `php8.4 artisan serve` on port 8000, Vite on port 5173, MariaDB on 3306, nginx serving an unrelated default page on port 80. XAMPP Apache/MySQL are installed but not running and do not serve this project.
- HTTPS, production web-server vhost, queue worker supervision, scheduler cron/unit, external uptime/pager, production secret store and log shipping are BLOCKED/NOT RUN until the real host owner provides and configures them; no VPS/cPanel details were assumed.
- Exact commands cover dependency install (dry-run verified), migrations (`Nothing to migrate`), cache/config, asset build, queue worker, scheduler, storage permissions, `GET /api/health`, log locations, rollback and forward recovery. No production secret or value appears in docs.
- No migration or code change: focused/full PHPUnit and Pint NOT RUN; last known full MariaDB suite from Stage 8.5 was PASS (338 tests / 2419 assertions). Full report, criteria statuses and risks: `docs/CHANGELOG.md` (Stage 8.6).

## Translations, accessibility and mobile (Stage 8.7)
- Translation completeness: BLOCKED. No owner-selected language list exists. Only `en` and `sn` are defined in `frontend/src/i18n/index.ts` (11 keys each); `LocaleContext` is not mounted and no screen calls `t()`. No language is claimed complete, and six languages are not implemented.
- Fixed bounded accessibility defects: 63 unlabelled form controls in POS/tasks/payroll/HR records, login error association, error/success live roles, 55 missing table header scopes, semantic `<h2>` headings, a theme-token focus-visible fallback, mobile table scrolling in the charts, and 44 px POS touch targets.
- Static audits pass (0 unlabelled controls, 0 table headers without scope); `npm run typecheck`, `npm run lint` and `npm run build` pass. Browser runtime, screen-reader and real-device checks are NOT RUN.
- Full PASS/BLOCKED/NOT RUN criteria, commands, files, assumptions, risks and rollback notes: `docs/CHANGELOG.md` (Stage 8.7).

## Release-candidate acceptance (Stage 8.8)
- Overall: **PARTIAL / BLOCKED — NOT production-ready.** No executed criterion failed, but owner-dependent critical gates are open.
- Clean acceptance: new non-production `torqueerp_stage88_acceptance_test`, 69 migrations via `migrate:fresh`, full MariaDB suite 338 tests / 2419 assertions, frontend typecheck/lint/build PASS.
- Focused PASS: tenant/security 44 tests / 136 assertions; financial reconciliation 23 / 129; public SEO 34 / 385; offline recovery 5 / 16; performance/sales 23 / 176; Stage 8.5 restore verification PASS (14 counts, 7 invariants, read-only).
- Performance: Stage 8.2 measurements retained (`sales.index` 80.98→24.89 ms, 34→4 queries; main bundle 615.37→243.25 kB); current build main chunk 337.31 kB / 111.38 kB gzip. Production latency BLOCKED.
- Static a11y/mobile PASS (0 unlabelled labels, 0 headers without scope, 8 POS 44 px targets); browser, screen-reader and device checks NOT RUN.
- BLOCKED: production host/TLS/secrets, queue/scheduler/uptime/pager, off-site encrypted backups/PITR, dependency advisories, owner language list, legal/tax/payroll assumptions; translation completeness BLOCKED.
- Release gates, exact commands/outcomes, verification steps and rollback plan: `docs/RELEASE_CHECKLIST.md`. Full report: `docs/CHANGELOG.md` (Stage 8.8). No code, dependency or migration change in this stage.

## Tech stack
- Backend: Laravel 13 + MariaDB
- Frontend: React 18 + TypeScript + Vite + Tailwind CSS + shadcn/ui patterns
- Auth: Laravel Breeze API + Sanctum (public registration disabled)
- Data: TanStack Query, typed API client ready
- 8.2 Measured performance optimization: DONE
