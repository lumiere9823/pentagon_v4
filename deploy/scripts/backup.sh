#!/usr/bin/env bash
# ==============================================================================
# Pentagon Page Builder - Automated Database & Asset Backup Script
# ==============================================================================
# Usage: ./backup.sh [destination_dir] (default: /var/backups/pentagon)

set -euo pipefail

BACKUP_DEST="${1:-/var/backups/pentagon}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_DIR="$BACKUP_DEST/$TIMESTAMP"
APP_ROOT="/var/www/pentagon"
SHARED_STORAGE="$APP_ROOT/shared/storage"
KEEP_DAYS=7

mkdir -p "$BACKUP_DIR"

echo "=== [1/4] Starting Backup: $TIMESTAMP -> $BACKUP_DIR ==="

# Source DB credentials from shared .env if available
if [ -f "$APP_ROOT/shared/.env" ]; then
    DB_DATABASE=$(grep -E "^DB_DATABASE=" "$APP_ROOT/shared/.env" | cut -d '=' -f2 | tr -d '"' | tr -d "'")
    DB_USERNAME=$(grep -E "^DB_USERNAME=" "$APP_ROOT/shared/.env" | cut -d '=' -f2 | tr -d '"' | tr -d "'")
    DB_HOST=$(grep -E "^DB_HOST=" "$APP_ROOT/shared/.env" | cut -d '=' -f2 | tr -d '"' | tr -d "'")
    DB_PORT=$(grep -E "^DB_PORT=" "$APP_ROOT/shared/.env" | cut -d '=' -f2 | tr -d '"' | tr -d "'")
else
    DB_DATABASE="page_builder_prod"
    DB_USERNAME="pentagon_app"
    DB_HOST="127.0.0.1"
    DB_PORT="5432"
fi

# 1. PostgreSQL logical database dump (custom compressed format)
echo "=== [2/4] Dumping PostgreSQL database: $DB_DATABASE ==="
DUMP_FILE="$BACKUP_DIR/${DB_DATABASE}_${TIMESTAMP}.dump"
pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -F c -b -v -f "$DUMP_FILE" "$DB_DATABASE"

# 2. Archive Media Assets and Static Releases
echo "=== [3/4] Archiving media assets and static page releases ==="
MEDIA_ARCHIVE="$BACKUP_DIR/media_and_releases_${TIMESTAMP}.tar.gz"
if [ -d "$SHARED_STORAGE/app" ]; then
    tar -czf "$MEDIA_ARCHIVE" -C "$SHARED_STORAGE/app" public/media page_releases 2>/dev/null || true
fi

# 3. Compute SHA-256 Checksums for integrity verification
echo "=== [4/4] Generating SHA-256 checksums ==="
cd "$BACKUP_DIR"
sha256sum * > SHA256SUMS

# Rotate older backups beyond retention period
echo "Rotating backups older than $KEEP_DAYS days in $BACKUP_DEST..."
find "$BACKUP_DEST" -mindepth 1 -maxdepth 1 -type d -mtime +$KEEP_DAYS -exec rm -rf {} +

echo "=== Backup completed successfully at $BACKUP_DIR ==="
