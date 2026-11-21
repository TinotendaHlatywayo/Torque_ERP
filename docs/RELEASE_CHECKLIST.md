# Release candidate acceptance and checklist (Stage 8.8)

Overall status: **PARTIAL — BLOCKED for production release. NOT production-ready.**

All executable local acceptance criteria pass, but critical deployment, security, operational, legal/language and browser-runtime criteria remain BLOCKED or NOT RUN. A production release must not be approved until every gate in section 4 is closed with the named owner's evidence. This document is a reporting artifact only: no application feature, dependency, schema or configuration was changed in Stage 8.8.

## 1. Evidence boundary

- Acceptance database: `torqueerp_stage88_acceptance_test`, a newly created non-production MariaDB database. `migrate:fresh --force` ran there; no shared or production database was migrated or written.
- Backups: the existing Stage 8.5 artifacts in `/tmp/opencode/tms-backup-drill` and the separate `torqueerp_stage85_restore_test` database were verified read-only; no backup was created, restored over, or promoted to production.
- "PASS" means the exact command below exited 0 on this host with the stated result. "BLOCKED" means an external owner, secret, host or external truth is required. "NOT RUN" means the tooling or environment is absent. No unverified criterion is recorded as PASS.

## 2. Criterion status

| # | Criterion | Status | Evidence |
|---|---|---|---|
| C1 | Composer/npm install validity, no dependency changes | PASS | `composer validate` valid; `composer install --dry-run` nothing to change; `npm ci --dry-run --ignore-scripts` added 47 packages in dry run; dependency manifests unchanged by this stage. |
| C2 | Clean setup and migration in non-production DB | PASS | New `torqueerp_stage88_acceptance_test`; `migrate:fresh --force` ran 69 migrations, all DONE; `migrate:status` PASS; `migrations` row count 69. |
| C3 | Frontend typecheck / lint / build | PASS | `typecheck` exit 0; `lint` 0 errors, 2 pre-existing warnings; `build` exit 0, 258 modules, main chunk 337.31 kB (gzip 111.38 kB). |
| C4 | Full PHPUnit with MariaDB | PASS | 338 tests / 2419 assertions / 159.361 s against the clean acceptance database, exit 0. |
| C5 | Focused tenant and security | PASS | 44 tests / 136 assertions (branch isolation, branch context, access control, branch users, security regression). |
| C6 | Financial fixture reconciliation | PASS | 23 tests / 129 assertions (`FinancialReconciliationTest` plus adjacent finance/report suites); no double-counting regressions. |
| C7 | Public SEO and crawler policy | PASS | 34 tests / 385 assertions (`PublicSeoTest`, public catalogue, CMS content, public reservations). |
| C8 | Offline recovery (implemented) | PASS | 5 tests / 16 assertions (`SyncRecoveryTest`, `SyncTest`): duplicate/retry, invalid payload and expired session behavior. |
| C9 | Performance measurements | PASS (local) / BLOCKED (production) | Stage 8.2 measurements unchanged: `sales.index` 80.98→24.89 ms, 34→4 queries, 13,185→6,150 bytes; bundle 615.37→243.25 kB main. Stage 8.8 re-ran bounded performance/sales tests (23 tests / 176 assertions) and re-measured the current build. Production latency claims remain BLOCKED. |
| C10 | Backup restore verification from Stage 8.5 artifacts | PASS (drill) | Manifest valid, two artifact checksums PASS, 14 counts and 7 workflow invariants PASS, `RESTORE VERIFICATION PASS` in read-only mode. Off-site storage, encryption, PITR and production-scale RTO remain BLOCKED. |
| C11 | Accessibility / mobile static checks | PASS (static) | 0 unlabelled labels, 99 table headers with scope (0 missing), 8 POS 44 px targets, focus-visible rule present; Stage 8.7 real browser, screen reader and 375 px device checks remain NOT RUN. |
| C12 | Translation completeness | BLOCKED | No owner-approved language list. `en`/`sn` have 11 keys each, `LocaleProvider` is unmounted and there are 0 `t()` calls in components; no language is claimed complete. |
| C13 | Production host, document root, HTTPS/TLS, secrets | BLOCKED | Local health returned HTTP 200 but TLS probe returned `000` with `SSL_ERROR_SYSCALL`; no `:443`, production vhost, secret store or real origin was supplied. |
| C14 | Queue worker, scheduler, uptime and payment/webhook pager | BLOCKED / NOT RUN | Bounded commands are documented; no TMS worker/cron, external monitor or Slack/webhook pager is configured. Failed-job logging is file-only. |
| C15 | Dependency advisories | BLOCKED | `composer audit --locked --format=json` exit 4: laravel/framework 1, symfony/http-foundation 4, symfony/http-kernel 1, symfony/routing 1 advisories. Dependency changes need approval. |
| C16 | Legal, tax, payroll, depreciation and forecast assumptions | BLOCKED | Statutory rates, tax filing, leave-pay effects, depreciation policy, COGS cost layers and authoritative cash forecasts are unconfirmed by the owner/professional advisers. |
| C17 | Browser runtime verification | NOT RUN | No browser/DevTools, screen reader, live Paynow account or mobile device was used in this environment. |
| C18 | Documentation, release checklist and rollback plan | PASS | This checklist plus the seven updated root docs record criteria, commands, outcomes, files, migrations, assumptions, risks, verification and rollback. |
| FAIL criteria | None | — | No executed criterion failed; blocked/unverified criteria are not hidden. |

## 3. Exact commands and outcomes

All commands were run from `/opt/lampp/htdocs/TMS_ERP` unless a directory is shown. Credentials were sourced from the ignored `backend/.env` (`set -a; . backend/.env; set +a`) and never printed.

| Command | Outcome |
|---|---|
| `cd backend && composer validate --no-check-publish --no-interaction` | `./composer.json is valid`, exit 0 (system Composer emitted PHP 8.4 deprecation notices). |
| `cd backend && composer install --dry-run --no-interaction` | `Nothing to install, update or remove`, exit 0. |
| `cd frontend && npm ci --dry-run --ignore-scripts` | `added 47 packages in 1s`, exit 0. |
| `mysql -e "DROP DATABASE IF EXISTS torqueerp_stage88_acceptance_test; CREATE DATABASE torqueerp_stage88_acceptance_test ..."` | New empty non-production database created. |
| `cd backend && DB_DATABASE=torqueerp_stage88_acceptance_test php8.4 artisan migrate:fresh --force --no-interaction` | 69 migrations DONE, exit 0; `migrate:status` PASS; `SELECT COUNT(*) ... migrations` = 69. |
| `cd backend && DB_CONNECTION=mariadb DB_DATABASE=torqueerp_stage88_acceptance_test php8.4 vendor/bin/phpunit --colors=never` | PASS: 338 tests, 2419 assertions, 159361 ms. |
| `... php8.4 vendor/bin/phpunit tests/Feature/BranchIsolationTest.php tests/Feature/Tenancy/BranchContextTest.php tests/Feature/AccessControl/BranchAccessTest.php tests/Feature/AccessControl/BranchUserManagementTest.php tests/Feature/Security/SecurityRegressionTest.php --colors=never` | PASS: 44 tests, 136 assertions. |
| `... php8.4 vendor/bin/phpunit tests/Feature/Acceptance/FinancialReconciliationTest.php tests/Feature/Finance/FinanceMetricsTest.php tests/Feature/Finance/ExpenseBillTest.php tests/Feature/Finance/MoneyMovementTest.php tests/Feature/Sales/ReceivableTest.php tests/Feature/Reports/ReportTest.php --colors=never` | PASS: 23 tests, 129 assertions. |
| `... php8.4 vendor/bin/phpunit tests/Feature/PublicSeoTest.php tests/Feature/PublicCatalogueTest.php tests/Feature/CmsContentTest.php tests/Feature/PublicReservationTest.php --colors=never` | PASS: 34 tests, 385 assertions. |
| `... php8.4 vendor/bin/phpunit tests/Feature/Sync/SyncRecoveryTest.php tests/Feature/Sync/SyncTest.php --colors=never` | PASS: 5 tests, 16 assertions. |
| `... php8.4 vendor/bin/phpunit tests/Feature/Performance/PerformanceOptimizationTest.php tests/Feature/Sales --colors=never` | PASS: 23 tests, 176 assertions (bounded query counts, payload caps, checkout budget). |
| `cd frontend && npm run typecheck` | Exit 0. |
| `cd frontend && npm run lint` | Exit 0; 2 pre-existing `react-refresh/only-export-components` warnings in `LocaleContext.tsx:36` and `ThemeContext.tsx:37`; 0 errors. |
| `cd frontend && npm run build` | Exit 0; 258 modules, main chunk 337.31 kB (gzip 111.38 kB), built in 6.11 s. |
| Read-only `python3` TSX scan from `cd frontend` | `labels_htmlfor=142 labels_wrapping=75 unlabelled=0`; `th_elements=99 th_missing_scope=0`; `pos_min_h_11=8`; `focus_visible_rule=True`; `i18n_en_keys=11 i18n_sn_keys=11`; `locale_provider_mounted=False`; `t_calls_in_components=0`. |
| `php8.4 backend/scripts/backup/manifest.php validate <manifest>` | `PASS manifest`. |
| `RESTORE_TARGET_DB=torqueerp_stage85_restore_test BACKUP_MANIFEST=<manifest> RESTORE_VERIFY_ONLY=1 bash backend/scripts/backup/restore.sh` | 2 artifacts PASS checksum/size; 14 counts PASS; 7 workflow invariants PASS; `RESTORE VERIFICATION PASS`; report `/tmp/opencode/tms-backup-drill/verify-torqueerp_stage85_restore_test-20261121T053152Z.tsv`. |
| `cd backend && composer audit --locked --format=json` | Exit 4; advisories: laravel/framework 1, symfony/http-foundation 4, symfony/http-kernel 1, symfony/routing 1. |
| `curl -sS -o /dev/null -w '%{http_code}\n' --max-time 5 http://127.0.0.1:8000/api/health` | `200`. |
| `curl -k -sS -o /dev/null -w '%{http_code}\n' --max-time 5 https://127.0.0.1:8000/api/health` | `000`, `SSL_ERROR_SYSCALL` — HTTPS not configured. |

Manifest path used above: `/tmp/opencode/tms-backup-drill/torqueerp_stage85_source_test-20261121T035551Z.manifest.json` (from `last_manifest_path.txt`).

## 4. Release checklist — gates requiring owner sign-off

| Gate | Requirement | Status | Owner | Evidence / action to close |
|---|---|---|---|---|
| G1 | Clean setup, 69 migrations and full MariaDB suite green on the release commit | PASS (this commit) | On-call engineer | Re-run section 3 commands on the exact release commit; attach PHPUnit output. |
| G2 | Tenant/security, finance, SEO, offline and performance suites green | PASS (this commit) | On-call engineer | Re-run focused commands after any code change; no failures allowed. |
| G3 | Frontend typecheck/lint/build and static a11y/mobile checks green | PASS | Frontend owner | Re-run commands; browser/device items are separate gates. |
| G4 | Backup artifact verified by restore into a separate non-production DB | PASS (synthetic drill) | On-call + application owners | Confirm the manifest and verification report above; repeat after any schema change. |
| G5 | Production host, web root, HTTPS certificate, `APP_ENV=production`, secure cookies, secret store | BLOCKED | Infrastructure owner | Complete `docs/DEPLOYMENT.md` placeholders, install TLS, validate with a real `curl` and record results. |
| G6 | Queue worker, scheduler cron/unit, external uptime/TLS expiry checks and payment/webhook pager | BLOCKED | Infrastructure owner | Install units and pager webhook; prove a failed-job alert and reservation expiry in staging; see `docs/DEPLOYMENT.md` §4.6-4.8. |
| G7 | Off-site backup target, encryption in transit/at rest, retention pruning and PITR | BLOCKED | Infrastructure + security owners | Configure target, GPG recipient and lifecycle policy; re-run the encrypted drill; implement binlogs/PITR if the 15-minute RPO is required. |
| G8 | Dependency advisories reviewed and approved or upgraded | BLOCKED | Security owner + project approver | Approval is required before dependency changes; re-run `composer audit` and record the decision. |
| G9 | Owner-approved language list and translated screens | BLOCKED | Product owner | Supply languages and approved copy; mount `LocaleProvider`, add a switcher, translate screens and repeat the audit before claiming any language complete. |
| G10 | Legal/tax/payroll, depreciation, COGS and forecast assumptions confirmed | BLOCKED | Application owner + professional advisers | Sign off statutory rates, filing/leave-pay rules, accounting policy and cost-basis approach; update `docs/BUSINESS_RULES.md`. |
| G11 | Browser runtime, screen reader, keyboard walk and 375 px/touch device verification | NOT RUN | QA owner | Complete the manual passes listed in `docs/CHANGELOG.md` Stage 8.7 and record findings. |
| G12 | Production-scale performance and one business smoke test after deployment | BLOCKED / NOT RUN | Infrastructure + application owners | Deploy to the real origin, measure with production-like traffic and run a sales/reservation smoke test. |

**Do not mark the release PASS or production-ready while any of G5-G12 remains open.** The current recommendation is: **do not release to production; close BLOCKED/NOT RUN gates and re-run G1-G4 on the final release commit.**

## 5. Known risks and blockers

- Production target details, TLS, worker/scheduler supervision, log shipping and external monitoring are placeholders, not installed controls. Local `/api/health` HTTP 200 does not prove those services or TLS.
- Backups are proven only for a synthetic 14-table fixture with `encryption_at_rest=none` on a local filesystem; no off-site transfer, GPG restore, PITR or production-volume RTO measurement was executed. Nightly dumps give up to 24 h RPO.
- Upstream Composer advisories remain open; no dependency was changed because project rules require approval.
- Translation, legal/tax/payroll, depreciation, COGS and cash-forecast assumptions require owner/adviser confirmation. No invoices, filings or accounting postings were created in acceptance.
- Browser runtime, screen-reader and device checks are NOT RUN; static a11y scans do not prove announcements, focus order, contrast or touch comfort.
- Stage 8.2 performance figures come from a test-harness MariaDB fixture, not production traffic. The current build remains a 337.31 kB main chunk; no new bundle regression was introduced.
- The acceptance DB and restored drill DB are disposable. They contain no production data; leave them in place until evidence is archived, then drop only the explicitly named test databases.

## 6. Verification steps for the release owner

1. Check out the exact release commit, confirm no dependency file changed since this run, and repeat C1-C3.
2. Create a new `torqueerp_*_test` database (never a shared/production name) and repeat C2-C8; require zero failures.
3. Repeat C10 with the final manifest and a fresh restore target; require `RESTORE VERIFICATION PASS` and a read-only re-check.
4. On the real host, complete G5-G8 using `docs/DEPLOYMENT.md` and record TLS, health, queue, scheduler, pager and smoke-test evidence.
5. Complete G9-G11 with owner copy, professional sign-off and QA browser/device notes.
6. Only when every gate is closed, update this checklist's overall status with the owner's name and date, then deploy with the rollback plan below ready.

## 7. Rollback and recovery plan

The authoritative operational procedure is `docs/DEPLOYMENT.md` section 5; the Stage 8.5 restore procedure is `docs/BACKUP_RESTORE.md`. Stage 8.8 itself is documentation-only.

1. **Before deploy:** record the current commit, `migrate:status` and a verified backup manifest; confirm `/api/health` is 200. Without a verified backup, stop — do not deploy or roll back schema.
2. **Freeze writes:** stop the queue worker unit, drain with `php8.4 artisan queue:work --queue=default --stop-when-empty --tries=3 --timeout=30 --no-interaction`, and use `php8.4 artisan down --no-interaction` only with owner approval.
3. **Code rollback:** point the release symlink/web root back to the previous immutable release, run `php8.4 artisan optimize:clear --no-interaction`, restart the queue worker and PHP-FPM, then `php8.4 artisan up --no-interaction`.
4. **Config rollback:** restore the previous environment from the secret store, run `config:clear`, `config:cache`, `optimize`, `queue:restart` and restart worker/PHP-FPM. Never edit secrets into docs or the repository.
5. **Database forward recovery (preferred):** fix the root cause, restore a verified backup into a separate non-production target if data changed, then run `php8.4 artisan migrate --force --no-interaction` and `migrate:status`. Never rerun a partially applied migration blindly.
6. **Database rollback (gated):** only with a verified backup and a confirmed reversible migration, run `php8.4 artisan migrate:rollback --step=1 --force --no-interaction`. Do not roll back `2026_11_21_033105_create_webhook_events_table` while callbacks may be in flight; drain and verify first.
7. **Storage/frontend:** restore `backend/storage/app/private` only from a verified manifest, and redeploy the previous immutable `frontend/dist` artifact rather than rebuilding during an incident.
8. **After recovery:** confirm HTTPS `curl -fsS https://<public-host>/api/health`, `queue:failed`, `schedule:list`, log tails and one business smoke test before declaring recovery complete. Preserve failed targets for investigation; never promote an unverified restore.

Stage 8.8-specific rollback: revert the documentation files listed in `docs/CHANGELOG.md` Stage 8.8 and delete `docs/RELEASE_CHECKLIST.md`. Drop `torqueerp_stage88_acceptance_test` only after the evidence is archived; never drop `torqueerp_test`, `torqueerp_stage12_test` or any Stage 8.5 drill database before its evidence is retained.

## 8. Files, migrations and assumptions

- New: `docs/RELEASE_CHECKLIST.md`.
- Updated: `README.md`, `docs/CHANGELOG.md`, `docs/DEVELOPMENT.md`, `docs/DECISIONS.md`, `docs/BUSINESS_RULES.md`, `docs/REQUIREMENTS_TRACEABILITY.md`, `docs/DEPLOYMENT.md`.
- Migrations: none added by Stage 8.8. The acceptance database was created from the existing 69 migrations; no schema, permission, seeder, ledger row, code, test or dependency file was changed.
- Assumptions: the inspected host represents only the local test topology; production values stay as placeholders; Stage 8.2 measurements and Stage 8.5 artifacts remain the authoritative local performance/backup evidence; status language follows PASS / FAIL / BLOCKED / NOT RUN and unproven criteria are never promoted to PASS.
