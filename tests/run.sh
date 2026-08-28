#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "== PHP syntax =="
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
  echo "OK $file"
done < <(find backend database tests -type f -name '*.php' -print0)

echo "== Unit/regression checks =="
php tests/PhoneNormalizerTest.php
php tests/AuditSanitizerTest.php
php tests/SecurityPolicyTest.php
php tests/AppointmentRulesTest.php

echo "TESTS_OK"
