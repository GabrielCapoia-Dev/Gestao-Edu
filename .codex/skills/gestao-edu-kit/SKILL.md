---
name: gestao-edu-kit
description: Use antes de mudancas grandes no Gestao-Edu em Laravel, Filament, Livewire, policies, services, relatorios ou dados, escolhendo skills de dominio, confirmando o comportamento atual no codigo e definindo validacao focada.
---

# Gestao-Edu Kit

Use esta skill para orientar mudancas amplas no projeto Gestao-Edu ou quando a tarefa atravessar mais de um fluxo funcional.

## Objetivo

Escolher a skill especializada correta e montar uma sequencia segura de analise para evitar regressao em:

- painel Filament e paginas Livewire
- regras de dominio em services e models
- policies, roles, permissoes e filtros por escola ou setor
- relatorios PDF, XLSX, CSV e exportacoes em fila
- fluxos de dados com alunos, professores, turmas, avaliacoes, estoque ou manutencao

## Sequencia recomendada

1. Ler [`docs/context-skills/mapa-de-modulos.md`](../../../docs/context-skills/mapa-de-modulos.md).
2. Ler [`docs/context-skills/arquitetura-geral.md`](../../../docs/context-skills/arquitetura-geral.md).
3. Se a mudanca for sensivel, ler:
   - [`docs/cross-cutting/checklist-alteracao-sensivel.md`](../../../docs/cross-cutting/checklist-alteracao-sensivel.md)
   - [`docs/context-skills/testes-e-debug.md`](../../../docs/context-skills/testes-e-debug.md)
4. Escolher as skills de dominio:
   - `$gestao-edu-acesso-permissoes-flow` para login, roles, permissoes, policies, menus e escopo
   - `$gestao-edu-professores-turmas-componentes-flow` para professores, turmas, series e componentes
   - `$gestao-edu-avaliacoes-flow` para avaliacoes, pautas, alternativas, respostas e pareceres
   - `$gestao-edu-transferencia-remanejamento-flow` para matricula pendente, transferencia, remanejamento e parecer de transferencia
   - `$estoque-flow-mapper` para estoque, merenda, inventarios, reservas e romaneios
   - `$manutencao-fluxo-pedidos` para pedidos de manutencao, status, anexos, historico e notificacoes
   - `$manutencao-cadastros-tipos` para tipos, status, setores, empresas, seeders e permissoes de manutencao
   - `$manutencao-relatorios-feedback` para PDFs, exportacoes, dashboards, anexos e feedbacks de manutencao
   - `$gestao-edu-revisao-lingua-portuguesa` sempre que a tarefa criar, alterar ou revisar textos visiveis
5. Confirmar no codigo atual os pontos canonicos antes de propor alteracao.

## Checklist de analise

- Identificar o dominio principal e os dominios transversais.
- Confirmar se a mudanca toca permissao, policy, role, menu, `id_escola` ou `setor_id`.
- Confirmar se ha efeito em dados historicos, exportacoes, notificacoes ou filas.
- Revisar com `$gestao-edu-revisao-lingua-portuguesa` labels, mensagens, validacoes, notificacoes e saidas geradas que forem alteradas.
- Localizar testes existentes antes de criar novos.
- Preferir validacao focada por arquivo ou grupo pequeno de feature tests.
- Registrar risco residual quando o fluxo depender de validacao manual.

## Saida esperada

Ao final da analise, registrar:

- skills acionadas
- arquivos canonicos consultados
- comportamento atual confirmado
- estrategia de implementacao
- testes focados e validacao manual necessaria
