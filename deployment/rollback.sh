#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
: "${APP_DIR:?APP_DIR is required}"
: "${APP_URL:?APP_URL is required}"
: "${BACKUP_DIR:?BACKUP_DIR is required}"

cd "$APP_DIR"
TARGET_COMMIT="${TARGET_COMMIT:-}"
if [[ -z "$TARGET_COMMIT" && -f "$BACKUP_DIR/last_predeploy_commit" ]]; then
  TARGET_COMMIT="$(cat "$BACKUP_DIR/last_predeploy_commit")"
fi
[[ -n "$TARGET_COMMIT" ]] || { echo "ROLLBACK_FAIL no target commit" >&2; exit 1; }

git cat-file -e "${TARGET_COMMIT}^{commit}"
git reset --hard "$TARGET_COMMIT"

# A rollback restores versioned source files, including the GitHub Pages demo
# origin. Re-render the public SEO origin for the environment we are actually
# serving before tests/health checks can declare the rollback successful.
APP_ROOT="$APP_DIR" php "$SCRIPT_DIR/render-public-origin.php"
APP_ROOT="$APP_DIR" php "$SCRIPT_DIR/render-public-origin.php" --check

if [[ -n "${RESTORE_DB_BACKUP:-}" ]]; then
  : "${DB_HOST:?DB_HOST is required for database restore}"
  : "${DB_NAME:?DB_NAME is required for database restore}"
  : "${DB_USER:?DB_USER is required for database restore}"
  [[ -f "$RESTORE_DB_BACKUP" ]] || { echo "ROLLBACK_FAIL database backup not found" >&2; exit 1; }
  gzip -dc "$RESTORE_DB_BACKUP" | MYSQL_PWD="${DB_PASSWORD:-}" mariadb -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USER" "$DB_NAME"
fi

bash tests/run.sh
bash "$SCRIPT_DIR/health-check.sh"
echo "ROLLBACK_OK $(git rev-parse HEAD)"
