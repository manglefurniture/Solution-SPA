#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

APP="$TMP/app"
BACKUPS="$TMP/backups"
mkdir -p "$APP/deployment" "$APP/tests" "$BACKUPS"

# Reproduce the relevant shape of the real pre-SEO target b18c6a94: it has the
# public index, but no canonical/og:url and none of privacy.html, robots.txt,
# sitemap.xml or render-public-origin.php.
cp "$ROOT/index.html" "$APP/index.html"
sed -i '/<link rel="canonical"/d; /<meta property="og:url"/d' "$APP/index.html"
! grep -q '<link rel="canonical"' "$APP/index.html"
! grep -q '<meta property="og:url"' "$APP/index.html"

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
chmod +x "$APP/tests/run.sh" "$APP/deployment/health-check.sh"

cd "$APP"
git init -q
git config user.email test@example.invalid
git config user.name "Solution SPA CI"
git add .
git commit -qm legacy-before-seo-baseline
TARGET="$(git rev-parse HEAD)"

for file in privacy.html robots.txt sitemap.xml deployment/render-public-origin.php; do
  test ! -e "$APP/$file"
done
LEGACY_INDEX="$TMP/legacy-index.html"
git show "$TARGET:index.html" > "$LEGACY_INDEX"

# Simulate the currently deployed revision that introduced the SEO baseline and
# hardened rollback. These assets must be preserved outside the checkout before
# reset because the legacy target removes them.
cp "$ROOT/privacy.html" "$ROOT/robots.txt" "$ROOT/sitemap.xml" "$APP/"
cp "$ROOT/deployment/render-public-origin.php" "$APP/deployment/"
cp "$ROOT/deployment/rollback.sh" "$APP/deployment/"
chmod +x "$APP/deployment/rollback.sh"
git add privacy.html robots.txt sitemap.xml deployment/render-public-origin.php deployment/rollback.sh
git commit -qm current-with-seo-baseline

# Simulate stale output from a different environment. reset must discard it.
printf '\n<!-- stale -->\n' >> index.html

APP_DIR="$APP" \
APP_URL="https://rollback.example.test/spa" \
BACKUP_DIR="$BACKUPS" \
TARGET_COMMIT="$TARGET" \
bash "$APP/deployment/rollback.sh" >/dev/null

test "$(git rev-parse HEAD)" = "$TARGET"
# The target truly has no renderer; success proves the helper survived outside
# the checkout. The three auxiliary SEO files are restored only because the
# target did not contain them.
test ! -e "$APP/deployment/render-public-origin.php"
for file in privacy.html robots.txt sitemap.xml; do
  test -f "$APP/$file"
done

grep -F '<link rel="canonical" href="https://rollback.example.test/spa/" />' index.html >/dev/null
grep -F '<meta property="og:url" content="https://rollback.example.test/spa/" />' index.html >/dev/null
grep -F '<link rel="canonical" href="https://rollback.example.test/spa/privacy.html" />' privacy.html >/dev/null
grep -F 'Sitemap: https://rollback.example.test/spa/sitemap.xml' robots.txt >/dev/null
grep -F '<loc>https://rollback.example.test/spa/privacy.html</loc>' sitemap.xml >/dev/null

if grep -R -F 'https://manglefurniture.github.io/Solution-SPA' index.html privacy.html robots.txt sitemap.xml >/dev/null; then
  echo 'ROLLBACK_TEST_FAIL GitHub Pages origin leaked after pre-SEO rollback' >&2
  exit 1
fi

# Verify we did not replace the historical page with the newer index. Removing
# only the metadata injected by the renderer must reproduce the legacy index
# byte-for-byte.
INDEX_WITHOUT_INJECTED_SEO="$TMP/index-without-injected-seo.html"
sed '/<link rel="canonical"/d; /<meta property="og:url"/d' index.html > "$INDEX_WITHOUT_INJECTED_SEO"
cmp "$LEGACY_INDEX" "$INDEX_WITHOUT_INJECTED_SEO"

echo 'ROLLBACK_PUBLIC_ORIGIN_OK'
