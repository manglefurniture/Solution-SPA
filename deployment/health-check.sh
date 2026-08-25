#!/usr/bin/env bash
set -euo pipefail

: "${APP_URL:?APP_URL is required}"

URL="${APP_URL%/}/backend/api/health.php"
BODY="$(curl --fail --silent --show-error --max-time 15 "$URL")"

if [[ "$BODY" != *'"ok":true'* ]]; then
  echo "HEALTH_FAIL unexpected response: $BODY" >&2
  exit 1
fi

echo "HEALTH_OK $URL"
