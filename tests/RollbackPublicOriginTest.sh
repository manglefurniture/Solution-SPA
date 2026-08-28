#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

APP="$TMP/app"
BACKUPS="$TMP/backups"
mkdir -p "$APP/deployment" "$APP/tests" "$BACKUPS"

cp "$ROOT/index.html" "$ROOT/privacy.html" "$ROOT/robots.txt" "$ROOT/sitemap.xml" "$APP/"
cp "$ROOT/deployment/render-public-origin.php" "$APP/deployment/"
cp "$ROOT/deployment/rollback.sh" "$APP/deployment/"

cat > "$APP/tests/run.sh" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
exit 0
SH
cat > "$APP/deployment/health-check.sh" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
exit 0
SH
chmod +x "$APP/tests/run.sh" "$APP/deployment/health-check.sh" "$APP/deployment/rollback.sh"

cd "$APP"
git init -q
git config user.email test@example.invalid
git config user.name "Solution SPA CI"
git add .
git commit -qm initial
TARGET="$(git rev-parse HEAD)"

# Simulate stale rendered output from a previous environment. rollback.sh must
# discard it with git reset and then render the requested APP_URL again.
printf '\n<!-- stale -->\n' >> index.html

APP_DIR="$APP" \
APP_URL="https://rollback.example.test/spa" \
BACKUP_DIR="$BACKUPS" \
TARGET_COMMIT="$TARGET" \
bash "$APP/deployment/rollback.sh" >/dev/null

grep -F '<link rel="canonical" href="https://rollback.example.test/spa/" />' index.html >/dev/null
grep -F '<meta property="og:url" content="https://rollback.example.test/spa/" />' index.html >/dev/null
grep -F '<link rel="canonical" href="https://rollback.example.test/spa/privacy.html" />' privacy.html >/dev/null
grep -F 'Sitemap: https://rollback.example.test/spa/sitemap.xml' robots.txt >/dev/null
grep -F '<loc>https://rollback.example.test/spa/privacy.html</loc>' sitemap.xml >/dev/null

if grep -R -F 'https://manglefurniture.github.io/Solution-SPA' index.html privacy.html robots.txt sitemap.xml >/dev/null; then
  echo 'ROLLBACK_TEST_FAIL GitHub Pages origin leaked after rollback' >&2
  exit 1
fi

echo 'ROLLBACK_PUBLIC_ORIGIN_OK'
