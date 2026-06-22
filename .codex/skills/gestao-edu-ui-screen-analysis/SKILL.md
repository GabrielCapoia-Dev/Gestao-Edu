---
name: gestao-edu-ui-screen-analysis
description: Use para analisar uma tela do Gestao-Edu antes de qualquer alteracao visual, lendo provider, page/resource, views, partials, CSS e possiveis acoplamentos com permissoes, filtros, actions e queries.
---

# Analise de Tela UI

## Objetivo

Mapear a superficie visual real da tela antes de editar qualquer detalhe de UI.

## Sequencia recomendada

1. Ler o `PanelProvider` relevante.
2. Ler a `Page`, `Resource`, `Table` ou `Form` da tela.
3. Ler as `views` e `partials` usadas pela tela.
4. Ler o CSS base e o CSS local conectado a essa superficie.
5. Identificar onde termina a camada visual e onde comeca a regra de negocio.

## Arquivos que podem ser alterados

- `resources/views/**`
- `resources/css/**`
- `resources/js/**` quando o JS for estritamente visual
- `app/Providers/Filament/**` quando a mudanca for token, fonte, logo, render hook ou tema visual

## Arquivos somente consulta

- `app/Services/**`
- `app/Policies/**`
- `app/Models/**`
- `database/**`
- `routes/**`
- `app/Filament/**` fora do ponto estritamente visual da tela

## Limites de seguranca

- Nao assumir que uma mudanca e apenas visual sem ler o fluxo completo da tela.
- Nao refatorar query, filtro, action, permissao ou validacao junto com acabamento visual.
- Nao iniciar servidor, navegador, Docker ou build.

## Checklist de validacao

- Confirmar arquivos canonicos lidos.
- Confirmar pontos de acoplamento com regra.
- Confirmar o que pode e o que nao pode ser alterado.
- Registrar risco residual se houver HTML inline ou `HtmlString`.

## Saida esperada

- mapa curto da tela
- arquivos visuais envolvidos
- riscos para regra de negocio
- estrategia segura de alteracao visual

