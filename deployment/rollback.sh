#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
: "${APP_DIR:?APP_DIR is required}"
: "${APP_URL:?APP_URL is required}"
: "${BACKUP_DIR:?BACKUP_DIR is required}"

# The target commit may predate render-public-origin.php. Preserve the helper
# outside the Git checkout before reset so legacy rollbacks can still restore
# the environment-specific public origin.
RENDER_HELPER_SOURCE="$SCRIPT_DIR/render-public-origin.php"
[[ -f "$RENDER_HELPER_SOURCE" ]] || { echo "ROLLBACK_FAIL public origin renderer not found" >&2; exit 1; }
PRESERVED_RENDER_HELPER="$(mktemp "${TMPDIR:-/tmp}/solution-spa-render-public-origin.XXXXXX.php")"
cleanup() {
  rm -f "$PRESERVED_RENDER_HELPER"
}
trap cleanup EXIT
cp "$RENDER_HELPER_SOURCE" "$PRESERVED_RENDER_HELPER"
chmod 600 "$PRESERVED_RENDER_HELPER"
php -l "$PRESERVED_RENDER_HELPER" >/dev/null

cd "$APP_DIR"
TARGET_COMMIT="${TARGET_COMMIT:-}"
if [[ -z "$TARGET_COMMIT" && -f "$BACKUP_DIR/last_predeploy_commit" ]]; then
  TARGET_COMMIT="$(cat "$BACKUP_DIR/last_predeploy_commit")"
fi
[[ -n "$TARGET_COMMIT" ]] || { echo "ROLLBACK_FAIL no target commit" >&2; exit 1; }

git cat-file -e "${TARGET_COMMIT}^{commit}"
git reset --hard "$TARGET_COMMIT"

# A rollback restores versioned source files, including the GitHub Pages demo
# origin. Re-render with the preserved helper so this also works when the
# target commit does not contain render-public-origin.php yet.
APP_ROOT="$APP_DIR" php "$PRESERVED_RENDER_HELPER"
APP_ROOT="$APP_DIR" php "$PRESERVED_RENDER_HELPER" --check

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
