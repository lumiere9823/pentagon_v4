#!/usr/bin/env bash
# ==============================================================================
# Pentagon Page Builder - Instant Atomic Rollback Script
# ==============================================================================
# Usage: ./rollback.sh [optional_target_release_timestamp]

set -euo pipefail

APP_ROOT="/var/www/pentagon"
RELEASES_DIR="$APP_ROOT/releases"
CURRENT_LINK="$APP_ROOT/current"

if [ ! -L "$CURRENT_LINK" ]; then
    echo "ERROR: Current symlink $CURRENT_LINK not found!" >&2
    exit 1
fi

ACTIVE_RELEASE=$(readlink -f "$CURRENT_LINK")
echo "Active release is: $ACTIVE_RELEASE"

if [ -n "${1:-}" ]; then
    TARGET_RELEASE="$RELEASES_DIR/$1"
else
    # Find second newest release directory
    cd "$RELEASES_DIR"
    TARGET_RELEASE=$(ls -1dt 20* | grep -v "$(basename "$ACTIVE_RELEASE")" | head -n 1 || echo "")
    if [ -n "$TARGET_RELEASE" ]; then
        TARGET_RELEASE="$RELEASES_DIR/$TARGET_RELEASE"
    fi
fi

if [ -z "$TARGET_RELEASE" ] || [ ! -d "$TARGET_RELEASE" ]; then
    echo "ERROR: No valid target release found to roll back to!" >&2
    exit 1
fi

echo "Rolling back to: $TARGET_RELEASE"

# Atomically switch symlink
ln -sfn "$TARGET_RELEASE" "$CURRENT_LINK"

# Reload PHP-FPM
if command -v systemctl >/dev/null 2>&1; then
    sudo systemctl reload php8.4-fpm || sudo systemctl reload php-fpm
fi

# Clear and re-cache on the rolled-back target
cd "$CURRENT_LINK"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Verify health
echo "Verifying health post-rollback..."
if php artisan app:production-health-check; then
    echo "SUCCESS: Rollback to $(basename "$TARGET_RELEASE") completed and verified."
else
    echo "WARNING: Health check reported issues after rollback. Please inspect logs!" >&2
    exit 1
fi
