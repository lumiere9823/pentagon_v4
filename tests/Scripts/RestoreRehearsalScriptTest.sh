#!/usr/bin/env bash

set -euo pipefail

PROJECT_ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)
SCRIPT="$PROJECT_ROOT/deploy/scripts/restore-rehearsal.sh"
FIXTURE_ROOT=$(mktemp -d)
BIN_DIR="$FIXTURE_ROOT/bin"
BACKUP_DIR="$FIXTURE_ROOT/backup"
COMMAND_LOG="$FIXTURE_ROOT/commands.log"

cleanup() {
    rm -rf -- "$FIXTURE_ROOT"
}
trap cleanup EXIT

mkdir -p "$BIN_DIR" "$BACKUP_DIR/assets/public/media" "$BACKUP_DIR/assets/private/releases/site/page"
printf 'fixture dump\n' > "$BACKUP_DIR/page_builder_prod.dump"
printf 'media\n' > "$BACKUP_DIR/assets/public/media/hero.txt"
printf '<html>release</html>\n' > "$BACKUP_DIR/assets/private/releases/site/page/index.html"
tar -czf "$BACKUP_DIR/media_and_releases_fixture.tar.gz" -C "$BACKUP_DIR/assets" public/media private/releases
(
    cd "$BACKUP_DIR"
    sha256sum page_builder_prod.dump media_and_releases_fixture.tar.gz > SHA256SUMS
)

cat > "$BIN_DIR/dropdb" <<'EOF'
#!/usr/bin/env bash
printf 'dropdb %s\n' "$*" >> "$COMMAND_LOG"
EOF

cat > "$BIN_DIR/createdb" <<'EOF'
#!/usr/bin/env bash
printf 'createdb %s\n' "$*" >> "$COMMAND_LOG"
EOF

cat > "$BIN_DIR/pg_restore" <<'EOF'
#!/usr/bin/env bash
printf 'pg_restore %s\n' "$*" >> "$COMMAND_LOG"
if [ "${FAIL_PG_RESTORE:-0}" = "1" ]; then
    exit 17
fi
EOF

cat > "$BIN_DIR/psql" <<'EOF'
#!/usr/bin/env bash
printf 'psql %s\n' "$*" >> "$COMMAND_LOG"
query="${*: -1}"
if [[ "$query" == *"to_regclass"* ]]; then
    printf '1\n'
else
    printf '3\n'
fi
EOF

chmod +x "$BIN_DIR/dropdb" "$BIN_DIR/createdb" "$BIN_DIR/pg_restore" "$BIN_DIR/psql"
export PATH="$BIN_DIR:$PATH"
export COMMAND_LOG
export REHEARSAL_DB='pentagon_rehearsal_fixture'

SUCCESS_OUTPUT=$(bash "$SCRIPT" "$BACKUP_DIR")
printf '%s\n' "$SUCCESS_OUTPUT" | grep -q 'SUCCESS: Backup restore rehearsal completed'
grep -q 'pg_restore --exit-on-error' "$COMMAND_LOG"
grep -q 'dropdb --if-exists pentagon_rehearsal_fixture' "$COMMAND_LOG"

: > "$COMMAND_LOG"
if FAIL_PG_RESTORE=1 bash "$SCRIPT" "$BACKUP_DIR" >/dev/null 2>&1; then
    echo 'Expected pg_restore failure to fail the rehearsal.' >&2
    exit 1
fi
grep -q 'dropdb --if-exists pentagon_rehearsal_fixture' "$COMMAND_LOG"

cp "$BACKUP_DIR/SHA256SUMS" "$BACKUP_DIR/SHA256SUMS.valid"
printf 'corruption\n' >> "$BACKUP_DIR/page_builder_prod.dump"
if bash "$SCRIPT" "$BACKUP_DIR" >/dev/null 2>&1; then
    echo 'Expected checksum corruption to fail the rehearsal.' >&2
    exit 1
fi
mv "$BACKUP_DIR/SHA256SUMS.valid" "$BACKUP_DIR/SHA256SUMS"
printf 'fixture dump\n' > "$BACKUP_DIR/page_builder_prod.dump"

mv "$BACKUP_DIR/media_and_releases_fixture.tar.gz" "$FIXTURE_ROOT/archive.saved"
(
    cd "$BACKUP_DIR"
    sha256sum page_builder_prod.dump > SHA256SUMS
)
if bash "$SCRIPT" "$BACKUP_DIR" >/dev/null 2>&1; then
    echo 'Expected missing asset archive to fail the rehearsal.' >&2
    exit 1
fi

echo 'Restore rehearsal success and fail-closed fixtures passed.'
