#!/usr/bin/env bash

set -euo pipefail

PROJECT_ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)
DEPLOY_SCRIPT="$PROJECT_ROOT/deploy/scripts/deploy.sh"
ROLLBACK_SCRIPT="$PROJECT_ROOT/deploy/scripts/rollback.sh"
FIXTURE_ROOT=$(mktemp -d)

cleanup() {
    rm -rf -- "$FIXTURE_ROOT"
}
trap cleanup EXIT

mkdir -p "$FIXTURE_ROOT/releases/20261031010101"
ln -s "$FIXTURE_ROOT/releases/20261031010101" "$FIXTURE_ROOT/current"

if PENTAGON_APP_ROOT="$FIXTURE_ROOT" bash "$DEPLOY_SCRIPT" main >/dev/null 2>&1; then
    echo 'Expected deploy to reject a missing repository URL.' >&2
    exit 1
fi

if PENTAGON_APP_ROOT="$FIXTURE_ROOT" \
    PENTAGON_REPOSITORY_URL='https://example.invalid/pentagon.git' \
    PENTAGON_KEEP_RELEASES=0 \
    bash "$DEPLOY_SCRIPT" main >/dev/null 2>&1; then
    echo 'Expected deploy to reject a non-positive release retention value.' >&2
    exit 1
fi

if PENTAGON_APP_ROOT="$FIXTURE_ROOT" bash "$ROLLBACK_SCRIPT" '../outside' >/dev/null 2>&1; then
    echo 'Expected rollback to reject a non-timestamp target.' >&2
    exit 1
fi

if PENTAGON_APP_ROOT="$FIXTURE_ROOT" bash "$ROLLBACK_SCRIPT" 20261031020202 >/dev/null 2>&1; then
    echo 'Expected rollback to reject a missing timestamped release.' >&2
    exit 1
fi

echo 'Deployment and rollback guard fixtures passed.'
