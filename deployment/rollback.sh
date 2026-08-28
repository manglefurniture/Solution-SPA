#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
: "${APP_DIR:?APP_DIR is required}"
: "${APP_URL:?APP_URL is required}"
: "${BACKUP_DIR:?BACKUP_DIR is required}"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR" 2>/dev/null || true

# Persist the modern deployment controller outside the Git checkout before any
# reset. rollback targets may contain deploy/preflight scripts that predate the
# SEO baseline or the deterministic-render rules, so the next documented
# `bash deployment/deploy.sh` must not depend on those historical scripts.
PERSISTED_TOOLING="$BACKUP_DIR/deployment-tooling"
TOOLING_STAGE="$(mktemp -d "$BACKUP_DIR/.deployment-tooling.XXXXXX")"
cleanup() {
  rm -rf "$TOOLING_STAGE"
}
trap cleanup EXIT

for file in release-controller.sh preflight.sh backup.sh render-public-origin.php; do
  [[ -f "$SCRIPT_DIR/$file" ]] || { echo "ROLLBACK_FAIL deployment tooling missing: $file" >&2; exit 1; }
  cp "$SCRIPT_DIR/$file" "$TOOLING_STAGE/$file"
done
chmod 700 "$TOOLING_STAGE/release-controller.sh" "$TOOLING_STAGE/preflight.sh" "$TOOLING_STAGE/backup.sh"
chmod 600 "$TOOLING_STAGE/render-public-origin.php"
php -l "$TOOLING_STAGE/render-public-origin.php" >/dev/null
rm -rf "$PERSISTED_TOOLING"
mv "$TOOLING_STAGE" "$PERSISTED_TOOLING"
TOOLING_STAGE=""

cd "$APP_DIR"
TARGET_COMMIT="${TARGET_COMMIT:-}"
if [[ -z "$TARGET_COMMIT" && -f "$BACKUP_DIR/last_predeploy_commit" ]]; then
  TARGET_COMMIT="$(cat "$BACKUP_DIR/last_predeploy_commit")"
fi
[[ -n "$TARGET_COMMIT" ]] || { echo "ROLLBACK_FAIL no target commit" >&2; exit 1; }
git cat-file -e "${TARGET_COMMIT}^{commit}"

# Classify the target before reset. A true pre-SEO commit is restored exactly.
# A complete SEO baseline is re-rendered from APP_URL. Partial baselines fail
# closed because they cannot be published deterministically.
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
if [[ "$seo_signal_count" -eq 0 ]]; then
  TARGET_SEO_MODE="legacy"
elif [[ "$seo_signal_count" -eq 5 ]]; then
  TARGET_SEO_MODE="baseline"
else
  echo "ROLLBACK_FAIL target has a partial SEO baseline; refusing ambiguous rollback" >&2
  exit 1
fi

git reset --hard "$TARGET_COMMIT"

if [[ "$TARGET_SEO_MODE" == "baseline" ]]; then
  APP_ROOT="$APP_DIR" APP_URL="$APP_URL" php "$PERSISTED_TOOLING/render-public-origin.php"
  APP_ROOT="$APP_DIR" APP_URL="$APP_URL" php "$PERSISTED_TOOLING/render-public-origin.php" --check
else
  [[ -z "$(git status --porcelain)" ]] || {
    echo "ROLLBACK_FAIL legacy rollback was not exact before bootstrap installation" >&2
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
bash deployment/health-check.sh

# Install a deterministic one-file bootstrap only after the historical revision
# has passed its own tests/health check. The next normal deployment starts the
# preserved modern controller; that controller restores this file from HEAD
# before preflight, so strict historical preflights and rendered SEO changes do
# not block forward deployment.
mkdir -p deployment
cat > deployment/deploy.sh <<'BOOTSTRAP'
#!/usr/bin/env bash
# SOLUTION_SPA_DEPLOY_BOOTSTRAP
set -euo pipefail
: "${BACKUP_DIR:?BACKUP_DIR is required}"
CONTROLLER="$BACKUP_DIR/deployment-tooling/release-controller.sh"
[[ -f "$CONTROLLER" ]] || { echo "DEPLOY_FAIL preserved release controller missing" >&2; exit 1; }
exec bash "$CONTROLLER" "$@"
BOOTSTRAP
chmod 755 deployment/deploy.sh

echo "ROLLBACK_OK $(git rev-parse HEAD) mode=$TARGET_SEO_MODE forward_deploy=preserved-controller"
