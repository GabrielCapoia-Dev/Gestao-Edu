# Regras Criticas de Pedidos

## Objetivo

Destacar os pontos mais sensiveis do fluxo de manutencao para reduzir regressao em mudancas futuras.

## Onde isso vive no codigo

- `app/Services/PedidoService.php`
- `app/Observers/PedidoObserver.php`
- `app/Models/Pedido.php`
- `app/Models/PedidoProblema.php`
- `app/Models/FeedbackPedidoItem.php`
- `database/seeders/TipoStatusSeeder.php`
- `database/seeders/SetorSeeder.php`

## Regras principais

- O fluxo depende de strings seedadas:
  - `Em Aberto`
  - `Em Analise`
  - `Encaminhado ao Setor`
  - `Enviado para Empresa`
  - `Em Manutencao`
  - `Concluido`
  - `Reaberto`
  - `Pedido Adicional`
  - `Educacao`
  - `Obras`
- `Em Andamento` deve permanecer inativo e indisponivel para novos movimentos.
- A conclusao do pedido nao e apenas trocar status:
  - pode gravar `data_entrega`
  - cria feedback agregado
  - cria itens por problema
  - pode anexar fotos de conclusao
  - pode disparar notificacoes
- Nota `1` nao reabre pedido. Reabertura depende do campo/botao `reabrir_pedido`.
- Encaminhamento Educacao -> Obras deve terminar com status atual `Encaminhado ao Setor` no setor Obras, sem misturar o pedido com novos pedidos `Em Aberto`.
- `Pedido Adicional` nao passa pelo tramite completo e deve ficar vinculado ao pedido principal por `pedido_principal_id`.
- Tipos e opcoes inativas nao aparecem em novos pedidos, mas nao podem ser removidos dos historicos.

## Impacto de mudancas

- Renomear status ou setor sem revisar services, observer, seeders, filtros e docs pode quebrar o fluxo.
- Alterar protocolo afeta ordenacao, identificacao humana, relatorios e vinculo de adicionais.
- Mexer em `avaliarPedido()` altera fechamento, reabertura, fotos e qualidade dos dados de feedback.
- Mexer em escopo por setor precisa considerar `roles.setor_id`, `Listar Todos os Pedidos` e fallback por escola.
- A matriz `setor_acessos` vale apenas para Pedidos de Manutencao na primeira versao; outros modulos continuam usando `UserSetorAccessService`.
- Listagem considera o setor atual ou o setor de origem, permitindo que a origem acompanhe pedidos encaminhados.
- Edicao e cancelamento avaliam o setor atual; encaminhamento exige editar o setor atual e poder encaminhar ao destino.

## Pontos de atencao

- O sistema mistura regra de dominio com comportamento de Filament.
- Policies cobrem capacidade ampla; o filtro real por escola/setor depende do `PedidoService`.
- Relatorios e PDFs precisam ser atualizados junto com qualquer novo campo operacional.
- Testes focados devem cobrir criacao, escopo, encaminhamento, empresa, adicionais e feedback por problema.

## Quando consultar

- Antes de mexer em qualquer status.
- Antes de alterar notificacoes, feedback, relatorios ou historico.
- Antes de refatorar `PedidoService`, observer ou forms/actions do Filament.
