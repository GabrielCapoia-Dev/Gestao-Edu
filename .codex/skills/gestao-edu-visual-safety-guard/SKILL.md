---
name: gestao-edu-visual-safety-guard
description: Use para garantir que uma mudanca visual no Gestao-Edu nao altere permissoes, queries, validacoes, policies, models, exports, notificacoes ou qualquer regra de negocio sem autorizacao explicita.
---

# Guarda de Seguranca Visual

## Objetivo

Bloquear deriva funcional durante ajustes de UI e UX.

## Sequencia recomendada

1. Ler os arquivos visuais da tarefa.
2. Identificar chamadas de service, policy, query, action e validacao tocadas pela superficie.
3. Separar claramente o que e apresentacao do que e comportamento.
4. Permitir apenas o menor diff visual seguro.

## Arquivos que podem ser alterados

- `resources/views/**`
- `resources/css/**`
- `resources/js/**` estritamente visual
- `app/Providers/Filament/**` para tema e hooks visuais

## Arquivos proibidos sem autorizacao explicita

- `app/Services/**`
- `app/Policies/**`
- `app/Models/**`
- `database/**`
- `routes/**`
- queries, filtros e actions em `app/Filament/**` que mudem comportamento

## Limites de seguranca

- Nao aceitar refatoracao ampla disfarcada de polimento visual.
- Nao remover dados de tela que sustentem decisao operacional.
- Nao tocar em export, notificacao, permissao ou historico sem ordem explicita.

## Checklist de validacao

- Conferir diff de escopo.
- Conferir se a regra permaneceu intacta.
- Conferir se a alteracao nova e realmente visual.
- Registrar qualquer risco residual.

## Saida esperada

- avaliacao de risco visual x funcional
- limites claros de alteracao
- validacao final de escopo seguro

