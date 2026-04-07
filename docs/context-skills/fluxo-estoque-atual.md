# Fluxo Atual de Estoque

## Escopo atual

O estoque existente representa a matriz logistica da merenda. Ele concentra o saldo principal da rede e hoje nao possui divisao por escola.

## Onde o fluxo vive

- `app/Models/Estoque.php`
- `app/Models/EstoqueMovimentacao.php`
- `app/Models/BaixasEstoques.php`
- `app/Models/BalancoEstoque.php`
- `app/Models/BalancoEstoqueItem.php`
- `app/Models/BalancoEstoqueEvento.php`
- `app/Services/Estoque/GestaoEstoqueDataService.php`
- `app/Services/Estoque/BalancoEstoqueService.php`
- `app/Services/Estoque/BalancoEstoqueBloqueioService.php`
- `app/Filament/Admin/Pages/GestaoEstoque.php`
- `app/Filament/Admin/Resources/BaixasEstoque`
- `app/Filament/Admin/Resources/HistoricoBaixasEstoque`
- `app/Filament/Admin/Resources/BalancosEstoque`
- `app/Services/Relatorios/EstoqueRelatorioService.php`
- `app/Http/Controllers/EstoqueRelatorioController.php`
- `routes/web.php`

## Modelagem principal

### Estoque da matriz

- `estoque` possui uma linha por item.
- Cada linha e unica por `item_id`.
- O saldo fisico fica em `quantidade`.
- `Estoque::entrada()` incrementa o saldo e grava `estoque_movimentacoes`.
- `Estoque::saida()` decrementa o saldo e grava `estoque_movimentacoes`.
- `Estoque::registrarBaixa()` faz uma saida operacional com rastreabilidade em `baixas_estoques`.

### Movimentacoes

- `estoque_movimentacoes` registra entradas e saidas.
- A movimentacao pode guardar `pedido_merenda_id`, observacao e usuario textual em `registrado_por`.
- O enum usado e `App\Models\Enums\TipoMovimentacao`.

### Baixas

- `baixas_estoques` registra motivo, descricao, saldo anterior, saldo posterior e responsavel textual.
- A tabela de baixas opera sobre o proprio registro de `Estoque`.
- O enum usado e `App\Models\Enums\MotivoBaixa`.

### Balancos

- O balanco e separado em `balancos_estoque`, `balanco_estoque_itens` e `balanco_estoque_eventos`.
- Ao iniciar um balanco, o sistema cria um snapshot dos itens elegiveis.
- Enquanto um item esta em balanco em andamento, `BalancoEstoqueBloqueioService` impede movimentacoes normais.
- Ao concluir, o sistema ajusta o saldo real usando `entrada()` ou `saida()` e registra eventos de auditoria.

## Telas e comportamento

### Gestao de Estoque

- `app/Filament/Admin/Pages/GestaoEstoque.php` monta cards, filtros, paginacao e slide-over de movimentacoes por item.
- `GestaoEstoqueDataService` centraliza:
  - filtros e ordenacao
  - listagem de itens
  - movimentacoes por item
  - baixas por item
  - metricas gerais
  - agregacao por categoria

### Baixas

- `BaixasEstoqueResource` lista os itens do estoque e abre modal para baixa.
- A acao chama `Estoque::registrarBaixa()`.
- `HistoricoBaixasEstoqueResource` lista o historico consolidado das baixas.

### Balancos

- `BalancoEstoqueResource` lista e detalha balancos.
- `ViewBalancoEstoque` concentra as acoes de iniciar, adiar, cancelar e concluir.
- O fluxo principal fica em `BalancoEstoqueService`.

## Relatorios existentes

- Rotas em `routes/web.php` exportam:
  - relatorio geral do estoque em PDF/XLSX
  - relatorio individual de item em PDF/XLSX
  - relatorio de baixas
  - relatorio de balanco
- `EstoqueRelatorioService` usa `GestaoEstoqueDataService` para montar resumo, itens, movimentacoes e baixas.

## Permissoes e hierarquia atual

- O modulo usa permissoes Spatie criadas em `app/Console/Commands/CriarPermissoes.php`.
- O acesso ao estoque da matriz hoje depende principalmente de:
  - `Listar Gestão de Estoque`
  - `Criar Balanços de Estoque`
  - `Listar Balanços de Estoque`
  - `Iniciar Balanços de Estoque`
  - `Registrar Contagem de Balanços de Estoque`
  - `Concluir Balanços de Estoque`
  - `Adiar Balanços de Estoque`
  - `Cancelar Balanços de Estoque`
  - `Exportar Relatórios`
- A hierarquia por escola ja existe no sistema por `users.id_escola`, mas o estoque da merenda ainda nao usa esse escopo.

## Relacao com pedidos de merenda

- O pedido de merenda atual nao representa pedido interno escola -> matriz.
- Ele opera sobre contratos e reservas de `ContratoItem`.
- Referencias principais:
  - `app/Models/PedidoMerenda.php`
  - `app/Models/PedidoMerendaItem.php`
  - `app/Filament/Admin/Resources/PedidosMerenda`
- O pedido de merenda atual nao cria inventarios por escola e nao separa saldo por unidade.

## Pontos de extensao seguros

- Criar um novo agregado de inventario por escola, sem reutilizar diretamente `estoque` da matriz.
- Reaproveitar `Item`, enums de movimentacao e padrao de relatorios.
- Reaproveitar `users.id_escola` para escopo do gestor escolar.
- Reaproveitar a arquitetura de `DataService` e `RelatorioService` para dashboards e exportacoes.

## Riscos antes de evoluir

- Alterar `Estoque::saida()` ou `Estoque::registrarBaixa()` pode quebrar balancos e relatorios existentes.
- O estoque atual nao possui reserva propria; qualquer reserva da matriz precisa ser modelada com cuidado para nao distorcer saldo.
- O bloqueio por balanco em andamento precisa continuar funcionando, inclusive se a nova funcionalidade criar outro tipo de movimentacao.
- Como relatorios e telas usam consultas dedicadas, novas colunas exigem revisar exportacao PDF/XLSX e visoes Blade.

## Testes que protegem o modulo atual

- `tests/Feature/Estoque/BalancoEstoqueTest.php` cobre o fluxo principal do balanco.
- Ainda ha pouco teste automatizado para o restante do estoque, entao mudancas em saldo e reserva pedem cobertura adicional.
