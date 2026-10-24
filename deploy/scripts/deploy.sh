#!/usr/bin/env bash
# ==============================================================================
# Pentagon Page Builder - Zero-Downtime Atomic Deployment Script
# ==============================================================================
# Usage: ./deploy.sh [git_branch_or_tag] (default: master)

set -euo pipefail

# Configuration
APP_ROOT="/var/www/pentagon"
RELEASES_DIR="$APP_ROOT/releases"
SHARED_DIR="$APP_ROOT/shared"
CURRENT_LINK="$APP_ROOT/current"
TIMESTAMP=$(date +"%Y%m%d%H%M%S")
NEW_RELEASE_DIR="$RELEASES_DIR/$TIMESTAMP"
TARGET_BRANCH="${1:-master}"
KEEP_RELEASES=5

echo "=== [1/8] Starting Deployment: $TIMESTAMP on branch: $TARGET_BRANCH ==="

# Pre-flight check: ensure shared directory exists
if [ ! -f "$SHARED_DIR/.env" ]; then
    echo "ERROR: Shared environment file $SHARED_DIR/.env does not exist!" >&2
    exit 1
fi

mkdir -p "$NEW_RELEASE_DIR"
mkdir -p "$SHARED_DIR/storage/app/public"
mkdir -p "$SHARED_DIR/storage/framework/cache"
mkdir -p "$SHARED_DIR/storage/framework/sessions"
mkdir -p "$SHARED_DIR/storage/framework/views"
mkdir -p "$SHARED_DIR/storage/logs"

# 2. Clone / Export latest code
echo "=== [2/8] Exporting application code ==="
git clone --depth 1 --branch "$TARGET_BRANCH" https://github.com/pentagon/page-builder.git "$NEW_RELEASE_DIR"

cd "$NEW_RELEASE_DIR"

# 3. Symlink shared environment & storage
echo "=== [3/8] Linking persistent storage and environment ==="
ln -sfn "$SHARED_DIR/.env" "$NEW_RELEASE_DIR/.env"
rm -rf "$NEW_RELEASE_DIR/storage"
ln -sfn "$SHARED_DIR/storage" "$NEW_RELEASE_DIR/storage"

# 4. Install PHP dependencies
echo "=== [4/8] Installing production PHP dependencies ==="
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet

# 5. Build frontend assets
echo "=== [5/8] Building Vite frontend assets ==="
npm ci --silent
npm run build --silent
rm -rf node_modules

# 6. Database migrations & Cache warmups
echo "=== [6/8] Running database migrations and warming caches ==="
php artisan migrate --force --isolated
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Atomic Symlink Activation & FPM Reload
echo "=== [7/8] Activating release via atomic symlink ==="
PREVIOUS_RELEASE=$(readlink -f "$CURRENT_LINK" 2>/dev/null || echo "")

ln -sfn "$NEW_RELEASE_DIR" "$CURRENT_LINK"

if command -v systemctl >/dev/null 2>&1; then
    sudo systemctl reload php8.4-fpm || sudo systemctl reload php-fpm
fi

# 8. Post-deploy health verification
echo "=== [8/8] Verifying production health check ==="
if php "$CURRENT_LINK/artisan" app:production-health-check; then
    echo "SUCCESS: Production health check verified release $TIMESTAMP!"
else
    echo "CRITICAL: Health check FAILED! Initiating automatic rollback..." >&2
    if [ -n "$PREVIOUS_RELEASE" ] && [ -d "$PREVIOUS_RELEASE" ]; then
        ln -sfn "$PREVIOUS_RELEASE" "$CURRENT_LINK"
        if command -v systemctl >/dev/null 2>&1; then
            sudo systemctl reload php8.4-fpm || sudo systemctl reload php-fpm
        fi
        echo "Rolled back to previous release: $PREVIOUS_RELEASE"
    fi
    exit 1
fi

# Clean up older releases (keep last N)
echo "=== Pruning old releases (keeping last $KEEP_RELEASES) ==="
cd "$RELEASES_DIR"
ls -1dt 20* | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -rf

echo "=== Deployment completed successfully: $TIMESTAMP ==="
