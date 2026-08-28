#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONTROLLER="$SCRIPT_DIR/release-controller.sh"

[[ -f "$CONTROLLER" ]] || { echo "DEPLOY_FAIL release controller missing" >&2; exit 1; }
exec bash "$CONTROLLER" "$@"
