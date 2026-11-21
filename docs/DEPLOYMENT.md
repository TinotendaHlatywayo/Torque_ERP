# Deployment and operations runbook (Stage 8.6)

This runbook records the host that was actually observed in this repository and the commands that were checked against it. It is **not** a guessed VPS/cPanel recipe. The current host is a local development host, not a production host; production-only details (domain, document root, web user, TLS paths, service manager names and secret store) are left as explicit placeholders.

No password, API key, token, private key or other production secret appears in this document. Real values belong in the deployment secret store and the ignored `backend/.env`, never in the repository.

## 1. Verified host inventory

All commands below were run from `/opt/lampp/htdocs/TMS_ERP` unless a different directory is shown. Outcomes are the actual observations for this stage.

| Command | Verified outcome |
|---|---|
| `php -v` | PHP 8.4.27 (cli), NTS, Zend Engine v4.4.27, Ubuntu build, OPcache 8.4.27 |
| `node -v` | `v22.17.1` |
| `npm -v` | `10.9.2` |
| `php8.4 artisan --version` (repo root, as specified) | FAIL: `Could not open input file: artisan`. Artisan is in `backend/`, not the repo root. |
| `cd backend && php8.4 artisan --version` | `Laravel Framework 13.39.0` |
| `composer --version` | `Composer version 2.7.1 2024-02-09 15:26:28` (system Composer; emits PHP 8.4 deprecation notices) |
| `php8.4 -m` | Required extensions present: `pdo_mysql`, `pdo_sqlite`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `zip`, `bcmath`, `PCNTL`, `POSIX`, OPcache |
| `cd backend && php8.4 artisan about --only=environment --json` | `application_name: TorqueERP`, `laravel_version: 13.39.0`, `php_version: 8.4.27`, `composer_version: 2.7.1`, `environment: local`, `debug_mode: true`, `url: localhost:8000`, `maintenance_mode: false`, `timezone: UTC` |
| `/opt/lampp/bin/apachectl -v` | `Server version: Apache/2.4.58 (Unix)`, built `Nov 25 2023` |
| `/opt/lampp/lampp status` | XAMPP for Linux 8.2.12-0. Reported `Apache is not running.`, `MySQL is not running.`, `ProFTPD is not running.` It also could not remove stale PID files (`Permission denied`), so the status output is advisory only. |
| `/opt/lampp/bin/mysql --version` | `Ver 15.1 Distrib 10.4.32-MariaDB` client |
| `cd backend && php8.4 artisan tinker --execute 'echo DB::connection()->getDriverName()." ".DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION)." ".DB::connection()->getDatabaseName();'` | `mariadb 8.4.11 torqueerp_test` (driver, server version and non-secret database name only) |
| `nginx -v` | `nginx version: nginx/1.24.0 (Ubuntu)` |
| `systemctl is-active nginx` | `active` |
| `curl -sS -D - -o /dev/null --max-time 5 http://127.0.0.1/` | `HTTP/1.1 200 OK`, `Server: nginx/1.24.0 (Ubuntu)`, default nginx page |
| `curl -sS -D - -o /dev/null --max-time 5 http://127.0.0.1/TMS_ERP/backend/public/` | `HTTP/1.1 404 Not Found`; no enabled nginx vhost points at this project |
| `/etc/nginx/sites-enabled/default` | Default server listens on `:80`, `root /var/www/html`, `server_name _`; PHP `location ~ \.php$` block is commented out |
| `ss -ltnp` | Listening: `:80` nginx, `0.0.0.0:8000` php8.4, `0.0.0.0:5173` node/Vite, `:3306` mysqld. No `:443` listener. |
| `curl -k https://127.0.0.1:8000/api/health` | TLS failure (`SSL_ERROR_SYSCALL`, HTTP code `000`). HTTPS is not configured for the app. |
| `pgrep -af 'artisan\|vite\|nginx\|apache\|mysqld\|php-fpm\|queue:work\|schedule:work'` | Running: system nginx/php-fpm, XAMPP-era php-fpm pool, mysqld, `php8.4 artisan serve --host=0.0.0.0 --port=8000`, Vite on 5173. No TMS `queue:work` or `schedule:work` process. |
| `crontab -l` | Only unrelated KairoCORE cron entries exist; no TorqueERP scheduler or queue entry is installed. |
| `stat -c '%a %U:%G %n' ...` | `backend/storage`, `backend/storage/app`, `backend/storage/app/private`, `backend/storage/framework`, `backend/storage/logs`, `backend/bootstrap/cache`, `backend/public`, `frontend/dist` are all `775 wayne:wayne` |
| `ls -la backend/storage/logs` | `laravel.log` (single channel, currently ~1.5 MB) and `observability-2026-11-21.log` (daily channel) exist |
| `frontend/dist` | Existing build output contains `index.html`, `assets/`, `favicon.svg`, `TMS_Homepage.html`, `vite.svg` |

**Conclusion:** the verified deployment is a local development topology: PHP 8.4 CLI dev server on port 8000, Vite dev server on port 5173, MariaDB 8.4.11 on 3306, and system nginx serving an unrelated default page on port 80. XAMPP Apache is installed but not running and is not serving this project. No production host details were supplied.

## 2. Dependency, migration and cache verification

These commands were checked without changing dependencies or environment secrets.

| Command | Outcome |
|---|---|
| `cd backend && composer validate --no-check-publish --no-interaction` | `./composer.json is valid` (system Composer emitted PHP 8.4 deprecation notices only) |
| `cd backend && composer install --dry-run --no-interaction` | PASS; `Nothing to install, update or remove` |
| `cd backend && composer install --dry-run --no-dev --no-interaction` | PASS; platform requirements verified; would remove 39 dev packages on a production-style install. Not executed here. |
| `cd backend && composer check-platform-reqs --no-interaction` | All listed platform requirements `success` |
| `cd frontend && npm ci --dry-run --ignore-scripts` | PASS; resolved lockfile, `added 47 packages in 1s` (dry run only) |
| `cd backend && php8.4 artisan migrate --force --no-interaction` | PASS; `Nothing to migrate` |
| `cd backend && php8.4 artisan migrate:status --no-interaction` | PASS; migrations listed as Ran, including the baseline and Stage 8.4 `webhook_events` migration |
| `cd backend && php8.4 artisan route:list --path=api/health --no-interaction` | `GET\|HEAD api/health .. Api\HealthController@index` |
| `cd backend && php8.4 artisan schedule:list --no-interaction` | `* * * * * php artisan reservations:expire .. Next Due: ...` |
| `cd backend && php8.4 artisan queue:failed --no-interaction` | `No failed jobs found.` |
| `cd backend && php8.4 artisan optimize:clear --no-interaction` | PASS; config, cache, compiled, events, routes and views caches cleared |
| `cd frontend && npm run build` | NOT RUN this stage. Last known result: PASS in Stage 8.3; no frontend file changed here. |

## 3. Local / development deployment

These are the commands for this observed host. They assume `backend/.env` already exists locally with development-only database settings; never commit that file.

```bash
cd /opt/lampp/htdocs/TMS_ERP/backend
composer install --no-interaction --prefer-dist
php8.4 artisan key:generate --no-interaction        # first local setup only
php8.4 artisan migrate --force --no-interaction
php8.4 artisan optimize:clear --no-interaction
php8.4 artisan serve --host=127.0.0.1 --port=8000
```

In a second terminal:

```bash
cd /opt/lampp/htdocs/TMS_ERP/frontend
npm ci
npm run dev -- --host=127.0.0.1 --port=5173
```

Optional watcher processes for workflows that need them:

```bash
cd /opt/lampp/htdocs/TMS_ERP/backend
php8.4 artisan queue:work --queue=default --tries=3 --timeout=30 --sleep=3 --no-interaction
php8.4 artisan schedule:work --no-interaction
```

Local health check:

```bash
curl -fsS -D - http://127.0.0.1:8000/api/health
```

Verified response at `Sat, 21 Nov 2026 03:59:05 GMT` was HTTP 200 with `status: ok`, `checks.database.ok: true`, `checks.queue.ok: true`, `checks.queue.pending: 0`, `failed_jobs.count: 0`, correlation headers and a correlation ID in the JSON. UUID values change per request.

`start-dev.sh` in the repo root starts the same two dev servers; `tms_start.sh` starts XAMPP and is **not** required for the verified dev topology. `docker-compose.yml` contains hardcoded development database passwords and must not be used as a production deployment template.

## 4. Production checklist (host-specific values are placeholders)

Fill these in before a production deployment; none were verified in this stage:

- Public HTTPS origin: `<https://public.example>` (`APP_URL`, canonical SEO origin, Paynow callback origin)
- Workspace origin: `<https://app.example>` (`FRONTEND_URL`)
- Release directory: `<release-dir>` (for example a versioned directory containing `backend/` and `frontend/`)
- Web document root: `<web-root>` — must be the release's `backend/public`
- Web server and service manager: nginx/1.24.0 and systemd are present here, but the production choice/name is `<web-service>`
- Web user/group for `storage` and `bootstrap/cache`: `<web-user>:<web-group>`
- PHP-FPM socket/service: `<php-fpm-socket>` / `<php-fpm-service>`
- Database host/port/name/user and secret store path: `<db-host>`, `<db-port>`, `<db-name>`, `<db-user>`, `<secret-store-ref>` (password never in docs or shell history)
- TLS certificate, chain and key paths: `<tls-cert>`, `<tls-chain>`, `<tls-key>`
- Queue worker and scheduler unit names: `<queue-unit>`, `<schedule-unit>` (systemd, supervisor or equivalent)
- External uptime/pager endpoint and webhook: `<monitor-url>`, `<pager-webhook-ref>`

### 4.1 Web server and HTTPS

1. Point the production vhost at `<release-dir>/backend/public`; keep `backend/`, `frontend/src`, `.env`, `storage` and `bootstrap/cache` outside the served document root.
2. Send requests that are not files or directories to Laravel `index.php` so server-rendered public pages and API routes work. Serve the built SPA from `<release-dir>/frontend/dist` on the workspace origin (or the same host with deliberate route separation); do not serve the Vite dev server in production.
3. Configure TLS with `<tls-cert>`, `<tls-chain>` and `<tls-key>` on port 443; redirect HTTP to HTTPS only after the certificate is validated.
4. Set in the production environment: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=<https://public.example>`, `FRONTEND_URL=<https://app.example>`, `SESSION_SECURE_COOKIE=true`. Do not enable HSTS until HTTPS has been verified on the real origin.
5. Validate and reload using the real service manager, for example:

```bash
sudo nginx -t
sudo systemctl reload <web-service>
curl -fsSI https://<public-host>/ | sed -n '1,12p'
```

For an XAMPP Apache production host (not currently in use here), validate with `sudo /opt/lampp/bin/apachectl -t` and restart with `sudo /opt/lampp/lampp restart`. Do not assume these commands work on the unknown production host; verify them there first.

### 4.2 Environment secrets

Use the ignored `backend/.env` populated from the deployment secret store. Required keys are visible by name in `backend/.env.example`; never add values to that file or to documentation. Production review checklist:

- `APP_KEY` generated once and stored in the secret manager; rotating it later requires the re-encryption plan in `docs/BACKUP_RESTORE.md`.
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` from `<secret-store-ref>` (MariaDB driver confirmed locally; production host/credentials unknown).
- `QUEUE_CONNECTION=database` and `CACHE_STORE=database` match the verified local configuration.
- `SESSION_DRIVER=database`; `SESSION_SECURE_COOKIE=true` on HTTPS.
- Mail, Paynow, `FAILED_JOB_LOG_CHANNEL` and optional `LOG_SLACK_WEBHOOK_URL` set at the provider side and injected as secrets.
- `OBSERVABILITY_LOG_CHANNEL`, `OBSERVABILITY_LOG_LEVEL` and `OBSERVABILITY_LOG_MAX_FILES` retained from `.env.example` unless deliberately changed.

After any environment change:

```bash
cd <release-dir>/backend
php8.4 artisan config:clear --no-interaction
php8.4 artisan config:cache --no-interaction
php8.4 artisan optimize --no-interaction
php8.4 artisan queue:restart --no-interaction
sudo systemctl restart <queue-unit> <php-fpm-service>
curl -fsS https://<public-host>/api/health
```

`config:cache` is production-only; leave it cleared during local development.

### 4.3 Database and migrations

Supported local database is MariaDB 8.4.11 through `pdo_mysql` (driver name `mariadb`). Create the database and least-privilege user on the real host with the secret store/password prompt; do not paste credentials into commands or docs.

```bash
cd <release-dir>/backend
php8.4 artisan migrate:status --no-interaction
php8.4 artisan migrate --force --no-interaction
php8.4 artisan optimize:clear --no-interaction
```

Take a verified backup using `docs/BACKUP_RESTORE.md` before any migration on production. Current stage added no migration. `2026_11_21_033105_create_webhook_events_table` from Stage 8.4 is already applied locally.

### 4.4 Frontend asset build

```bash
cd <release-dir>/frontend
npm ci
npm run typecheck
npm run lint
npm run build
```

Publish `<release-dir>/frontend/dist` as static files. `npm run dev` is not a production server. The Laravel backend also serves the indexable public site from Blade views; do not replace those routes with the SPA build without a separate SEO decision.

### 4.5 Storage permissions

Observed local directories are `775 wayne:wayne`. On production substitute the real web user/group; avoid `777`.

```bash
cd <release-dir>
sudo chown -R <web-user>:<web-group> backend/storage backend/bootstrap/cache
sudo chmod -R ug+rwX backend/storage backend/bootstrap/cache
sudo find backend/storage backend/bootstrap/cache -type d -exec chmod 775 {} +
sudo find backend/storage backend/bootstrap/cache -type f -exec chmod 664 {} +
sudo chown <deploy-user>:<web-group> backend/.env
sudo chmod 640 backend/.env
stat -c '%a %U:%G %n' backend/storage backend/storage/framework backend/storage/logs backend/bootstrap/cache
```

`backend/storage/app/private` holds CMS originals and HR attachments and must be included in backups.

### 4.6 Queue worker

The local queue is `database`. Run one or more workers under the real supervisor with exactly the bounded options used in code:

```bash
cd <release-dir>/backend
php8.4 artisan queue:work --queue=default --tries=3 --timeout=30 --sleep=3 --no-interaction
```

To drain before a deploy/restart:

```bash
php8.4 artisan queue:work --queue=default --stop-when-empty --tries=3 --timeout=30 --no-interaction
php8.4 artisan queue:failed --no-interaction
```

No production queue unit, supervisor config or TMS worker process exists in this environment; worker supervision is BLOCKED until the host owner installs it.

### 4.7 Scheduler

`php8.4 artisan schedule:list` shows one task: `reservations:expire` every minute with `withoutOverlapping()`. For local verification run `php8.4 artisan schedule:work --no-interaction`. For production use the host scheduler, for example:

```cron
* * * * * cd <release-dir>/backend && php8.4 artisan schedule:run >> <release-dir>/backend/storage/logs/schedule-cron.log 2>&1
```

The existing local crontab contains only unrelated KairoCORE entries; no TorqueERP cron/scheduler unit is installed here.

### 4.8 Health checks and uptime

Application health:

```bash
curl -fsS -D - https://<public-host>/api/health
```

Expected: HTTP 200 and JSON `status: ok`, `checks.database.ok: true`, `checks.queue.ok: true`; degraded returns HTTP 503 with `status: degraded`. The endpoint reports database, queue size and failed-job count only. It does **not** prove that a worker or scheduler is running, that storage is writable, or that TLS is valid; monitor those separately.

Local verified command and result are in section 3. External uptime/pager configuration is BLOCKED: no monitor, pager webhook or scheduled check was configured in this stage.

Recommended external checks (to be installed by the infrastructure owner):

- Every 60 seconds: HTTPS GET `/api/health`, require 200 and `status=ok`; alert on 503, timeout or TLS error.
- TLS certificate expiry: alert below 14 days.
- Queue: alert when `checks.queue.pending` stays above an approved threshold or `failed_jobs.count` increases.
- Scheduler: alert when `reservations:expire` has not advanced the expiry of a known expired hold within two minutes.
- Logs: alert on the `queue.job.failed` event in `observability-*.log` once a pager channel is configured.

### 4.9 Logs

Observed log locations:

- `backend/storage/logs/laravel.log` — default `single` channel, level from `LOG_LEVEL`; not rotated by the application.
- `backend/storage/logs/observability-YYYY-MM-DD.log` — daily channel configured in `backend/config/logging.php`, `OBSERVABILITY_LOG_MAX_FILES` default 14; carries correlation IDs and redacted structured events.
- `failed_jobs` table — authoritative list of failed queue jobs; inspect with `php8.4 artisan queue:failed`.

Read-only inspection:

```bash
tail -n 50 <release-dir>/backend/storage/logs/laravel.log
tail -n 50 <release-dir>/backend/storage/logs/observability-$(date +%F).log
cd <release-dir>/backend && php8.4 artisan queue:failed --no-interaction
```

Production log rotation/shipping is not configured in this environment; configure it for `laravel.log` and ship `observability-*.log` to the central log store without recording secrets.

## 5. Rollback and forward recovery

1. **Before deploy:** verify the current health check, take a backup per `docs/BACKUP_RESTORE.md`, and record the current release/commit plus `migrate:status`.
2. **Pause writes:** stop `<queue-unit>`, then run `php8.4 artisan queue:work --stop-when-empty --tries=3 --timeout=30 --no-interaction`. Put the site in maintenance mode only with owner approval: `php8.4 artisan down --no-interaction` (recover with `php8.4 artisan up --no-interaction`).
3. **Code rollback (placeholders):** switch the web root/current symlink back to the previous verified release, e.g. `sudo ln -sfn <previous-release> <current-link>`, then `cd <release-dir>/backend && php8.4 artisan optimize:clear --no-interaction`, restart `<queue-unit>` and `<php-fpm-service>`, and re-run the health check.
4. **Config rollback:** restore the previous environment from the secret store, then run `config:clear`, `config:cache`, `optimize`, `queue:restart` and restart workers/PHP-FPM as in section 4.2.
5. **Database forward recovery:** fix the root cause, restore the verified backup if data was changed, then run `php8.4 artisan migrate --force --no-interaction` and `migrate:status`. Never rerun a partially applied migration blindly.
6. **Database rollback:** only after backup and with confirmation that the migration is reversible, run `php8.4 artisan migrate:rollback --step=1 --force --no-interaction`. Do not roll back `2026_11_21_033105_create_webhook_events_table` while callbacks may still be in flight; drain and verify first.
7. **Storage recovery:** restore `backend/storage/app/private` from the verified backup described in `docs/BACKUP_RESTORE.md`; do not point production at an unverified restore target.
8. **Frontend recovery:** redeploy the previous immutable `frontend/dist` artifact; do not rebuild from an unverified commit during an incident.
9. **After recovery:** confirm `curl -fsS https://<public-host>/api/health`, `queue:failed`, `schedule:list`, log tails and one business smoke test before declaring recovery complete.

## 6. Acceptance report

| Criterion | Status | Evidence / limitation |
|---|---|---|
| Actual target host: PHP and Node versions | PASS | section 1: PHP 8.4.27, Node 22.17.1, npm 10.9.2, Composer 2.7.1, Laravel 13.39.0 |
| Actual target host: web server | PASS (observed) / BLOCKED (production) | nginx 1.24.0 active on :80 with default root; XAMPP Apache 2.4.58 installed but not running; no TMS vhost. Production web server details not supplied. |
| HTTPS | BLOCKED | no :443 listener; TLS curl failed. Production cert/domain and redirect require host configuration. |
| MySQL/MariaDB | PASS (local) / BLOCKED (production details) | MariaDB server 8.4.11 via `pdo_mysql`; local db `torqueerp_test`; production host/user/secret store unknown. |
| Queue workers | PASS documented / NOT RUN installed | exact bounded command recorded; no TMS worker process or supervisor unit exists locally. |
| Scheduler | PASS documented / NOT RUN installed | `schedule:list` shows `reservations:expire` each minute; no TMS cron/unit installed locally. |
| Storage permissions | PASS documented / PARTIAL observed | local dirs are `775 wayne:wayne`; production web user/group is a placeholder. |
| Environment secrets | PASS | `.env.example` placeholders reviewed; production secret store unknown; no secret printed or written. |
| Migrations | PASS | `migrate --force` = `Nothing to migrate`; `migrate:status` ran; no migration added by this stage. |
| Asset build | PASS documented / NOT RUN this stage | `npm ci --dry-run` PASS; last known `npm run build` PASS in Stage 8.3; no frontend file changed. |
| Health checks | PASS | HTTP 200 `status=ok` from `/api/health`; payload and degradation behaviour recorded. |
| Rollback and forward recovery | PASS documented / NOT RUN in production | section 5 gives exact commands and gates; no production deploy/rollback performed. |
| Logs and uptime checks | PASS logs / BLOCKED uptime | log channels and paths verified; external uptime/pager not configured. |
| No production secrets in docs | PASS | this runbook, the six updated docs and `.env.example` contain names/placeholders only. |
| FAIL criteria | None | — |

## 7. Files changed, migrations and verification

- New: `docs/DEPLOYMENT.md`.
- Updated: `README.md`, `docs/CHANGELOG.md`, `docs/DEVELOPMENT.md`, `docs/DECISIONS.md`, `docs/BUSINESS_RULES.md`, `docs/REQUIREMENTS_TRACEABILITY.md`.
- Migrations: none. No application PHP, route, permission, seeder, frontend file, `.env` or `.env.example` credential was changed.
- Focused PHPUnit/full MariaDB suite: NOT RUN because no code changed. Last known full result from Stage 8.5: PASS, 338 tests / 2419 assertions.
- Pint: NOT RUN because no PHP file changed in this stage.
- Frontend typecheck/lint/build and browser runtime: NOT RUN because no frontend file changed and browser verification was out of scope.

## 8. Assumptions and known risks

- Assumptions: the current host is a local development environment; the production host details in section 4 are supplied by the infrastructure owner; Laravel serves public SEO pages and the built React app is the private workspace.
- `artisan serve` and Vite are development servers only. Using them in production would be unsafe and unsupported.
- No HTTPS/TLS, no TMS queue worker, no TMS scheduler and no external uptime monitor are installed on the observed host. Reservation expiry depends on the scheduler; notification delivery depends on the worker.
- XAMPP status output was partially permission-denied; do not rely on stale PID files as evidence of service health.
- Production web user/group is unknown. Wrong ownership or `777` permissions would create either runtime failures or a security exposure.
- Production `config:cache` must be rebuilt after environment changes; stale cache can mask corrected secrets or URLs.
- `laravel.log` uses the `single` channel and has no application-level rotation; configure logrotate/log shipping.
- `docker-compose.yml` contains development-only hardcoded database passwords; never use it as a production template.
- Upstream Composer advisories from Stage 8.1 remain BLOCKED; no dependency change was made here.
- No production deploy, backup restore, secret rotation, TLS renewal or pager delivery was performed in this stage.

## 9. Repeatable verification steps

```bash
cd /opt/lampp/htdocs/TMS_ERP
php -v; node -v; npm -v; php8.4 artisan --version || true
cd backend && php8.4 artisan --version && php8.4 artisan about --only=environment --json
cd ../backend && php8.4 artisan migrate:status --no-interaction && php8.4 artisan schedule:list --no-interaction
cd ../backend && php8.4 artisan queue:failed --no-interaction
curl -fsS -D - http://127.0.0.1:8000/api/health
cd ../backend && composer validate --no-check-publish --no-interaction
cd ../frontend && npm ci --dry-run --ignore-scripts
stat -c '%a %U:%G %n' ../backend/storage ../backend/storage/logs ../backend/bootstrap/cache
```

On the real production host, replace the local health URL with `https://<public-host>/api/health` and additionally verify TLS expiry, web service status, queue/scheduler unit status, log shipping and one external uptime check before closing the deployment.

## 10. Stage 8.8 release-candidate acceptance addendum

This runbook remains the authoritative deployment/rollback procedure. Stage 8.8 re-checked the local boundary and added a release-gate document; it did not change the host, application, dependencies or schema.

- Overall release status: **PARTIAL / BLOCKED — NOT production-ready**. Gates, owners, exact commands/outcomes and verification steps are in `docs/RELEASE_CHECKLIST.md`; the criterion-by-criterion report is `docs/CHANGELOG.md` (Stage 8.8).
- Re-checked locally: `curl -sS -o /dev/null -w '%{http_code}\n' --max-time 5 http://127.0.0.1:8000/api/health` = `200`; `curl -k -sS -o /dev/null -w '%{http_code}\n' --max-time 5 https://127.0.0.1:8000/api/health` = `000` with `SSL_ERROR_SYSCALL`. Section 1's conclusion (no TLS, no TMS vhost) is unchanged.
- Clean acceptance used the new disposable `torqueerp_stage88_acceptance_test` database with `migrate:fresh --force` (69 migrations). This is a test-only procedure; production migrations still follow section 4.3 and require a verified backup first.
- Focused/adjacent backend suites, full MariaDB suite (338 tests / 2419 assertions), frontend typecheck/lint/build and Stage 8.5 read-only restore verification all PASSED on this host. Stage 8.2 measurements remain local test-harness evidence, not production latency.
- Production blockers are unchanged: section 4 placeholders must be filled by the infrastructure owner, including TLS, real origins, web root, web user, secret store, worker/scheduler units, pager and monitoring. Off-site encrypted backups/PITR, dependency advisories, language list and legal/tax/payroll assumptions also remain open.
- Rollback/forward recovery: use section 5 verbatim. The concise incident checklist and the Stage 8.8 documentation-only rollback are in `docs/RELEASE_CHECKLIST.md` section 7. Never promote a failed or unverified restore target, and do not roll back `2026_11_21_033105_create_webhook_events_table` while callbacks may be in flight.
