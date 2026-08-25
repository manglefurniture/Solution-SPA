#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
: "${APP_DIR:?APP_DIR is required}"
: "${BACKUP_DIR:?BACKUP_DIR is required}"

DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"

bash "$SCRIPT_DIR/preflight.sh"
mkdir -p "$BACKUP_DIR"
cd "$APP_DIR"
PREVIOUS_COMMIT="$(git rev-parse HEAD)"
printf '%s\n' "$PREVIOUS_COMMIT" > "$BACKUP_DIR/last_predeploy_commit"

bash "$SCRIPT_DIR/backup.sh"

git fetch --prune origin "$DEPLOY_BRANCH"
TARGET_COMMIT="${TARGET_COMMIT:-$(git rev-parse "origin/$DEPLOY_BRANCH")}" 
git cat-file -e "${TARGET_COMMIT}^{commit}"

git checkout "$DEPLOY_BRANCH"
git reset --hard "$TARGET_COMMIT"

php database/migrate.php
bash tests/run.sh
bash "$SCRIPT_DIR/health-check.sh"

echo "DEPLOY_OK $(git rev-parse HEAD)"
