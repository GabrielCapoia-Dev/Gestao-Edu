---
name: manutencao-fluxo-pedidos
description: Use quando for preciso analisar ou evoluir o fluxo de pedidos de manutencao deste projeto, incluindo Pedido, PedidoService, historico, anexos, setores, status, pedidos adicionais, notificacoes e escopo por usuario.
---

# Manutencao: Fluxo de Pedidos

Use esta skill antes de alterar pedidos de manutencao, transicoes de status, setores operacionais, anexos, historico, notificacoes ou regras de visibilidade.

## Objetivo

Mapear o comportamento atual do ciclo de vida de `Pedido` para evitar regressao em criacao, gestao, encaminhamento, conclusao, reabertura, pedidos adicionais e escopo por usuario.

## Fluxo recomendado

1. Leia primeiro:
   - `docs/context-skills/fluxo-pedidos-manutencao.md`
   - `docs/context-skills/regras-criticas-pedidos.md`
   - `docs/context-skills/autenticacao-e-autorizacao.md`, se a mudanca tocar permissoes ou escopo
2. Confirme no codigo os pontos centrais:
   - `app/Models/Pedido.php`
   - `app/Models/PedidoProblema.php`
   - `app/Models/PedidoHistorico.php`
   - `app/Models/PedidoArquivo.php`
   - `app/Services/PedidoService.php`
   - `app/Observers/PedidoObserver.php`
   - `app/Filament/Admin/Resources/Pedidos`
3. Antes de editar, identifique:
   - qual metodo centraliza a regra
   - quais actions/forms do Filament chamam a regra
   - qual historico ou notificacao deve ser preservado
   - quais permissoes e setores limitam a operacao

## Regras sensiveis

- O status inicial de pedidos comuns e `Em Aberto`; pedidos adicionais usam `Pedido Adicional`.
- Encaminhamento para setor deve preservar rastreabilidade no historico.
- Tipos e opcoes inativas nao entram em novos pedidos, mas devem continuar em historicos e relatorios.
- Nota baixa no feedback nao reabre pedido sozinha; reabertura depende da acao explicita.
- `PedidoService` e o ponto preferencial para regras transacionais do fluxo.
- Alteracoes em status, setor, responsavel, empresa, anexos ou conclusao devem considerar observer, historico e notificacoes.

## Estrategia de implementacao

- Prefira expandir `PedidoService` em vez de espalhar regra nova em actions do Filament.
- Use queries com escopo por `UserSetorAccessService`, permissao global e fallback por escola.
- Preserve pedidos adicionais fora da fila principal, mas visiveis por vinculo, relatorio e avaliacao.
- Se adicionar novo estado, revisar seeders, filtros, actions, relatorios, testes e docs de contexto.

## Validacao minima

- Cobrir criacao de pedido com tipo/opcoes ativas e data de identificacao.
- Cobrir escopo por setor, escola e permissao global.
- Cobrir encaminhamento, conclusao, reabertura e pedido adicional quando a mudanca tocar esses fluxos.
- Reexecutar ou atualizar `tests/Feature/Manutencao/PedidoServiceFluxoManutencaoTest.php`.
