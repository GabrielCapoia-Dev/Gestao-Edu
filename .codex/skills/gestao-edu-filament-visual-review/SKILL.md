---
name: gestao-edu-filament-visual-review
description: Use para revisar visualmente componentes Filament do Gestao-Edu, incluindo tables, forms, tabs, badges, actions, modais, slideovers, headers e empty states, sem alterar comportamento funcional.
---

# Revisao Visual Filament

## Objetivo

Revisar consistencia visual das superficies Filament mais usadas do sistema.

## Sequencia recomendada

1. Ler a `Page`, `Resource`, `Table` ou `Schema`.
2. Ler a view Blade e partials conectadas.
3. Localizar estilos inline, classes duplicadas e blocos locais reaproveitaveis.
4. Comparar com o padrao documentado do design system.
5. Ajustar somente apresentacao, nunca o fluxo funcional.

## Arquivos que podem ser alterados

- `resources/views/filament/**`
- `resources/css/**`
- `app/Providers/Filament/**`
- `app/Filament/**` apenas quando o trecho alterado for texto, rotulo visual ou wrapper de apresentacao

## Arquivos somente consulta

- `app/Services/**`
- `app/Policies/**`
- `app/Models/**`
- `database/**`

## Limites de seguranca

- Nao mexer em `modifyQueryUsing`, filtros, policies ou services.
- Nao trocar schema de formulario junto com regra de persistencia.
- Nao simplificar tela retirando dados importantes.

## Checklist de validacao

- Confirmar consistencia de espacamento, tipografia, badge e acao.
- Confirmar que modais e slideovers seguem acabamento coerente.
- Confirmar ausencia de regressao em headers e tabs.

## Saida esperada

- lista objetiva de desvios visuais
- correcao visual limitada ao necessario
- risco funcional claramente apontado quando houver

