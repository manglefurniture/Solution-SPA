#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
: "${APP_DIR:?APP_DIR is required}"
: "${APP_URL:?APP_URL is required}"
: "${BACKUP_DIR:?BACKUP_DIR is required}"

# A rollback target may predate both the public-origin renderer and the SEO
# support files introduced with the current baseline. Preserve those deployment
# helpers outside the Git checkout before reset so a legacy target can still be
# served with the correct public origin without replacing its historical page.
RENDER_HELPER_SOURCE="$SCRIPT_DIR/render-public-origin.php"
[[ -f "$RENDER_HELPER_SOURCE" ]] || { echo "ROLLBACK_FAIL public origin renderer not found" >&2; exit 1; }

PRESERVED_DIR="$(mktemp -d "${TMPDIR:-/tmp}/solution-spa-rollback-seo.XXXXXX")"
PRESERVED_RENDER_HELPER="$PRESERVED_DIR/render-public-origin.php"
cleanup() {
  rm -rf "$PRESERVED_DIR"
}
trap cleanup EXIT

cp "$RENDER_HELPER_SOURCE" "$PRESERVED_RENDER_HELPER"
chmod 600 "$PRESERVED_RENDER_HELPER"
php -l "$PRESERVED_RENDER_HELPER" >/dev/null

for file in privacy.html robots.txt sitemap.xml; do
  [[ -f "$APP_DIR/$file" ]] || { echo "ROLLBACK_FAIL current SEO asset missing: $file" >&2; exit 1; }
  cp "$APP_DIR/$file" "$PRESERVED_DIR/$file"
done

cd "$APP_DIR"
TARGET_COMMIT="${TARGET_COMMIT:-}"
if [[ -z "$TARGET_COMMIT" && -f "$BACKUP_DIR/last_predeploy_commit" ]]; then
  TARGET_COMMIT="$(cat "$BACKUP_DIR/last_predeploy_commit")"
fi
[[ -n "$TARGET_COMMIT" ]] || { echo "ROLLBACK_FAIL no target commit" >&2; exit 1; }

git cat-file -e "${TARGET_COMMIT}^{commit}"
git reset --hard "$TARGET_COMMIT"

# Restore only auxiliary SEO files that the historical commit never had. If a
# target already contains one, keep its own version. index.html is never copied
# from the newer revision; the preserved renderer only injects canonical/og:url
# when those tags are absent.
for file in privacy.html robots.txt sitemap.xml; do
  if [[ ! -f "$APP_DIR/$file" ]]; then
    cp "$PRESERVED_DIR/$file" "$APP_DIR/$file"
  fi
done

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
