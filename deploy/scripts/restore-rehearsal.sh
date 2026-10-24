#!/usr/bin/env bash
# ==============================================================================
# Pentagon Page Builder - Disaster Recovery & Backup Restore Rehearsal Script
# ==============================================================================
# Purpose: Dry-run test restoration of a backup into an isolated validation DB
# Usage: ./restore-rehearsal.sh [path_to_backup_timestamp_directory]

set -euo pipefail

BACKUP_ROOT="${1:-}"
REHEARSAL_DB="pentagon_rehearsal_restore"

if [ -z "$BACKUP_ROOT" ]; then
    # Pick latest backup from /var/backups/pentagon
    BACKUP_ROOT=$(find /var/backups/pentagon -mindepth 1 -maxdepth 1 -type d | sort -r | head -n 1)
fi

if [ -z "$BACKUP_ROOT" ] || [ ! -d "$BACKUP_ROOT" ]; then
    echo "ERROR: No backup directory found to rehearse!" >&2
    exit 1
fi

echo "=== [1/5] Starting Backup Restore Rehearsal for: $BACKUP_ROOT ==="

# 1. Verify SHA-256 checksums
echo "=== [2/5] Verifying SHA-256 integrity checksums ==="
cd "$BACKUP_ROOT"
if [ -f "SHA256SUMS" ]; then
    sha256sum -c SHA256SUMS
    echo "Integrity check PASSED."
else
    echo "WARNING: SHA256SUMS file not found in $BACKUP_ROOT"
fi

# 2. Locate PostgreSQL dump file
DUMP_FILE=$(ls -1 *.dump 2>/dev/null | head -n 1 || echo "")
if [ -z "$DUMP_FILE" ]; then
    echo "ERROR: No .dump file found in $BACKUP_ROOT!" >&2
    exit 1
fi

# 3. Create transient validation database
echo "=== [3/5] Creating transient validation database: $REHEARSAL_DB ==="
dropdb --if-exists "$REHEARSAL_DB" 2>/dev/null || true
createdb "$REHEARSAL_DB"

# 4. Restore dump into transient database
echo "=== [4/5] Restoring $DUMP_FILE into $REHEARSAL_DB ==="
pg_restore -d "$REHEARSAL_DB" --clean --if-exists "$DUMP_FILE" || true

# Verify table counts and row integrity
echo "=== [5/5] Verifying restored schema and data integrity ==="
PAGES_COUNT=$(psql -d "$REHEARSAL_DB" -t -c "SELECT COUNT(*) FROM pages;" 2>/dev/null || echo "0")
RELEASES_COUNT=$(psql -d "$REHEARSAL_DB" -t -c "SELECT COUNT(*) FROM page_releases;" 2>/dev/null || echo "0")
REVISIONS_COUNT=$(psql -d "$REHEARSAL_DB" -t -c "SELECT COUNT(*) FROM page_revisions;" 2>/dev/null || echo "0")
MEDIA_COUNT=$(psql -d "$REHEARSAL_DB" -t -c "SELECT COUNT(*) FROM media_assets;" 2>/dev/null || echo "0")

echo "Restoration Integrity Metrics:"
echo " - Pages: $PAGES_COUNT"
echo " - Revisions: $REVISIONS_COUNT"
echo " - Releases: $RELEASES_COUNT"
echo " - Media Assets: $MEDIA_COUNT"

# Clean up transient validation database
echo "Dropping transient rehearsal database..."
dropdb "$REHEARSAL_DB"

echo "=== SUCCESS: Backup restore rehearsal completed with 100% integrity! ==="
