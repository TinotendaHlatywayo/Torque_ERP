# Backup, restore and retention drill (Stage 8.5)

This is the operational contract for TorqueERP backups. It is a proposal for production operations; the commands in this file were executed only against non-production databases. No production database, production credential or live off-site target was touched.

## Safety rules
- A backup is not accepted until a restore into a different non-production database has been demonstrated and verified.
- Never restore over a production database. The scripts refuse database names that do not match `torqueerp_*_test` and refuse `torqueerp_test` / `torqueerp_stage12_test` as restore targets.
- Never restore into the source database. `restore.sh` compares the manifest source with the requested target and exits before any DDL.
- Credentials are read from the shell environment (`DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`). No script prints a password, writes one to the manifest, or stores one in the repository.
- The source database is read only during backup. The drill only creates/drops its own reserved databases `torqueerp_stage85_source_test` and `torqueerp_stage85_restore_test`.

## Scope and tooling
Available host tools were confirmed before the drill:

| Tool | Version / result |
|---|---|
| `mysql` | Ver 14.14 Distrib 5.7.24 |
| `mysqldump` | Ver 10.13 Distrib 5.7.24 |
| Database server `SELECT VERSION()` | 8.4.11 |
| `tar` | GNU tar 1.35 |
| `gzip` | 1.12 |
| `php8.4` | 8.4.27 |
| `gpg` | available (optional encryption path) |
| `sha256sum` | available |

A backup contains:
- one `mysqldump --single-transaction --quick --routines --triggers --hex-blob` SQL dump, gzipped;
- one `tar.gz` of `backend/storage/app/private` (CMS originals, HR attachments and other private files) when the directory exists;
- one `torqueerp-backup-manifest/v1` JSON manifest with UTC timestamp, source database, tool versions, key-table counts, artifact names, byte sizes and SHA-256 digests, plus declared encryption/off-site state.

If `GPG_RECIPIENT` is set, each artifact is encrypted with GPG before the plaintext is removed and the manifest records `encryption.at_rest = gpg`. Without it the manifest records `none`. This local drill used `none`; the GPG path is implemented but NOT RUN.

## Scripts
- `backend/scripts/backup/common.sh` — shared environment validation, database-name guards and count queries.
- `backend/scripts/backup/manifest.php` — manifest writer/validator and read helpers (`validate`, `field`, `counts`, `files`).
- `backend/scripts/backup/backup.sh` — creates an artifact set and manifest for `BACKUP_SOURCE_DB`.
- `backend/scripts/backup/restore.sh` — verifies checksums, creates a separate non-production target, restores, and verifies counts plus read-only workflow invariants. `RESTORE_VERIFY_ONLY=1` re-runs verification without writing.
- `backend/scripts/backup/fixture.sql` — deterministic synthetic rows only (no production data) used by the drill.
- `backend/scripts/backup/run_drill.sh` — seeds a temporary source from the migrated `torqueerp_stage12_test` template, applies the fixture, backs up, restores and verifies.

## Proposed schedule (not installed)
Documentation only; no crontab entry was added in this stage. The infrastructure owner installs and monitors it:

```cron
30 2 * * * cd /srv/tms/backend && set -a && . ./.env && set +a && BACKUP_SOURCE_DB=<configured-production-db> BACKUP_DIR=/var/backups/torqueerp GPG_RECIPIENT=<key-fingerprint> scripts/backup/backup.sh >> /var/log/torqueerp-backup.log 2>&1
```

After a successful local backup, copy the manifest and all listed artifacts to the off-site target over TLS with server-side encryption enabled. The off-site target is the responsibility of the infrastructure owner and is not configured in this environment (`manifest.offsite.target = "not configured"` in the drill). Alert on a non-zero backup exit code and on any day with no new manifest.

## Retention (proposed)
- Local: 14 daily artifact sets.
- Off-site: 90 daily artifact sets plus 12 monthly sets taken on the first day of each month.
- Keep the newest successful set and the newest set with a PASS restore verification even if they exceed the age limit.
- Pruning/lifecycle enforcement is the infrastructure owner's responsibility and is BLOCKED until an off-site target exists. No prune command was invented.

## RPO / RTO proposal
- Achievable RPO with the nightly full dump is up to 24 hours plus off-site transfer time. This is the honest current figure.
- Target RPO of 15 minutes is BLOCKED: it requires binary logging and incremental/PITR archiving, which are not configured. Do not claim 15-minute recovery from a nightly dump.
- Proposed RTO is 2 hours for database plus private-storage restore and verification into non-production. The drill completed in under a minute on a synthetic fixture; that is not a production-volume SLA and must be re-measured with production-sized data.
- The business owner (TMS) approves RPO/RTO; the infrastructure owner implements the mechanisms and reports misses.

## Restore responsibilities
| Role | Responsibility |
|---|---|
| Infrastructure owner | Installs the schedule, owns off-site storage, encryption keys, retention/pruning and backup-job alerting |
| On-call engineer | Runs `restore.sh` into a separate non-production database, records exact commands/outputs and confirms checksums |
| Application owner (TMS) | Confirms which backup to use, approves the restore and signs off record counts and key workflows |
| Security owner | Owns credential/key rotation and access review for backup storage |

## Exact drill commands (executed)
From the repo root, with credentials sourced from the local backend environment:

```bash
set -a; . backend/.env; set +a
bash backend/scripts/backup/run_drill.sh
```

The final run produced:
- source `torqueerp_stage85_source_test`, target `torqueerp_stage85_restore_test`, template `torqueerp_stage12_test`;
- manifest `/tmp/opencode/tms-backup-drill/torqueerp_stage85_source_test-20261121T035551Z.manifest.json`;
- SQL artifact `torqueerp_stage85_source_test-20261121T035551Z.sql.gz`, 16820 bytes, SHA-256 `e46f22560597e10f86d2aba3fdb0f0ebf11c2034d370086e60eb8972dca1360a`;
- private-storage artifact `torqueerp_stage85_source_test-20261121T035551Z.private-storage.tar.gz`, 3018 bytes, SHA-256 `2db88e685428561a9ab6b115cf45dc902092f69c82b4dcdc1fb1990c4535ac8d`;
- `PASS manifest`, `BACKUP PASS`, `restore PASS target=torqueerp_stage85_restore_test source=torqueerp_stage85_source_test`, `storage_archive PASS integrity_only`, `RESTORE VERIFICATION PASS`, `DRILL PASS`.

Standalone commands for an operator:

```bash
set -a; . backend/.env; set +a
BACKUP_SOURCE_DB=torqueerp_stage85_source_test BACKUP_DIR=/tmp/opencode/tms-backup-drill \
  bash backend/scripts/backup/backup.sh
RESTORE_TARGET_DB=torqueerp_stage85_restore_test \
  BACKUP_MANIFEST=/tmp/opencode/tms-backup-drill/last_manifest_path.txt \
  bash backend/scripts/backup/restore.sh
RESTORE_TARGET_DB=torqueerp_stage85_restore_test \
  BACKUP_MANIFEST=/tmp/opencode/tms-backup-drill/last_manifest_path.txt \
  RESTORE_VERIFY_ONLY=1 bash backend/scripts/backup/restore.sh
php8.4 backend/scripts/backup/manifest.php validate <manifest.json>
```

If the target already exists, `restore.sh` stops unless `RESTORE_REPLACE=1` is set; the drill script creates a fresh target instead.

## Verification performed
Record counts matched the manifest exactly (69 migrations, 1 branch, 1 user, 1 product, 1 category, 1 unit, 1 customer, 1 stock level, 2 stock movements, 1 sale, 1 sale item, 1 payment, 1 receipt, 1 audit log). Read-only workflow checks all returned 0 violating rows:

- `stock_ledger_projection`: `stock_levels.quantity_on_hand` equals the sum of `stock_movements.quantity` per branch/product.
- `no_negative_or_over_reserved_stock`: no negative on-hand/reserved and reserved never exceeds on-hand.
- `posted_sale_has_receipt`: every posted sale has one receipt.
- `sale_paid_matches_payments`: `sales.paid_minor` equals summed payment amounts.
- `sale_total_matches_items`: `sales.total_minor` equals summed sale-item totals.
- `stock_idempotency_unique`: no duplicate branch/idempotency-key stock movement.
- `product_branch_integrity`: every product resolves to an existing branch.

The same suite passed again in `RESTORE_VERIFY_ONLY=1` mode, proving verification is repeatable and read-only. Read-only queries on the source database after the restore still showed 1 branch, 1 sale, 2 stock movements and 1 receipt, proving the source was not overwritten.

Guard checks also passed: restoring into the source database was refused (`Refusing to restore into the source database ...`), and a production-like name was refused (`Refusing database 'torqueerp_prod': only non-production torqueerp_*_test databases are allowed.`).

## Secret rotation
No real secret was rotated in this stage. The following is the documented procedure:

1. DB credentials: create a new least-privilege database user/secret in the secret manager, update `.env` on app and worker nodes, run `php8.4 artisan config:clear && php8.4 artisan config:cache`, restart PHP-FPM and queue workers, confirm `/api/health`, then revoke the old user.
2. Off-site/S3 credentials: rotate the IAM key or service credential, update the backup job environment, run one backup plus off-site copy, verify the new manifest and artifacts, then disable the old key.
3. GPG key: generate/add the new recipient key, update `GPG_RECIPIENT`, run a new encrypted backup and a restores drill, then securely retire the old private key only after all artifacts encrypted to it have aged out of retention. Re-encrypting older artifacts is a separate decision; do not delete them just to complete a rotation.
4. Paynow integration key and webhook tokens: rotate at the provider, update the backend environment, clear cached config, verify a sandbox hash/callback and check that no old key remains in logs or repository.
5. Mail, Slack/webhook and other notification credentials: rotate at the provider, update the environment, trigger a non-production notification and a failed-job hook to confirm delivery; the redaction rules must still hide the new secret.
6. `APP_KEY`: treat as a special case. Rotating it invalidates encrypted attributes, sessions and cookies. Do not rotate in production without a tested re-encryption/migration plan and a maintenance window; rehearse in non-production first.
7. After any rotation that affects backup credentials or encryption, re-run the full backup/restore drill and record the new manifest path and outcomes.

## Known risks and blockers
- No off-site target, retention job or cron entry exists yet: schedule, off-site storage and pruning are documented proposals, not installed controls.
- GPG encryption path is available but NOT RUN; the demonstrated artifact was `encryption_at_rest=none` on a local filesystem.
- 15-minute RPO is BLOCKED without binary logging/PITR; nightly dumps alone cannot meet it.
- RTO is an unmeasured proposal at production scale.
- The drill uses a synthetic 14-table fixture; it does not prove every domain table or a production-sized restore. Expand the key-table list and re-measure when production data exists.
- The 5.7 client against an 8.4 server worked for this dump. Re-verify the toolchain after a server or client upgrade.
- No production database, live off-site transfer, real secret rotation or browser verification was performed in this stage.
