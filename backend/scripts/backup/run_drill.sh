#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/common.sh"

TEMPLATE_DB="${SEED_TEMPLATE_DB:-torqueerp_stage12_test}"
SOURCE_DB="${DRILL_SOURCE_DB:-torqueerp_stage85_source_test}"
TARGET_DB="${DRILL_TARGET_DB:-torqueerp_stage85_restore_test}"
BACKUP_DIR="${DRILL_BACKUP_DIR:-/tmp/opencode/tms-backup-drill}"

require_tools mysql mysqldump gzip tar sha256sum php8.4
require_db_env
assert_test_db_name "$TEMPLATE_DB"
assert_test_db_name "$SOURCE_DB"
assert_test_db_name "$TARGET_DB"
for reserved in torqueerp_test torqueerp_stage12_test; do
    if [[ "$SOURCE_DB" == "$reserved" || "$TARGET_DB" == "$reserved" ]]; then
        echo "Refusing to use shared test database '$reserved' as a drill source or target." >&2
        exit 2
    fi
done
if [[ "$SOURCE_DB" == "$TARGET_DB" || "$SOURCE_DB" == "$TEMPLATE_DB" || "$TARGET_DB" == "$TEMPLATE_DB" ]]; then
    echo "Drill source, target and template databases must be different." >&2
    exit 2
fi
if ! db_exists "$TEMPLATE_DB"; then
    echo "BLOCKED: template database '$TEMPLATE_DB' does not exist." >&2
    exit 4
fi

for db in "$SOURCE_DB" "$TARGET_DB"; do
    if db_exists "$db"; then
        mysql_cli -e "DROP DATABASE \`$db\`"
        echo "recreated_dropped=$db"
    fi
done
mysql_cli -e "CREATE DATABASE \`$SOURCE_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

mysqldump_cli --single-transaction --quick "$TEMPLATE_DB" | mysql_cli "$SOURCE_DB"
mysql_cli "$SOURCE_DB" <"$SCRIPT_DIR/fixture.sql"
echo "fixture PASS source=$SOURCE_DB template=$TEMPLATE_DB"

BACKUP_SOURCE_DB="$SOURCE_DB" BACKUP_DIR="$BACKUP_DIR" "$SCRIPT_DIR/backup.sh"
MANIFEST="$(<"${BACKUP_DIR}/last_manifest_path.txt")"
RESTORE_TARGET_DB="$TARGET_DB" BACKUP_MANIFEST="$MANIFEST" "$SCRIPT_DIR/restore.sh"
RESTORE_TARGET_DB="$TARGET_DB" BACKUP_MANIFEST="$MANIFEST" RESTORE_VERIFY_ONLY=1 "$SCRIPT_DIR/restore.sh"
echo "DRILL PASS source=$SOURCE_DB target=$TARGET_DB manifest=$MANIFEST"
