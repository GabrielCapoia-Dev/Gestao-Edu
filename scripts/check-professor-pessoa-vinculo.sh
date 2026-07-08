#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if command -v rg >/dev/null 2>&1; then
  VIOLATIONS=$(rg 'Professor::(query\(\)->)?create\(' app/ \
    --glob '*.php' \
    --glob '!app/Services/ProfessorService.php' \
    --glob '!app/Services/ServidorService.php' \
    --glob '!app/Services/PessoaProfessorService.php' \
    --glob '!app/Observers/**' \
    2>/dev/null || true)
else
  VIOLATIONS=$(grep -R --include='*.php' -n 'Professor::\(query()->\)\?create(' app \
    | grep -v 'app/Services/ProfessorService.php' \
    | grep -v 'app/Services/ServidorService.php' \
    | grep -v 'app/Services/PessoaProfessorService.php' \
    | grep -v 'app/Observers/' \
    || true)
fi

if [[ -n "$VIOLATIONS" ]]; then
  echo "ERRO: criação direta de Professor fora dos services de pessoa/vínculo:"
  echo "$VIOLATIONS"
  exit 1
fi

if ! grep -q 'class PessoaProfessorService' app/Services/PessoaProfessorService.php; then
  echo "ERRO: PessoaProfessorService não encontrado."
  exit 1
fi

if ! grep -q 'sincronizarVinculosFuncionaisSilenciosos' app/Services/PessoaProfessorService.php; then
  echo "ERRO: shadow sync de vínculos funcionais ausente em PessoaProfessorService."
  exit 1
fi

echo "OK: cadastros de Professor passam pelos services centralizados de pessoa."