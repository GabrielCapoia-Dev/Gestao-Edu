---
name: gestao-edu-kit
description: Use antes de mudancas amplas ou transversais no Gestao-Edu em Laravel 12 e Filament 5, escolhendo skills de dominio, confirmando o comportamento atual no codigo e definindo validacao focada.
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
- pessoas e servidores, calendario, avisos, transporte e exportacoes em fila

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
   - `$gestao-edu-pessoas-servidores-flow` para Pessoa, Servidor, matriculas, funcoes, equipes e consolidacao de legados
   - `$gestao-edu-saldo-eleitoral-flow` para saldo eleitoral de servidores, solicitações de adição/uso/estorno, aprovação RH, descontos e relatórios
   - `$gestao-edu-dashboard-calendario-flow` para dashboard, calendario, avisos, publico-alvo, transporte e reservas
   - `$estoque-flow-mapper` para contratos, estoque, merenda, inventarios escolares, balancos e romaneios
   - `$manutencao-fluxo-pedidos` para pedidos de manutencao, status, anexos, historico e notificacoes
   - `$manutencao-cadastros-tipos` para tipos, status, setores, empresas, seeders e permissoes de manutencao
   - `$manutencao-relatorios-feedback` para PDFs, exportacoes, dashboards, anexos e feedbacks de manutencao
   - `$gestao-edu-exportacoes-flow` para infraestrutura compartilhada de exportacoes em fila, armazenamento, download e monitoramento
   - `$gestao-edu-revisao-lingua-portuguesa` sempre que a tarefa criar, alterar ou revisar textos visiveis
5. Confirmar no codigo atual os pontos canonicos antes de propor alteracao.

## Checklist de analise

- Identificar o dominio principal e os dominios transversais.
- Confirmar se a mudanca toca permissao, policy, role, menu, `id_escola` ou `setor_id`.
- Confirmar se ha efeito em dados historicos, exportacoes, notificacoes ou filas.
- Tratar `app/Filament/Admin` como superficie canonica; consultar caminhos legados em `app/Filament` apenas se ainda forem referenciados.
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

## Manutencao evolutiva das skills

Ao concluir uma implementacao que amplia um fluxo, introduz regra duradoura ou revela divergencia na documentacao do dominio, revise apenas as skills diretamente afetadas. Use `skill-creator` para fazer uma atualizacao pequena e especifica; valide descricoes, referencias e instrucoes contra o comportamento confirmado no codigo. Ajustes pontuais ou temporarios nao justificam alterar skills. Se surgir um novo dominio ou fluxo, atualize o mapa de modulos e o roteamento deste kit quando aplicavel. Encerre apos registrar o conhecimento duradouro, sem expandir para skills nao relacionadas.
