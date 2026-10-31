#!/usr/bin/env bash

set -euo pipefail

REPO_ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
TEST_ROOT=$(mktemp -d)
trap 'rm -rf "$TEST_ROOT"' EXIT

APP_ROOT="$TEST_ROOT/app"
BACKUP_DEST="$TEST_ROOT/backups"
STUB_BIN="$TEST_ROOT/bin"

mkdir -p \
    "$APP_ROOT/shared/storage/app/public/media" \
    "$APP_ROOT/shared/storage/app/private/releases/site/page" \
    "$STUB_BIN"

printf 'media fixture\n' > "$APP_ROOT/shared/storage/app/public/media/hero.txt"
printf 'release fixture\n' > "$APP_ROOT/shared/storage/app/private/releases/site/page/index.html"

cat > "$STUB_BIN/pg_dump" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

while [ "$#" -gt 0 ]; do
    if [ "$1" = "-f" ]; then
        printf 'database fixture\n' > "$2"
        exit 0
    fi
    shift
done

echo 'pg_dump test stub did not receive -f' >&2
exit 2
EOF
chmod +x "$STUB_BIN/pg_dump"

APP_ROOT_OVERRIDE="$APP_ROOT" \
PATH="$STUB_BIN:$PATH" \
bash "$REPO_ROOT/deploy/scripts/backup.sh" "$BACKUP_DEST"

BACKUP_DIR=$(find "$BACKUP_DEST" -mindepth 1 -maxdepth 1 -type d | head -n 1)
ARCHIVE=$(find "$BACKUP_DIR" -maxdepth 1 -name 'media_and_releases_*.tar.gz' | head -n 1)

tar -tzf "$ARCHIVE" | grep -Fx 'public/media/hero.txt'
tar -tzf "$ARCHIVE" | grep -Fx 'private/releases/site/page/index.html'
(cd "$BACKUP_DIR" && sha256sum -c SHA256SUMS)

RESTORE_ROOT="$TEST_ROOT/restored"
mkdir "$RESTORE_ROOT"
tar -xzf "$ARCHIVE" -C "$RESTORE_ROOT"
cmp "$APP_ROOT/shared/storage/app/public/media/hero.txt" "$RESTORE_ROOT/public/media/hero.txt"
cmp "$APP_ROOT/shared/storage/app/private/releases/site/page/index.html" "$RESTORE_ROOT/private/releases/site/page/index.html"

cat > "$STUB_BIN/tar" <<'EOF'
#!/usr/bin/env bash
exit 23
EOF
chmod +x "$STUB_BIN/tar"

if APP_ROOT_OVERRIDE="$APP_ROOT" \
    PATH="$STUB_BIN:$PATH" \
    bash "$REPO_ROOT/deploy/scripts/backup.sh" "$TEST_ROOT/tar-failure"; then
    echo 'Expected backup to fail when tar fails.' >&2
    exit 1
fi

echo 'Backup archive and isolated asset restore rehearsal passed.'
