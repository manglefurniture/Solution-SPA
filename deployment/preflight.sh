#!/usr/bin/env bash
set -euo pipefail

: "${APP_DIR:?APP_DIR is required}"
: "${APP_URL:?APP_URL is required}"
: "${DB_HOST:?DB_HOST is required}"
: "${DB_NAME:?DB_NAME is required}"
: "${DB_USER:?DB_USER is required}"

for cmd in git php curl mariadb mysqldump gzip tar; do
  command -v "$cmd" >/dev/null 2>&1 || { echo "PRECHECK_FAIL missing $cmd" >&2; exit 1; }
done

[[ -d "$APP_DIR/.git" ]] || { echo "PRECHECK_FAIL APP_DIR is not a git checkout" >&2; exit 1; }
[[ -f "$APP_DIR/database/migrate.php" ]] || { echo "PRECHECK_FAIL migration runner missing" >&2; exit 1; }
[[ -f "$APP_DIR/backend/api/health.php" ]] || { echo "PRECHECK_FAIL health endpoint missing" >&2; exit 1; }

cd "$APP_DIR"
if [[ -n "$(git status --porcelain)" ]]; then
  echo "PRECHECK_FAIL working tree is not clean" >&2
  exit 1
fi

MYSQL_PWD="${DB_PASSWORD:-}" mariadb -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USER" "$DB_NAME" -e 'SELECT 1;' >/dev/null

echo "PRECHECK_OK"
