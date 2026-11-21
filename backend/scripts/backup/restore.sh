#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/common.sh"

TARGET_DB="${RESTORE_TARGET_DB:-}"
MANIFEST="${BACKUP_MANIFEST:-}"
BACKUP_DIR="${BACKUP_DIR:-}"
REPLACE="${RESTORE_REPLACE:-0}"
VERIFY_ONLY="${RESTORE_VERIFY_ONLY:-0}"

if [[ -z "$MANIFEST" && -n "$BACKUP_DIR" && -f "${BACKUP_DIR}/last_manifest_path.txt" ]]; then
    MANIFEST="$(<"${BACKUP_DIR}/last_manifest_path.txt")"
fi
if [[ -z "$TARGET_DB" || -z "$MANIFEST" ]]; then
    echo "Usage: RESTORE_TARGET_DB=<db> BACKUP_MANIFEST=<manifest.json> [RESTORE_REPLACE=1] [RESTORE_VERIFY_ONLY=1] $0" >&2
    exit 64
fi
if [[ ! -f "$MANIFEST" ]]; then
    echo "BLOCKED: manifest '$MANIFEST' does not exist." >&2
    exit 4
fi

require_tools mysql gzip tar sha256sum php8.4
require_db_env
php8.4 "$SCRIPT_DIR/manifest.php" validate "$MANIFEST"

SOURCE_DB="$(manifest_field "$MANIFEST" source_db)"
ENCRYPTION="$(manifest_field "$MANIFEST" encryption)"
assert_restore_target "$TARGET_DB" "$SOURCE_DB"

if [[ "$ENCRYPTION" == "gpg" ]]; then
    require_tools gpg
fi

MANIFEST_DIR="$(cd "$(dirname "$MANIFEST")" && pwd)"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
REPORT_FILE="${MANIFEST_DIR}/verify-${TARGET_DB}-${STAMP}.tsv"
TMP_SQL=""

cleanup() {
    if [[ -n "$TMP_SQL" ]]; then
        rm -f "$TMP_SQL"
    fi
}
trap cleanup EXIT

while IFS=$'\t' read -r artifact digest bytes; do
    artifact_path="${MANIFEST_DIR}/${artifact}"
    if [[ ! -f "$artifact_path" ]]; then
        echo "BLOCKED: manifest artifact '$artifact' is missing." >&2
        exit 4
    fi
    actual_digest_output="$(sha256sum "$artifact_path")"
    actual_digest="${actual_digest_output%% *}"
    actual_bytes="$(wc -c <"$artifact_path")"
    if [[ "$actual_digest" != "$digest" || "$actual_bytes" != "$bytes" ]]; then
        echo "BLOCKED: manifest artifact '$artifact' failed checksum/size verification." >&2
        exit 4
    fi
    echo "artifact PASS name=$artifact bytes=$actual_bytes"
done < <(php8.4 "$SCRIPT_DIR/manifest.php" files "$MANIFEST")

if [[ "$VERIFY_ONLY" != "1" ]]; then
    if db_exists "$TARGET_DB"; then
        if [[ "$REPLACE" != "1" ]]; then
            echo "BLOCKED: target '$TARGET_DB' already exists. Set RESTORE_REPLACE=1 to replace this non-production database." >&2
            exit 4
        fi
        mysql_cli -e "DROP DATABASE \`$TARGET_DB\`"
        echo "target_recreated=$TARGET_DB"
    fi
    mysql_cli -e "CREATE DATABASE \`$TARGET_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

    SQL_ARTIFACT=""
    STORAGE_ARTIFACT=""
    while IFS=$'\t' read -r artifact digest bytes; do
        case "$artifact" in
            *.sql.gz|*.sql.gz.gpg) SQL_ARTIFACT="$artifact" ;;
            *.private-storage.tar.gz|*.private-storage.tar.gz.gpg) STORAGE_ARTIFACT="$artifact" ;;
        esac
    done < <(php8.4 "$SCRIPT_DIR/manifest.php" files "$MANIFEST")

    if [[ -z "$SQL_ARTIFACT" ]]; then
        echo "BLOCKED: manifest has no SQL dump artifact." >&2
        exit 4
    fi

    TMP_SQL="$(mktemp "${MANIFEST_DIR}/.restore-${TARGET_DB}-XXXXXX.sql")"
    SQL_SOURCE="${MANIFEST_DIR}/${SQL_ARTIFACT}"
    if [[ "$SQL_ARTIFACT" == *.gpg ]]; then
        TMP_GZ="${TMP_SQL}.gz"
        gpg --batch --yes --decrypt --output "$TMP_GZ" "$SQL_SOURCE"
        gzip -dc "$TMP_GZ" >"$TMP_SQL"
        rm -f "$TMP_GZ"
    else
        gzip -dc "$SQL_SOURCE" >"$TMP_SQL"
    fi
    mysql_cli "$TARGET_DB" <"$TMP_SQL"
    echo "restore PASS target=$TARGET_DB source=$SOURCE_DB"

    if [[ -n "$STORAGE_ARTIFACT" ]]; then
        TMP_TGZ="${MANIFEST_DIR}/.verify-storage-${TARGET_DB}-${STAMP}.tar.gz"
        STORAGE_SOURCE="${MANIFEST_DIR}/${STORAGE_ARTIFACT}"
        if [[ "$STORAGE_ARTIFACT" == *.gpg ]]; then
            gpg --batch --yes --decrypt --output "$TMP_TGZ" "$STORAGE_SOURCE"
            STORAGE_SOURCE="$TMP_TGZ"
        fi
        gzip -t "$STORAGE_SOURCE"
        tar -tzf "$STORAGE_SOURCE" >/dev/null
        rm -f "$TMP_TGZ"
        echo "storage_archive PASS integrity_only"
    fi
else
    if ! db_exists "$TARGET_DB"; then
        echo "BLOCKED: target '$TARGET_DB' does not exist in verify-only mode." >&2
        exit 4
    fi
    echo "verify_only=1 target=$TARGET_DB"
fi

: >"$REPORT_FILE"
FAILED=0

while IFS=$'\t' read -r table expected; do
    actual="$(mysql_cli -N -e "SELECT COUNT(*) FROM \`$TARGET_DB\`.\`$table\`")"
    if [[ "$actual" == "$expected" ]]; then
        outcome="PASS"
    else
        outcome="FAIL"
        FAILED=1
    fi
    printf 'count.%s\t%s\t%s\t%s\n' "$table" "$actual" "$expected" "$outcome" >>"$REPORT_FILE"
    echo "count.$table=$actual expected=$expected $outcome"
done < <(manifest_counts "$MANIFEST")

INVARIANTS=(
    "stock_ledger_projection|SELECT COUNT(*) FROM (SELECT sl.id FROM $TARGET_DB.stock_levels sl LEFT JOIN (SELECT branch_id, product_id, SUM(quantity) AS total FROM $TARGET_DB.stock_movements GROUP BY branch_id, product_id) m ON m.branch_id=sl.branch_id AND m.product_id=sl.product_id WHERE sl.quantity_on_hand <> COALESCE(m.total,0)) x"
    "no_negative_or_over_reserved_stock|SELECT COUNT(*) FROM $TARGET_DB.stock_levels WHERE quantity_on_hand < 0 OR quantity_reserved < 0 OR quantity_reserved > quantity_on_hand"
    "posted_sale_has_receipt|SELECT COUNT(*) FROM $TARGET_DB.sales s LEFT JOIN $TARGET_DB.receipts r ON r.sale_id=s.id WHERE s.status='posted' AND r.id IS NULL"
    "sale_paid_matches_payments|SELECT COUNT(*) FROM $TARGET_DB.sales s LEFT JOIN (SELECT sale_id, SUM(amount_minor) AS paid FROM $TARGET_DB.payments GROUP BY sale_id) p ON p.sale_id=s.id WHERE s.paid_minor <> COALESCE(p.paid,0)"
    "sale_total_matches_items|SELECT COUNT(*) FROM $TARGET_DB.sales s LEFT JOIN (SELECT sale_id, SUM(total_minor) AS total FROM $TARGET_DB.sale_items GROUP BY sale_id) i ON i.sale_id=s.id WHERE s.total_minor <> COALESCE(i.total,0)"
    "stock_idempotency_unique|SELECT COUNT(*) FROM (SELECT branch_id, idempotency_key FROM $TARGET_DB.stock_movements GROUP BY branch_id, idempotency_key HAVING COUNT(*)>1) x"
    "product_branch_integrity|SELECT COUNT(*) FROM $TARGET_DB.products p LEFT JOIN $TARGET_DB.branches b ON b.id=p.branch_id WHERE b.id IS NULL"
)

for invariant in "${INVARIANTS[@]}"; do
    name="${invariant%%|*}"
    sql="${invariant#*|}"
    actual="$(mysql_cli -N -e "$sql")"
    if [[ "$actual" == "0" ]]; then
        outcome="PASS"
    else
        outcome="FAIL"
        FAILED=1
    fi
    printf 'workflow.%s\t%s\t0\t%s\n' "$name" "$actual" "$outcome" >>"$REPORT_FILE"
    echo "workflow.$name=$actual expected=0 $outcome"
done

echo "verification_report=$REPORT_FILE"
if [[ "$FAILED" -ne 0 ]]; then
    echo "RESTORE VERIFICATION FAIL"
    exit 1
fi
echo "RESTORE VERIFICATION PASS"
