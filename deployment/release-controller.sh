#!/usr/bin/env bash
set -euo pipefail

SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
: "${APP_DIR:?APP_DIR is required}"
: "${APP_URL:?APP_URL is required}"
: "${BACKUP_DIR:?BACKUP_DIR is required}"
: "${DB_HOST:?DB_HOST is required}"
: "${DB_NAME:?DB_NAME is required}"
: "${DB_USER:?DB_USER is required}"

DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"

# Copy the controller dependencies outside the checkout before touching Git.
# This makes the deployment independent from whichever historical revision is
# currently checked out and from the files that the upcoming reset replaces.
TOOL_DIR="$(mktemp -d "${TMPDIR:-/tmp}/solution-spa-release-tooling.XXXXXX")"
cleanup() {
  rm -rf "$TOOL_DIR"
}
trap cleanup EXIT

for file in preflight.sh backup.sh render-public-origin.php; do
  [[ -f "$SOURCE_DIR/$file" ]] || { echo "DEPLOY_FAIL controller dependency missing: $file" >&2; exit 1; }
  cp "$SOURCE_DIR/$file" "$TOOL_DIR/$file"
done
chmod 700 "$TOOL_DIR/preflight.sh" "$TOOL_DIR/backup.sh"
chmod 600 "$TOOL_DIR/render-public-origin.php"
php -l "$TOOL_DIR/render-public-origin.php" >/dev/null

cd "$APP_DIR"
[[ -d .git ]] || { echo "DEPLOY_FAIL APP_DIR is not a git checkout" >&2; exit 1; }

# rollback-controller.sh intentionally installs tiny bootstraps for both deploy
# and rollback so documented commands keep using preserved modern tooling after
# reverting to an old commit. Once this external release controller is running,
# restore those files from HEAD before preflight so historical strict-clean
# checks cannot be tripped by either bootstrap.
restore_bootstrap_from_head() {
  local path="$1"
  local marker="$2"

  if [[ -f "$path" ]] && grep -q "$marker" "$path"; then
    if git ls-files --error-unmatch "$path" >/dev/null 2>&1; then
      git checkout -- "$path"
    else
      rm -f "$path"
    fi
  fi
}

restore_bootstrap_from_head deployment/deploy.sh SOLUTION_SPA_DEPLOY_BOOTSTRAP
restore_bootstrap_from_head deployment/rollback.sh SOLUTION_SPA_ROLLBACK_BOOTSTRAP

RENDER_HELPER="$TOOL_DIR/render-public-origin.php" bash "$TOOL_DIR/preflight.sh"
mkdir -p "$BACKUP_DIR"
PREVIOUS_COMMIT="$(git rev-parse HEAD)"
printf '%s\n' "$PREVIOUS_COMMIT" > "$BACKUP_DIR/last_predeploy_commit"

bash "$TOOL_DIR/backup.sh"

git fetch --prune origin "$DEPLOY_BRANCH"
TARGET_COMMIT="${TARGET_COMMIT:-$(git rev-parse "origin/$DEPLOY_BRANCH")}" 
git cat-file -e "${TARGET_COMMIT}^{commit}"

# Classify the target before checkout. A complete SEO baseline is rendered from
# APP_URL; a true pre-SEO target is left historical. Partial baselines fail
# closed because they are not safe to publish deterministically.
TARGET_INDEX="$(git show "$TARGET_COMMIT:index.html")" || { echo "DEPLOY_FAIL target index.html missing" >&2; exit 1; }
has_canonical=0
has_og_url=0
[[ "$TARGET_INDEX" == *'<link rel="canonical"'* ]] && has_canonical=1
[[ "$TARGET_INDEX" == *'<meta property="og:url"'* ]] && has_og_url=1
aux_count=0
for file in privacy.html robots.txt sitemap.xml; do
  if git cat-file -e "$TARGET_COMMIT:$file" 2>/dev/null; then
    aux_count=$((aux_count + 1))
  fi
done

if [[ "$has_canonical" -eq 1 && "$has_og_url" -eq 1 && "$aux_count" -eq 3 ]]; then
  TARGET_SEO_MODE="baseline"
elif [[ "$has_canonical" -eq 0 && "$has_og_url" -eq 0 && "$aux_count" -eq 0 ]]; then
  TARGET_SEO_MODE="legacy"
else
  echo "DEPLOY_FAIL target has a partial SEO baseline" >&2
  exit 1
fi

git checkout "$DEPLOY_BRANCH"
git reset --hard "$TARGET_COMMIT"

if [[ "$TARGET_SEO_MODE" == "baseline" ]]; then
  APP_ROOT="$APP_DIR" APP_URL="$APP_URL" php "$TOOL_DIR/render-public-origin.php"
  APP_ROOT="$APP_DIR" APP_URL="$APP_URL" php "$TOOL_DIR/render-public-origin.php" --check
else
  echo "DEPLOY_NOTE target predates SEO baseline; public-origin render skipped"
fi

php database/migrate.php
bash tests/run.sh
bash deployment/health-check.sh

echo "DEPLOY_OK $(git rev-parse HEAD)"
