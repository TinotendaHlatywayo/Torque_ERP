#!/usr/bin/env bash
set -euo pipefail

BACKUP_KEY_TABLES=(migrations branches users products categories units customers stock_levels stock_movements sales sale_items payments receipts audit_logs)

require_tools() {
    local tool
    for tool in "$@"; do
        if ! command -v "$tool" >/dev/null 2>&1; then
            echo "BLOCKED: required tool '$tool' is not installed." >&2
            exit 3
        fi
    done
}

require_db_env() {
    if [[ -z "${DB_USERNAME:-}" ]]; then
        echo "BLOCKED: DB_USERNAME must be set. Source backend/.env in the shell before running this script." >&2
        exit 3
    fi
}

assert_test_db_name() {
    local db="$1"
    if [[ ! "$db" =~ ^torqueerp_[a-z0-9_]+_test$ ]] && [[ "$db" != "torqueerp_test" ]] && [[ "$db" != "torqueerp_stage12_test" ]]; then
        echo "Refusing database '$db': only non-production torqueerp_*_test databases are allowed." >&2
        exit 2
    fi
    if [[ "$db" == *prod* || "$db" == *live* ]]; then
        echo "Refusing database '$db': production-like names are never allowed in this drill." >&2
        exit 2
    fi
}

assert_restore_target() {
    local target="$1"
    local source="$2"
    assert_test_db_name "$target"
    if [[ "$target" == "torqueerp_test" || "$target" == "torqueerp_stage12_test" ]]; then
        echo "Refusing to overwrite shared test database '$target' during a restore drill." >&2
        exit 2
    fi
    if [[ "$target" == "$source" ]]; then
        echo "Refusing to restore into the source database '$source'." >&2
        exit 2
    fi
}

mysql_cli() {
    MYSQL_PWD="${DB_PASSWORD:-}" mysql --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-3306}" --user="$DB_USERNAME" "$@"
}

mysqldump_cli() {
    MYSQL_PWD="${DB_PASSWORD:-}" mysqldump --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-3306}" --user="$DB_USERNAME" "$@"
}

db_exists() {
    local db="$1"
    [[ -n "$(mysql_cli -N -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$db'")" ]]
}

write_counts() {
    local db="$1"
    local outfile="$2"
    : >"$outfile"
    local table
    for table in "${BACKUP_KEY_TABLES[@]}"; do
        local count
        count="$(mysql_cli -N -e "SELECT COUNT(*) FROM \`$db\`.\`$table\`")"
        printf '%s\t%s\n' "$table" "$count" >>"$outfile"
    done
}

manifest_field() {
    local manifest="$1"
    local field="$2"
    php8.4 "$SCRIPT_DIR/manifest.php" field "$manifest" "$field"
}

manifest_counts() {
    local manifest="$1"
    php8.4 "$SCRIPT_DIR/manifest.php" counts "$manifest"
}
