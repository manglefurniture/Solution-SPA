#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
: "${BACKUP_DIR:?BACKUP_DIR is required}"

# On the current release, prefer the controller tracked beside this bootstrap.
# After a historical rollback, that tracked controller may no longer exist, so
# the bootstrap falls back to the controller preserved outside the checkout.
LOCAL_CONTROLLER="$SCRIPT_DIR/rollback-controller.sh"
PRESERVED_CONTROLLER="$BACKUP_DIR/deployment-tooling/rollback-controller.sh"

if [[ -f "$LOCAL_CONTROLLER" ]]; then
  exec bash "$LOCAL_CONTROLLER" "$@"
fi

[[ -f "$PRESERVED_CONTROLLER" ]] || {
  echo "ROLLBACK_FAIL rollback controller unavailable locally and in preserved tooling" >&2
  exit 1
}
exec bash "$PRESERVED_CONTROLLER" "$@"
