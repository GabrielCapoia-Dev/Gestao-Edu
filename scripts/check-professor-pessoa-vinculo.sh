#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

VIOLATIONS=$(rg 'Professor::(query\(\)->)?create\(' app/ \
  --glob '*.php' \
  --glob '!app/Services/ProfessorService.php' \
  --glob '!app/Services/ServidorService.php' \
  --glob '!app/Observers/**' \
  2>/dev/null || true)

if [[ -n "$VIOLATIONS" ]]; then
  echo "ERRO: criação direta de Professor fora dos services de pessoa/vínculo:"
  echo "$VIOLATIONS"
  exit 1
fi

echo "OK: cadastros de Professor passam pelos services centralizados de pessoa."