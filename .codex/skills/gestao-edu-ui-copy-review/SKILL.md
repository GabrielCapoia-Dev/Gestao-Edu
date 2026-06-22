---
name: gestao-edu-ui-copy-review
description: Use para revisar textos visiveis em contexto de UI do Gestao-Edu, como labels, botoes, badges, placeholders, mensagens e descricoes, preservando identificadores tecnicos e podendo acionar a skill de revisao de lingua portuguesa quando necessario.
---

# Revisao de Copy UI

## Objetivo

Melhorar clareza e consistencia dos textos visiveis da interface sem mexer em contratos tecnicos.

## Sequencia recomendada

1. Ler a tela e identificar todo texto visivel tocado.
2. Confirmar se o texto tambem funciona como identificador tecnico.
3. Revisar label, CTA, badge, placeholder, helper text e empty state no contexto.
4. Se a tarefa for linguistica ampla, usar tambem `$gestao-edu-revisao-lingua-portuguesa`.

## Arquivos que podem ser alterados

- `resources/views/**`
- `lang/**`
- `app/Filament/**` apenas em labels, titulos, descricoes e notificacoes visiveis

## Arquivos somente consulta

- `app/Services/**`
- `app/Models/**`
- `database/**`
- chaves persistidas de permissao, role, enum e integracao

## Limites de seguranca

- Nao renomear identificadores tecnicos.
- Nao alterar sentido funcional de validacoes e notificacoes.
- Nao alterar copy so por preferencia pessoal se ja estiver correta e consistente.

## Checklist de validacao

- Confirmar que so texto visivel foi alterado.
- Confirmar placeholders, interpolacoes e chaves tecnicas preservadas.
- Confirmar aderencia ao contexto visual da tela.

## Saida esperada

- copy revisada
- termos padronizados
- integridade tecnica preservada

