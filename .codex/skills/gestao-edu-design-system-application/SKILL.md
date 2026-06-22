---
name: gestao-edu-design-system-application
description: Use para aplicar o design system de referencia do CRM no Gestao-Edu de forma incremental, priorizando tokens, classes reutilizaveis, partials visuais e preservacao total da regra de negocio.
---

# Aplicacao do Design System

## Objetivo

Aplicar o padrao visual de referencia no Gestao-Edu sem copiar dominio do CRM e sem tocar na regra funcional.

## Sequencia recomendada

1. Ler `docs/design-system-crm-referencia.md`.
2. Identificar se a tarefa e de token, componente global ou tela isolada.
3. Preferir criar ou ajustar base reutilizavel antes de editar varias telas.
4. Aplicar somente a menor mudanca coerente com o padrao alvo.
5. Validar que o diff ficou restrito a UI.

## Arquivos que podem ser alterados

- `resources/css/**`
- `resources/views/**`
- `resources/js/**` estritamente visual
- `app/Providers/Filament/**` para tema, fonte, cores e hooks visuais

## Arquivos somente consulta

- `app/Services/**`
- `app/Policies/**`
- `app/Models/**`
- `database/**`
- `app/Filament/**` quando o trecho nao for de apresentacao

## Limites de seguranca

- Nao portar classes ou nomes do CRM como copia cega.
- Nao misturar limpeza visual com refatoracao estrutural ampla.
- Nao alterar filtros, actions, queries, exports ou notificacoes.

## Checklist de validacao

- Confirmar aderencia ao documento de referencia.
- Confirmar reuso de tokens ou classes.
- Confirmar ausencia de novas duplicacoes.
- Confirmar que o diff nao encostou em regra de negocio.

## Saida esperada

- alteracao visual pequena e rastreavel
- componente ou token reutilizavel quando couber
- riscos e limitacoes registrados

