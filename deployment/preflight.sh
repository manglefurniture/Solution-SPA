#!/usr/bin/env bash
set -euo pipefail

: "${APP_DIR:?APP_DIR is required}"
: "${APP_URL:?APP_URL is required}"
: "${DB_HOST:?DB_HOST is required}"
: "${DB_NAME:?DB_NAME is required}"
: "${DB_USER:?DB_USER is required}"

RENDER_HELPER="${RENDER_HELPER:-$APP_DIR/deployment/render-public-origin.php}"

for cmd in git php curl mariadb mysqldump gzip tar mktemp cmp; do
  command -v "$cmd" >/dev/null 2>&1 || { echo "PRECHECK_FAIL missing $cmd" >&2; exit 1; }
done

[[ -d "$APP_DIR/.git" ]] || { echo "PRECHECK_FAIL APP_DIR is not a git checkout" >&2; exit 1; }
[[ -f "$APP_DIR/database/migrate.php" ]] || { echo "PRECHECK_FAIL migration runner missing" >&2; exit 1; }
[[ -f "$APP_DIR/backend/api/health.php" ]] || { echo "PRECHECK_FAIL health endpoint missing" >&2; exit 1; }
[[ -f "$RENDER_HELPER" ]] || { echo "PRECHECK_FAIL public-origin renderer missing" >&2; exit 1; }

APP_ROOT="$APP_DIR" APP_URL="$APP_URL" php "$RENDER_HELPER" --validate-url >/dev/null

cd "$APP_DIR"
dirty="$(git status --porcelain)"
if [[ -n "$dirty" ]]; then
  allowed=1
  while IFS= read -r line; do
    path="${line:3}"
    case "$path" in
      index.html|privacy.html|robots.txt|sitemap.xml) ;;
      *) allowed=0 ;;
    esac
  done <<< "$dirty"

  if [[ "$allowed" -ne 1 ]]; then
    echo "PRECHECK_FAIL working tree contains unexpected changes" >&2
    exit 1
  fi

  deployed_url="$(APP_ROOT="$APP_DIR" php "$RENDER_HELPER" --detect)" || {
    echo "PRECHECK_FAIL cannot identify previously rendered public origin" >&2
    exit 1
  }

  tmp="$(mktemp -d)"
  trap 'rm -rf "$tmp"' EXIT
  for file in index.html privacy.html robots.txt sitemap.xml; do
    if ! git cat-file -e "HEAD:$file" 2>/dev/null; then
      echo "PRECHECK_FAIL ${file} is dirty but absent from HEAD" >&2
      exit 1
    fi
    git show "HEAD:$file" > "$tmp/$file"
  done
  APP_ROOT="$tmp" APP_URL="$deployed_url" php "$RENDER_HELPER" >/dev/null
  for file in index.html privacy.html robots.txt sitemap.xml; do
    if ! cmp -s "$APP_DIR/$file" "$tmp/$file"; then
      echo "PRECHECK_FAIL ${file} differs from the deterministic deployed-origin render" >&2
      exit 1
    fi
  done
  rm -rf "$tmp"
  trap - EXIT
fi

RATE_LIMIT_DIR="${RATE_LIMIT_DIR:-$(php -r 'echo sys_get_temp_dir();')/solution-spa-rate-limits}"
if ! mkdir -p "$RATE_LIMIT_DIR" || [[ ! -d "$RATE_LIMIT_DIR" || ! -w "$RATE_LIMIT_DIR" ]]; then
  echo "PRECHECK_FAIL rate-limit directory is not writable: $RATE_LIMIT_DIR" >&2
  exit 1
fi
probe="$RATE_LIMIT_DIR/.preflight-$$"
if ! (umask 077 && : > "$probe"); then
  echo "PRECHECK_FAIL cannot write rate-limit directory: $RATE_LIMIT_DIR" >&2
  exit 1
fi
rm -f "$probe"

MYSQL_PWD="${DB_PASSWORD:-}" mariadb -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USER" "$DB_NAME" -e 'SELECT 1;' >/dev/null

echo "PRECHECK_OK"
