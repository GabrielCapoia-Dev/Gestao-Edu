# Inventarios Escolares e Pedidos Internos

## Objetivo

Mapear como o estoque atual da matriz pode ser estendido para inventarios por escola e pedidos internos sem quebrar saldo, historico, exportacao e controle por escola.

## Onde isso toca no codigo

- `app/Models/Estoque.php`
- `app/Models/EstoqueMovimentacao.php`
- `app/Models/BaixasEstoques.php`
- `app/Models/BalancoEstoque.php`
- `app/Services/Estoque/GestaoEstoqueDataService.php`
- `app/Services/Estoque/BalancoEstoqueService.php`
- `app/Services/PedidoService.php`
- `app/Models/User.php`
- `app/Models/Escola.php`
- `app/Services/UserService.php`
- `routes/web.php`

## Modelo mental do estado atual

- O estoque de merenda atual representa a matriz logistica, com um unico saldo por `item_id`.
- Entradas e saidas sao persistidas em `estoque_movimentacoes`.
- Baixas operacionais usam `baixas_estoques` e sempre geram saida no estoque.
- Balancos de estoque criam snapshot, bloqueiam itens em contagem e aplicam reajustes no saldo ao concluir.
- Exportacoes ja existem para relatorio geral do estoque e historico individual por item.
- O sistema ja possui padrao de escopo por escola em outros modulos via `users.id_escola`.

## Regras de extensao para inventarios por escola

- O estoque da matriz deve continuar existindo como fonte principal de abastecimento.
- Cada escola precisa ter um `inventario` proprio vinculado a uma unica `escola`.
- O escopo padrao deve seguir o usuario autenticado:
  - usuario com `id_escola` ve apenas o inventario, pedidos e balancos da propria escola
  - gestor central sem `id_escola` ou com permissao central ve todos os inventarios
- Movimentacoes do inventario escolar precisam ser separadas das movimentacoes da matriz.
- Baixas escolares nao podem mexer diretamente no estoque da matriz.
- A reserva do estoque da matriz deve acontecer no momento do romaneio, nao no momento do pedido da escola.

## Regras de extensao para pedidos escola -> matriz

- Pedido interno deve referenciar `item_id`, nao `contrato_item_id`.
- A escola informa quantidade solicitada e observacao por item.
- O gestor central revisa cada item, podendo aprovar parcialmente ou recusar.
- O romaneio consolida varios pedidos aprovados, separa por escola e gera uma pagina final com totais.
- Ao gerar romaneio:
  - o pedido muda para `em_andamento`
  - o estoque da matriz incrementa quantidade reservada
- Na conferencia da escola:
  - a escola informa quantidade recebida por item
  - se a quantidade divergir do romaneio, observacao passa a ser obrigatoria
  - o inventario escolar recebe entrada
  - a matriz consome a reserva e registra a saida efetiva

## Padroes existentes que devem ser reaproveitados

- Services concentram regra de negocio com transacoes e validacoes.
- Pages personalizadas do Filament ja sao usadas para dashboards e telas mais densas.
- Resources e actions do Filament ja sao usadas para listas operacionais com filtros e modais.
- Relatorios PDF/XLSX seguem um padrao proprio do projeto em `app/Services/Relatorios`.

## Riscos reais

- `estoque` hoje nao possui `quantidade_reservada`; sem isso, romaneio vai distorcer saldo disponivel.
- O enum PHP `TipoMovimentacao` ja tem `transferencia`, mas a migration da matriz so aceita `entrada` e `saida`.
- O catalogo de permissoes vive em mais de um lugar; qualquer nova permissao precisa ser replicada com cuidado.
- O bloqueio de balanco atual protege apenas a matriz; inventarios escolares precisam de bloqueio proprio.
- O projeto ainda tem cobertura de testes concentrada em balancos; pedidos internos sem testes vao regredir com facilidade.

## Quando consultar

- Antes de mexer em estoque, inventario, pedidos internos ou romaneio
- Antes de alterar regras de saldo disponivel, reserva ou conferencia
- Antes de criar tela que dependa de escopo por escola
