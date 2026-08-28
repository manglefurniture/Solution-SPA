#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

install_runtime_stubs() {
  local app="$1"
  mkdir -p "$app/deployment" "$app/tests"
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
  chmod +x "$app/tests/run.sh" "$app/deployment/health-check.sh"
}

init_repo() {
  local app="$1"
  cd "$app"
  git init -q
  git config user.email test@example.invalid
  git config user.name "Solution SPA CI"
}

run_legacy_case() {
  local app="$TMP/legacy-app"
  local backups="$TMP/legacy-backups"
  mkdir -p "$app" "$backups"
  install_runtime_stubs "$app"

  # Shape of the real pre-SEO main commit b18c6a94: index exists but no
  # canonical/og:url and no privacy/robots/sitemap/renderer.
  cp "$ROOT/index.html" "$app/index.html"
  sed -i '/<link rel="canonical"/d; /<meta property="og:url"/d' "$app/index.html"
  init_repo "$app"
  git add .
  git commit -qm legacy-before-seo-baseline
  local target
  target="$(git rev-parse HEAD)"
  local legacy_index="$TMP/legacy-index.html"
  git show "$target:index.html" > "$legacy_index"

  for file in privacy.html robots.txt sitemap.xml deployment/render-public-origin.php; do
    test ! -e "$app/$file"
  done

  # Current revision contains the new deployment tooling and SEO baseline.
  cp "$ROOT/privacy.html" "$ROOT/robots.txt" "$ROOT/sitemap.xml" "$app/"
  cp "$ROOT/deployment/render-public-origin.php" "$app/deployment/"
  cp "$ROOT/deployment/rollback.sh" "$app/deployment/"
  chmod +x "$app/deployment/rollback.sh"
  git add privacy.html robots.txt sitemap.xml deployment/render-public-origin.php deployment/rollback.sh
  git commit -qm current-with-seo-baseline

  printf '\n<!-- stale -->\n' >> "$app/index.html"

  APP_DIR="$app" \
  APP_URL="https://rollback.example.test/spa" \
  BACKUP_DIR="$backups" \
  TARGET_COMMIT="$target" \
  bash "$app/deployment/rollback.sh" >/dev/null

  test "$(git -C "$app" rev-parse HEAD)" = "$target"
  cmp "$legacy_index" "$app/index.html"
  for file in privacy.html robots.txt sitemap.xml deployment/render-public-origin.php; do
    test ! -e "$app/$file"
  done
  test -z "$(git -C "$app" status --porcelain)"
}

run_baseline_case() {
  local app="$TMP/baseline-app"
  local backups="$TMP/baseline-backups"
  mkdir -p "$app" "$backups"
  install_runtime_stubs "$app"

  # A post-baseline rollback target owns all public SEO files. It intentionally
  # does not own the renderer, proving rollback can preserve that helper outside
  # the checkout while changing only tracked SEO files.
  cp "$ROOT/index.html" "$ROOT/privacy.html" "$ROOT/robots.txt" "$ROOT/sitemap.xml" "$app/"
  init_repo "$app"
  git add .
  git commit -qm target-with-seo-baseline
  local target
  target="$(git rev-parse HEAD)"

  cp "$ROOT/deployment/render-public-origin.php" "$app/deployment/"
  cp "$ROOT/deployment/rollback.sh" "$app/deployment/"
  chmod +x "$app/deployment/rollback.sh"
  git add deployment/render-public-origin.php deployment/rollback.sh
  git commit -qm current-with-renderer

  # Simulate a stale render from another environment; reset must discard it and
  # render the requested APP_URL from the target's own tracked SEO files.
  sed -i 's#https://manglefurniture.github.io/Solution-SPA#https://stale.example.test/old#g' \
    "$app/index.html" "$app/privacy.html" "$app/robots.txt" "$app/sitemap.xml"

  APP_DIR="$app" \
  APP_URL="https://rollback.example.test/spa" \
  BACKUP_DIR="$backups" \
  TARGET_COMMIT="$target" \
  bash "$app/deployment/rollback.sh" >/dev/null

  test "$(git -C "$app" rev-parse HEAD)" = "$target"
  test ! -e "$app/deployment/render-public-origin.php"
  grep -F '<link rel="canonical" href="https://rollback.example.test/spa/" />' "$app/index.html" >/dev/null
  grep -F '<meta property="og:url" content="https://rollback.example.test/spa/" />' "$app/index.html" >/dev/null
  grep -F '<link rel="canonical" href="https://rollback.example.test/spa/privacy.html" />' "$app/privacy.html" >/dev/null
  grep -F 'Sitemap: https://rollback.example.test/spa/sitemap.xml' "$app/robots.txt" >/dev/null
  grep -F '<loc>https://rollback.example.test/spa/privacy.html</loc>' "$app/sitemap.xml" >/dev/null

  if grep -R -F 'https://manglefurniture.github.io/Solution-SPA' \
    "$app/index.html" "$app/privacy.html" "$app/robots.txt" "$app/sitemap.xml" >/dev/null; then
    echo 'ROLLBACK_TEST_FAIL source origin leaked after baseline rollback' >&2
    exit 1
  fi

  # Baseline render is deterministic dirtiness of tracked files only; no
  # untracked modern assets are allowed to survive the reset.
  if git -C "$app" status --porcelain | grep '^??' >/dev/null; then
    echo 'ROLLBACK_TEST_FAIL baseline rollback left untracked files' >&2
    exit 1
  fi
}

run_legacy_case
run_baseline_case

echo 'ROLLBACK_PUBLIC_ORIGIN_OK'
