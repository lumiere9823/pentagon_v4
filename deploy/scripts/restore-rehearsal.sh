#!/usr/bin/env bash
# ==============================================================================
# Pentagon Page Builder - Isolated Backup Restore Rehearsal
# ==============================================================================
# Usage: ./restore-rehearsal.sh [path_to_backup_timestamp_directory]

set -euo pipefail

BACKUP_ROOT="${1:-}"
REHEARSAL_DB="${REHEARSAL_DB:-pentagon_rehearsal_restore}"
ASSET_RESTORE_ROOT=""
DATABASE_CREATED=0

cleanup() {
    local exit_code=$?

    if [ "$DATABASE_CREATED" -eq 1 ]; then
        dropdb --if-exists "$REHEARSAL_DB" >/dev/null 2>&1 || true
    fi

    if [ -n "$ASSET_RESTORE_ROOT" ] && [ -d "$ASSET_RESTORE_ROOT" ]; then
        rm -rf -- "$ASSET_RESTORE_ROOT"
    fi

    exit "$exit_code"
}
trap cleanup EXIT INT TERM

if [ -z "$BACKUP_ROOT" ]; then
    BACKUP_ROOT=$(find /var/backups/pentagon -mindepth 1 -maxdepth 1 -type d -print | sort -r | head -n 1)
fi

if [ -z "$BACKUP_ROOT" ] || [ ! -d "$BACKUP_ROOT" ]; then
    echo "ERROR: No backup directory found to rehearse." >&2
    exit 1
fi

BACKUP_ROOT=$(cd "$BACKUP_ROOT" && pwd -P)
echo "=== [1/7] Starting backup restore rehearsal: $BACKUP_ROOT ==="

echo "=== [2/7] Verifying SHA-256 checksums ==="
if [ ! -f "$BACKUP_ROOT/SHA256SUMS" ]; then
    echo "ERROR: SHA256SUMS is required for a restore rehearsal." >&2
    exit 1
fi
(
    cd "$BACKUP_ROOT"
    sha256sum -c SHA256SUMS
)

mapfile -t DUMP_FILES < <(find "$BACKUP_ROOT" -maxdepth 1 -type f -name '*.dump' -print)
mapfile -t ASSET_ARCHIVES < <(find "$BACKUP_ROOT" -maxdepth 1 -type f -name 'media_and_releases_*.tar.gz' -print)

if [ "${#DUMP_FILES[@]}" -ne 1 ]; then
    echo "ERROR: Expected exactly one PostgreSQL .dump file; found ${#DUMP_FILES[@]}." >&2
    exit 1
fi
if [ "${#ASSET_ARCHIVES[@]}" -ne 1 ]; then
    echo "ERROR: Expected exactly one media_and_releases archive; found ${#ASSET_ARCHIVES[@]}." >&2
    exit 1
fi

DUMP_FILE="${DUMP_FILES[0]}"
ASSET_ARCHIVE="${ASSET_ARCHIVES[0]}"

echo "=== [3/7] Creating transient database: $REHEARSAL_DB ==="
dropdb --if-exists "$REHEARSAL_DB" >/dev/null 2>&1 || true
createdb "$REHEARSAL_DB"
DATABASE_CREATED=1

echo "=== [4/7] Restoring PostgreSQL dump ==="
pg_restore --exit-on-error --clean --if-exists --dbname "$REHEARSAL_DB" "$DUMP_FILE"

echo "=== [5/7] Verifying restored schema and row counts ==="
REQUIRED_TABLES=(pages page_revisions page_releases media_assets)
for table in "${REQUIRED_TABLES[@]}"; do
    exists=$(psql --dbname "$REHEARSAL_DB" --tuples-only --no-align --command \
        "SELECT CASE WHEN to_regclass('public.${table}') IS NULL THEN 0 ELSE 1 END;")
    if [ "$exists" != "1" ]; then
        echo "ERROR: Required restored table is missing: $table" >&2
        exit 1
    fi
done

PAGES_COUNT=$(psql --dbname "$REHEARSAL_DB" --tuples-only --no-align --command 'SELECT COUNT(*) FROM pages;')
REVISIONS_COUNT=$(psql --dbname "$REHEARSAL_DB" --tuples-only --no-align --command 'SELECT COUNT(*) FROM page_revisions;')
RELEASES_COUNT=$(psql --dbname "$REHEARSAL_DB" --tuples-only --no-align --command 'SELECT COUNT(*) FROM page_releases;')
MEDIA_COUNT=$(psql --dbname "$REHEARSAL_DB" --tuples-only --no-align --command 'SELECT COUNT(*) FROM media_assets;')

for count in "$PAGES_COUNT" "$REVISIONS_COUNT" "$RELEASES_COUNT" "$MEDIA_COUNT"; do
    if ! [[ "$count" =~ ^[0-9]+$ ]]; then
        echo "ERROR: Restored row count is not numeric: $count" >&2
        exit 1
    fi
done

echo "=== [6/7] Extracting and validating media/release archive ==="
ASSET_RESTORE_ROOT=$(mktemp -d)
tar -xzf "$ASSET_ARCHIVE" -C "$ASSET_RESTORE_ROOT"

if [ ! -d "$ASSET_RESTORE_ROOT/public/media" ]; then
    echo "ERROR: Restored archive is missing public/media." >&2
    exit 1
fi
if [ ! -d "$ASSET_RESTORE_ROOT/private/releases" ]; then
    echo "ERROR: Restored archive is missing private/releases." >&2
    exit 1
fi

echo "=== [7/7] Restore rehearsal metrics ==="
echo " - Pages: $PAGES_COUNT"
echo " - Revisions: $REVISIONS_COUNT"
echo " - Releases: $RELEASES_COUNT"
echo " - Media Assets: $MEDIA_COUNT"
echo "=== SUCCESS: Backup restore rehearsal completed with verified database and assets. ==="
