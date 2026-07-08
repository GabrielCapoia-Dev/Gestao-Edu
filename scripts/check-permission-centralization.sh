#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

VIOLATIONS=$(rg 'hasPermissionTo' app/ \
  --glob '*.php' \
  --glob '!app/Policies/**' \
  --glob '!app/Models/User.php' \
  2>/dev/null || true)

if [[ -n "$VIOLATIONS" ]]; then
  echo "ERRO: hasPermissionTo encontrado fora de app/Policies/ (exceto User.php):"
  echo "$VIOLATIONS"
  exit 1
fi

echo "OK: todas as permissões Spatie estão centralizadas em app/Policies/."