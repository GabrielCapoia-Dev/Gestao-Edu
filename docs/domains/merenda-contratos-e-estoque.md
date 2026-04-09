# Merenda, Contratos e Estoque

- Dominio: alimentacao escolar na matriz, com contratos, pedidos de merenda, saldo reservado/utilizado e estoque operacional
- Status: `pilot`
- Fontes consultadas: `docs/context-skills/merenda-contratos-e-estoque.md`, `docs/context-skills/fluxo-estoque-atual.md`, `app/Models/Contrato.php`, `app/Models/ContratoItem.php`, `app/Models/PedidoMerenda.php`, `app/Models/PedidoMerendaItem.php`, `app/Models/Estoque.php`, `app/Models/EstoqueMovimentacao.php`, `app/Models/BaixasEstoques.php`, `app/Services/Estoque/GestaoEstoqueDataService.php`, `app/Services/Estoque/BalancoEstoqueService.php`, `app/Filament/Admin/Resources/PedidosMerenda`, `app/Filament/Admin/Pages/GestaoEstoque.php`, `app/Filament/Admin/Pages/GestaoMargens.php`, `app/Services/Relatorios/EstoqueRelatorioService.php`, `app/Http/Controllers/EstoqueRelatorioController.php`

## Objetivo do dominio

Este dominio controla o abastecimento de merenda a partir dos contratos e o estoque operacional da matriz. Ele cobre cadastro e importacao de itens de contrato, reserva e consumo de saldo contratual, pedidos de merenda, entregas parciais, cancelamento, baixas operacionais, balancos de estoque e exportacoes relacionadas.

O dominio ainda nao representa inventario escolar por unidade. O estoque atual e centralizado na matriz e precisa ser tratado como fonte principal de saldo fisico desse fluxo.

## Entidades e tabelas principais

- `Contrato` e `contratos`
- `ContratoItem` e `contrato_item`
- `Item` e `items`
- `PedidoMerenda` e `pedido_merendas`
- `PedidoMerendaItem` e `pedido_merenda_items`
- `Estoque` e `estoque`
- `EstoqueMovimentacao` e `estoque_movimentacoes`
- `BaixasEstoques` e `baixas_estoques`
- `BalancoEstoque`, `BalancoEstoqueItem`, `BalancoEstoqueEvento` e tabelas de balanco
- enums relevantes: `TipoMovimentacao`, `MotivoBaixa`, `StatusPedidoMerenda`, `TipoItem`, `TipoItemContrato`, `UnidadeMedida`

## Pontos de entrada do codigo

- `app/Models/Contrato.php`
- `app/Models/ContratoItem.php`
- `app/Models/PedidoMerenda.php`
- `app/Models/PedidoMerendaItem.php`
- `app/Models/Estoque.php`
- `app/Models/EstoqueMovimentacao.php`
- `app/Models/BaixasEstoques.php`
- `app/Filament/Admin/Resources/PedidosMerenda`
- `app/Filament/Admin/Pages/GestaoMargens.php`
- `app/Filament/Admin/Pages/GestaoEstoque.php`
- `app/Services/Contratos/ContratoItemSpreadsheetService.php`
- `app/Services/Estoque/GestaoEstoqueDataService.php`
- `app/Services/Estoque/BalancoEstoqueService.php`
- `app/Services/Estoque/BalancoEstoqueBloqueioService.php`
- `app/Services/Relatorios/EstoqueRelatorioService.php`
- `app/Http/Controllers/EstoqueRelatorioController.php`
- `app/Http/Controllers/BaixasEstoqueRelatorioController.php`
- `app/Http/Controllers/BalancoEstoqueRelatorioController.php`
- `app/Http/Controllers/PedidoMerendaEmpenhoController.php`

## Fluxos principais

### Contrato e saldo contratual

- itens de contrato podem ser importados por planilha
- `ContratoItem` calcula `saldo_disponivel` a partir de `quantidade_total`, `quantidade_utilizada` e `quantidade_reservada`
- a importacao de planilha e um ponto de entrada relevante para popular saldo de contrato

### Pedido de merenda

- o pedido usa `PedidoMerenda` e `PedidoMerendaItem`
- ao criar o pedido, os itens representam reserva ou consumo do saldo de contrato, dependendo do fluxo operacional da tabela e das actions
- `quantidade_pedida` nao deve ser alterada depois da criacao
- `quantidade_entregue` nao pode exceder `quantidade_pedida`
- o status do pedido e recalculado com base na soma entregue versus soma pedida

### Entrega parcial, total e cancelamento

- `registrarEntrega()` move quantidade de reservada para utilizada
- nada entregue: pedido aguardando
- parte entregue: pedido parcialmente entregue
- tudo entregue: pedido entregue
- cancelamento devolve apenas o saldo ainda pendente para `quantidade_reservada`; o que ja foi entregue permanece consumido

### Estoque da matriz

- `Estoque` possui uma linha por `item_id`
- `entrada()` e `saida()` alteram saldo fisico e registram `estoque_movimentacoes`
- `registrarBaixa()` gera saida operacional com rastreabilidade em `baixas_estoques`

### Balanco e exportacao

- `BalancoEstoqueService` agenda, inicia, registra contagens, adia, cancela e conclui balancos
- ao iniciar um balanco, o sistema cria snapshot e bloqueia movimentacoes normais para itens em contagem
- o modulo possui exportacoes PDF e XLSX de estoque geral, item individual, baixas e balanco

## Permissoes, policies e filtros de escopo

### Permissoes Spatie

- `PedidoMerendaPolicy` usa `Listar Pedidos: Merenda`, `Criar Pedidos: Merenda`, `Editar Pedidos: Merenda` e `Excluir Pedidos: Merenda`
- o estoque da matriz depende de permissoes especificas como `Listar Gestao de Estoque`, `Criar Balancos de Estoque`, `Listar Balancos de Estoque`, `Iniciar Balancos de Estoque`, `Registrar Contagem de Balancos de Estoque`, `Concluir Balancos de Estoque`, `Adiar Balancos de Estoque`, `Cancelar Balancos de Estoque` e `Exportar Relatorios`

### Policies

- `PedidoMerendaPolicy` responde pela capacidade ampla do recurso
- o estoque operacional usa mais permissao direta e regras de service/page do que policy dedicada

### Filtros de escopo

- hoje o estoque da matriz nao usa escopo por escola
- o pedido de merenda atual tambem nao representa pedido escola -> matriz
- o projeto ja conhece `id_escola` em outros modulos, mas esse escopo ainda nao foi aplicado ao estoque atual

## Regras de negocio criticas

- reserva, utilizacao e entrega sao estados diferentes e precisam continuar coerentes
- entrega parcial e um estado funcional do pedido, nao apenas uma etiqueta visual
- cancelamento nao apaga entrega ja realizada
- o estoque da matriz continua sendo saldo centralizado por `item_id`
- itens em balanco ativo nao podem receber movimentacoes normais

## Efeitos colaterais

- Historico: `estoque_movimentacoes`, `baixas_estoques` e eventos de balanco registram a trilha operacional
- Notificacao: nao foi identificado fluxo dedicado de notificacao neste dominio
- Exportacao: empenho de pedido de merenda e relatorios PDF/XLSX de estoque, baixas e balancos
- Arquivos: importacao de itens de contrato por planilha e geracao de relatorios
- Scheduler: nao foi identificado scheduler especifico deste dominio

## Seeders, enums e vocabulario funcional dependente

- saldo contratual depende de termos como `quantidade_total`, `quantidade_reservada`, `quantidade_utilizada` e `saldo_disponivel`
- status de pedido de merenda e enums de tipo de item sao vocabulario funcional do dominio
- seeders de merenda e estoque podem assumir a regra atual de reserva e utilizacao

## Pontos seguros para extensao

- nova regra de caso de uso: concentrar em service ou model canonico do agregado, nao em action isolada do Filament
- nova visualizacao operacional: reaproveitar `GestaoEstoqueDataService` e padrao de relatorios
- novo fluxo por escola: criar agregado proprio de inventario escolar, sem reutilizar diretamente `estoque` da matriz
- nova importacao ou exportacao: seguir padrao de service dedicado e controller fino

## Riscos de regressao

- alterar `ContratoItem`, `PedidoMerendaItem` ou actions de tabela pode desbalancear saldo reservado versus utilizado
- alterar `Estoque::saida()` ou `registrarBaixa()` pode quebrar balancos e relatorios
- ausencia de escopo por escola no estoque atual pode induzir implementacoes erradas de inventario escolar
- falta de testes para pedidos de merenda deixa alta a chance de regressao em entrega parcial e cancelamento

## Testes existentes e lacunas

- `tests/Feature/Contratos/ContratoItemSpreadsheetServiceTest.php` cobre importacao de itens de contrato
- `tests/Feature/Estoque/BalancoEstoqueTest.php` cobre o fluxo principal de balanco da matriz
- `tests/Feature/Seeders/EstoqueInventarioOrganicoSeederTest.php` ajuda a validar seeder ligado ao fluxo de estoque/inventario
- lacunas principais:
  - criacao e edicao de `PedidoMerenda`
  - reserva de saldo contratual
  - entrega parcial e total
  - cancelamento com devolucao apenas do saldo pendente
  - permissao e visibilidade do modulo de merenda
