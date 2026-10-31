#!/usr/bin/env bash
# ==============================================================================
# Pentagon Page Builder - Automated Database & Asset Backup Script
# ==============================================================================
# Usage: ./backup.sh [destination_dir] (default: /var/backups/pentagon)

set -euo pipefail

BACKUP_DEST="${1:-/var/backups/pentagon}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_DIR="$BACKUP_DEST/$TIMESTAMP"
APP_ROOT="${APP_ROOT_OVERRIDE:-/var/www/pentagon}"
SHARED_STORAGE="$APP_ROOT/shared/storage"

mkdir -p "$BACKUP_DEST"
if ! mkdir "$BACKUP_DIR"; then
    echo "ERROR: Backup directory already exists; refusing to overwrite: $BACKUP_DIR" >&2
    exit 1
fi

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
for required_path in public/media private/releases; do
    if [ ! -d "$SHARED_STORAGE/app/$required_path" ]; then
        echo "ERROR: Required backup source is missing: $SHARED_STORAGE/app/$required_path" >&2
        exit 1
    fi
done

if ! tar -czf "$MEDIA_ARCHIVE" -C "$SHARED_STORAGE/app" public/media private/releases; then
    echo "ERROR: Failed to archive media assets and static page releases." >&2
    exit 1
fi

# 3. Compute SHA-256 Checksums for integrity verification
echo "=== [4/4] Generating SHA-256 checksums ==="
cd "$BACKUP_DIR"
sha256sum * > SHA256SUMS

echo "=== Backup completed successfully at $BACKUP_DIR ==="
