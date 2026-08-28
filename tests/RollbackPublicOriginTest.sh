#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
DB_NAME="solution_spa_rollback_$$_test"

cleanup() {
  MYSQL_PWD=root mariadb -h 127.0.0.1 -uroot -e "DROP DATABASE IF EXISTS \`$DB_NAME\`;" >/dev/null 2>&1 || true
  rm -rf "$TMP"
}
trap cleanup EXIT

MYSQL_PWD=root mariadb -h 127.0.0.1 -uroot -e "CREATE DATABASE \`$DB_NAME\`;" >/dev/null

install_runtime_stubs() {
  local app="$1"
  mkdir -p "$app/deployment" "$app/tests" "$app/database" "$app/backend/api"

  cat > "$app/tests/run.sh" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
exit 0
SH
  cat > "$app/deployment/health-check.sh" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
exit 0
SH
  cat > "$app/database/migrate.php" <<'PHP'
<?php
exit(0);
PHP
  cat > "$app/backend/api/health.php" <<'PHP'
<?php
http_response_code(200);
PHP
  chmod +x "$app/tests/run.sh" "$app/deployment/health-check.sh"
}

install_historical_deploy_tools() {
  local app="$1"
  # These scripts deliberately fail. A successful forward deployment after a
  # rollback therefore proves that deployment/deploy.sh used the preserved
  # modern controller rather than the historical deploy/preflight tooling.
  cat > "$app/deployment/deploy.sh" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
echo 'HISTORICAL_DEPLOY_MUST_NOT_RUN' >&2
exit 91
SH
  cat > "$app/deployment/preflight.sh" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
echo 'HISTORICAL_PREFLIGHT_MUST_NOT_RUN' >&2
exit 92
SH
  chmod +x "$app/deployment/deploy.sh" "$app/deployment/preflight.sh"
}

build_fixture() {
  local seed="$TMP/seed"
  local origin="$TMP/origin.git"
  mkdir -p "$seed"
  install_runtime_stubs "$seed"
  install_historical_deploy_tools "$seed"

  cp "$ROOT/index.html" "$seed/index.html"
  sed -i '/<link rel="canonical"/d; /<meta property="og:url"/d' "$seed/index.html"

  cd "$seed"
  git init -q
  git config user.email test@example.invalid
  git config user.name "Solution SPA CI"
  git branch -M main
  git add .
  git commit -qm legacy-before-seo-baseline
  LEGACY_TARGET="$(git rev-parse HEAD)"

  cp "$ROOT/index.html" "$ROOT/privacy.html" "$ROOT/robots.txt" "$ROOT/sitemap.xml" "$seed/"
  git add index.html privacy.html robots.txt sitemap.xml
  git commit -qm baseline-with-strict-historical-preflight
  BASELINE_TARGET="$(git rev-parse HEAD)"

  # Current release owns the hardened deployment controller and rollback.
  cp "$ROOT/deployment/deploy.sh" \
     "$ROOT/deployment/release-controller.sh" \
     "$ROOT/deployment/preflight.sh" \
     "$ROOT/deployment/backup.sh" \
     "$ROOT/deployment/render-public-origin.php" \
     "$ROOT/deployment/rollback.sh" \
     "$seed/deployment/"
  chmod +x "$seed/deployment/deploy.sh" \
           "$seed/deployment/release-controller.sh" \
           "$seed/deployment/preflight.sh" \
           "$seed/deployment/backup.sh" \
           "$seed/deployment/rollback.sh"
  git add deployment
  git commit -qm current-hardened-release-tooling
  CURRENT_TARGET="$(git rev-parse HEAD)"

  git init --bare -q "$origin"
  git remote add origin "$origin"
  git push -q -u origin main
  ORIGIN="$origin"
}

assert_forward_release() {
  local app="$1"
  local current="$2"
  local public_url="$3"

  test "$(git -C "$app" rev-parse HEAD)" = "$current"
  ! grep -q 'SOLUTION_SPA_DEPLOY_BOOTSTRAP' "$app/deployment/deploy.sh"
  grep -F "<link rel=\"canonical\" href=\"$public_url/\" />" "$app/index.html" >/dev/null
  grep -F "<meta property=\"og:url\" content=\"$public_url/\" />" "$app/index.html" >/dev/null
  grep -F "Sitemap: $public_url/sitemap.xml" "$app/robots.txt" >/dev/null
  grep -F "<loc>$public_url/privacy.html</loc>" "$app/sitemap.xml" >/dev/null

  if git -C "$app" status --porcelain | grep '^??' >/dev/null; then
    echo 'ROLLBACK_TEST_FAIL forward deployment left untracked files' >&2
    exit 1
  fi
}

run_case() {
  local name="$1"
  local rollback_target="$2"
  local rollback_mode="$3"
  local app="$TMP/$name-app"
  local backups="$TMP/$name-backups"
  local rollback_url="https://rollback.example.test/$name"
  local forward_url="https://forward.example.test/$name"

  git clone -q -b main "$ORIGIN" "$app"
  mkdir -p "$backups"

  APP_DIR="$app" \
  APP_URL="$rollback_url" \
  BACKUP_DIR="$backups" \
  TARGET_COMMIT="$rollback_target" \
  bash "$app/deployment/rollback.sh" >/dev/null

  test "$(git -C "$app" rev-parse HEAD)" = "$rollback_target"
  grep -q 'SOLUTION_SPA_DEPLOY_BOOTSTRAP' "$app/deployment/deploy.sh"
  test -f "$backups/deployment-tooling/release-controller.sh"
  test -f "$backups/deployment-tooling/preflight.sh"
  test -f "$backups/deployment-tooling/backup.sh"
  test -f "$backups/deployment-tooling/render-public-origin.php"

  if [[ "$rollback_mode" == "legacy" ]]; then
    test ! -e "$app/privacy.html"
    test ! -e "$app/robots.txt"
    test ! -e "$app/sitemap.xml"
    ! grep -q '<link rel="canonical"' "$app/index.html"
    ! grep -q '<meta property="og:url"' "$app/index.html"
  else
    grep -F "<link rel=\"canonical\" href=\"$rollback_url/\" />" "$app/index.html" >/dev/null
    grep -F "Sitemap: $rollback_url/sitemap.xml" "$app/robots.txt" >/dev/null
  fi

  # This is the exact documented command after rollback. The checkout currently
  # contains deliberately broken historical deploy/preflight scripts in HEAD;
  # the bootstrap must delegate to the preserved modern controller, which
  # restores deployment/deploy.sh before preflight and then deploys current main.
  APP_DIR="$app" \
  APP_URL="$forward_url" \
  BACKUP_DIR="$backups" \
  DB_HOST=127.0.0.1 \
  DB_PORT=3306 \
  DB_NAME="$DB_NAME" \
  DB_USER=root \
  DB_PASSWORD=root \
  RATE_LIMIT_DIR="$TMP/$name-rate-limits" \
  bash "$app/deployment/deploy.sh" >/dev/null

  assert_forward_release "$app" "$CURRENT_TARGET" "$forward_url"
}

build_fixture
run_case legacy "$LEGACY_TARGET" legacy
run_case baseline "$BASELINE_TARGET" baseline

echo 'ROLLBACK_PUBLIC_ORIGIN_OK'
