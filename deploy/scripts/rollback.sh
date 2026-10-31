#!/usr/bin/env bash
# ==============================================================================
# Pentagon Page Builder - Instant Atomic Rollback Script
# ==============================================================================
# Usage: ./rollback.sh [optional_target_release_timestamp]

set -euo pipefail

APP_ROOT="${PENTAGON_APP_ROOT:-/var/www/pentagon}"
RELEASES_DIR="$APP_ROOT/releases"
CURRENT_LINK="$APP_ROOT/current"
PHP_FPM_SERVICE="${PENTAGON_PHP_FPM_SERVICE:-php8.4-fpm}"

reload_php_fpm() {
    if command -v systemctl >/dev/null 2>&1; then
        sudo systemctl reload "$PHP_FPM_SERVICE"
    fi
}

activate_release() {
    local release_dir="$1"
    local pending_link="$APP_ROOT/.current-rollback-$$"

    ln -s "$release_dir" "$pending_link"
    mv -Tf "$pending_link" "$CURRENT_LINK"
}

if [ ! -L "$CURRENT_LINK" ]; then
    echo "ERROR: Current symlink $CURRENT_LINK not found!" >&2
    exit 1
fi

ACTIVE_RELEASE=$(readlink -f "$CURRENT_LINK")
echo "Active release is: $ACTIVE_RELEASE"

if [ -n "${1:-}" ]; then
    if ! [[ "$1" =~ ^[0-9]{14}$ ]]; then
        echo "ERROR: Target release must be a 14-digit deployment timestamp." >&2
        exit 1
    fi
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
TARGET_RELEASE=$(cd "$TARGET_RELEASE" && pwd -P)
case "$TARGET_RELEASE" in
    "$RELEASES_DIR"/*) ;;
    *)
        echo "ERROR: Target release resolves outside $RELEASES_DIR." >&2
        exit 1
        ;;
esac

activate_release "$TARGET_RELEASE"

# Reload PHP-FPM
reload_php_fpm

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
