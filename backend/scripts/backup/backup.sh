#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/common.sh"

SOURCE_DB="${BACKUP_SOURCE_DB:-}"
BACKUP_DIR="${BACKUP_DIR:-}"
PRIVATE_DIR="${BACKUP_PRIVATE_DIR:-/opt/lampp/htdocs/TMS_ERP/backend/storage/app/private}"
GPG_RECIPIENT="${GPG_RECIPIENT:-}"

if [[ -z "$SOURCE_DB" || -z "$BACKUP_DIR" ]]; then
    echo "Usage: BACKUP_SOURCE_DB=<db> BACKUP_DIR=<dir> [BACKUP_PRIVATE_DIR=<dir>] [GPG_RECIPIENT=<key-id>] $0" >&2
    exit 64
fi

require_tools mysql mysqldump gzip tar sha256sum php8.4
if [[ -n "$GPG_RECIPIENT" ]]; then
    require_tools gpg
fi
require_db_env
assert_test_db_name "$SOURCE_DB"

if ! db_exists "$SOURCE_DB"; then
    echo "BLOCKED: source database '$SOURCE_DB' does not exist." >&2
    exit 4
fi

mkdir -p "$BACKUP_DIR"
CREATED_AT="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
STAMP="${CREATED_AT//-/}"
STAMP="${STAMP//:/}"
PREFIX="${SOURCE_DB}-${STAMP}"
SQL_FILE="${BACKUP_DIR}/${PREFIX}.sql"
SQL_GZ="${SQL_FILE}.gz"
COUNTS_FILE="${BACKUP_DIR}/${PREFIX}.counts.tsv"
FILES_FILE="${BACKUP_DIR}/${PREFIX}.files.tsv"
MANIFEST_FILE="${BACKUP_DIR}/${PREFIX}.manifest.json"
ARTIFACTS=()

mysqldump_cli --single-transaction --quick --routines --triggers --hex-blob "$SOURCE_DB" >"$SQL_FILE"
gzip -c "$SQL_FILE" >"$SQL_GZ"
rm -f "$SQL_FILE"
ARTIFACTS+=("$SQL_GZ")

if [[ -d "$PRIVATE_DIR" ]]; then
    STORAGE_TGZ="${BACKUP_DIR}/${PREFIX}.private-storage.tar.gz"
    tar -czf "$STORAGE_TGZ" -C "$(dirname "$PRIVATE_DIR")" "$(basename "$PRIVATE_DIR")"
    ARTIFACTS+=("$STORAGE_TGZ")
fi

ENCRYPTION_MODE="none"
if [[ -n "$GPG_RECIPIENT" ]]; then
    for artifact in "${ARTIFACTS[@]}"; do
        gpg --batch --yes --trust-model always --encrypt --recipient "$GPG_RECIPIENT" --output "${artifact}.gpg" "$artifact"
        rm -f "$artifact"
    done
    ENCRYPTION_MODE="gpg"
fi

: >"$FILES_FILE"
for artifact in "${ARTIFACTS[@]}"; do
    artifact_path="$artifact"
    if [[ "$ENCRYPTION_MODE" == "gpg" ]]; then
        artifact_path="${artifact}.gpg"
    fi
    digest_output="$(sha256sum "$artifact_path")"
    digest="${digest_output%% *}"
    bytes="$(wc -c <"$artifact_path")"
    printf '%s\t%s\t%s\n' "$(basename "$artifact_path")" "$digest" "$bytes" >>"$FILES_FILE"
done

write_counts "$SOURCE_DB" "$COUNTS_FILE"
MYSQL_VERSION="$(mysql_cli -N -e 'SELECT VERSION()')"
MYSQLDUMP_VERSION="$(mysqldump --version)"

php8.4 "$SCRIPT_DIR/manifest.php" write \
    "$MANIFEST_FILE" \
    "$SOURCE_DB" \
    "$CREATED_AT" \
    "$MYSQL_VERSION" \
    "$MYSQLDUMP_VERSION" \
    "$ENCRYPTION_MODE" \
    "$COUNTS_FILE" \
    "$FILES_FILE"
php8.4 "$SCRIPT_DIR/manifest.php" validate "$MANIFEST_FILE"
printf '%s\n' "$MANIFEST_FILE" >"${BACKUP_DIR}/last_manifest_path.txt"

echo "BACKUP PASS"
echo "manifest=$MANIFEST_FILE"
echo "source_db=$SOURCE_DB"
echo "encryption_at_rest=$ENCRYPTION_MODE"
echo "artifacts=${#ARTIFACTS[@]}"
while IFS=$'\t' read -r artifact digest bytes; do
    echo "artifact=$artifact bytes=$bytes sha256=$digest"
done <"$FILES_FILE"
echo "counts:"
while IFS=$'\t' read -r table count; do
    echo "  $table=$count"
done <"$COUNTS_FILE"
