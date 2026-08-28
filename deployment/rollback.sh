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

# Classify the target before reset. A commit from before the SEO baseline has no
# canonical/og:url and no privacy/robots/sitemap assets, so there is nothing to
# re-render and the safest rollback is an exact, clean checkout. A commit that
# already contains the full SEO baseline must be re-rendered for APP_URL.
target_index="$(git show "$TARGET_COMMIT:index.html" 2>/dev/null)" || {
  echo "ROLLBACK_FAIL target index.html missing" >&2
  exit 1
}

target_has_canonical=0
target_has_og_url=0
target_has_privacy=0
target_has_robots=0
target_has_sitemap=0

grep -q '<link[[:space:]][^>]*rel="canonical"' <<< "$target_index" && target_has_canonical=1 || true
grep -q '<meta[[:space:]][^>]*property="og:url"' <<< "$target_index" && target_has_og_url=1 || true
git cat-file -e "$TARGET_COMMIT:privacy.html" 2>/dev/null && target_has_privacy=1 || true
git cat-file -e "$TARGET_COMMIT:robots.txt" 2>/dev/null && target_has_robots=1 || true
git cat-file -e "$TARGET_COMMIT:sitemap.xml" 2>/dev/null && target_has_sitemap=1 || true

seo_signal_count=$((target_has_canonical + target_has_og_url + target_has_privacy + target_has_robots + target_has_sitemap))
TARGET_SEO_MODE=""
if [[ "$seo_signal_count" -eq 0 ]]; then
  TARGET_SEO_MODE="legacy"
elif [[ "$seo_signal_count" -eq 5 ]]; then
  TARGET_SEO_MODE="baseline"
else
  echo "ROLLBACK_FAIL target has a partial SEO baseline; refusing ambiguous rollback" >&2
  exit 1
fi

PRESERVED_RENDER_HELPER=""
cleanup() {
  if [[ -n "$PRESERVED_RENDER_HELPER" ]]; then
    rm -f "$PRESERVED_RENDER_HELPER"
  fi
}
trap cleanup EXIT

if [[ "$TARGET_SEO_MODE" == "baseline" ]]; then
  RENDER_HELPER_SOURCE="$SCRIPT_DIR/render-public-origin.php"
  [[ -f "$RENDER_HELPER_SOURCE" ]] || { echo "ROLLBACK_FAIL public origin renderer not found" >&2; exit 1; }
  PRESERVED_RENDER_HELPER="$(mktemp "${TMPDIR:-/tmp}/solution-spa-render-public-origin.XXXXXX.php")"
  cp "$RENDER_HELPER_SOURCE" "$PRESERVED_RENDER_HELPER"
  chmod 600 "$PRESERVED_RENDER_HELPER"
  php -l "$PRESERVED_RENDER_HELPER" >/dev/null
fi

git reset --hard "$TARGET_COMMIT"

if [[ "$TARGET_SEO_MODE" == "baseline" ]]; then
  # The target already owns the SEO files, so rendering changes only tracked
  # baseline files. The matching preflight knows how to validate this
  # deterministic deployed-origin state on the next deploy.
  APP_ROOT="$APP_DIR" php "$PRESERVED_RENDER_HELPER"
  APP_ROOT="$APP_DIR" php "$PRESERVED_RENDER_HELPER" --check
else
  # Pre-SEO targets must remain exact and clean. Adding modern SEO assets or
  # injecting metadata here would make the historical preflight reject the
  # next deployment.
  [[ -z "$(git status --porcelain)" ]] || {
    echo "ROLLBACK_FAIL legacy rollback left a dirty working tree" >&2
    exit 1
  }
fi

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
