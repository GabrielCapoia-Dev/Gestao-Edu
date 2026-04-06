# Merenda, Contratos e Estoque

## Objetivo

Explicar como pedidos de merenda, contratos e estoque se conectam e quais sao as regras que protegem saldo e entrega.

## Onde isso vive no codigo

- `app/Models/Contrato.php`
- `app/Models/ContratoItem.php`
- `app/Models/PedidoMerenda.php`
- `app/Models/PedidoMerendaItem.php`
- `app/Models/Estoque.php`
- `app/Models/EstoqueMovimentacao.php`
- `app/Filament/Admin/Resources/PedidosMerenda`
- `app/Filament/Admin/Pages/GestaoEstoque.php`
- `app/Filament/Admin/Pages/GestaoMargens.php`

## Comportamento e regras principais

- `ContratoItem` calcula `saldo_disponivel` como `quantidade_total - quantidade_utilizada - quantidade_reservada`.
- Ao criar pedido de merenda, os itens representam reserva ou consumo de saldo de contrato.
- `PedidoMerenda` recalcula o status com base na soma entregue versus soma pedida:
  - nada entregue: `Aguardando`
  - parte entregue: `Parcialmente Entregue`
  - tudo entregue: `Entregue`
- `PedidoMerendaItem` nao permite alterar `quantidade_pedida` depois da criacao.
- `quantidade_entregue` nao pode exceder `quantidade_pedida`.
- `registrarEntrega()` move quantidade de `reservada` para `utilizada` no contrato e recalcula o status do pedido.
- O cancelamento devolve apenas o saldo pendente para `quantidade_reservada`; o que ja foi entregue permanece consumido.
- `Estoque` registra entradas e saidas com rastreabilidade em `EstoqueMovimentacao`.

## Regra de negocio

- Reserva, utilizacao e entrega sao estados diferentes e precisam continuar coerentes.
- Pedido parcialmente entregue e um estado funcional, nao apenas visual.
- Cancelamento nao apaga historico de entrega ja realizada.

## Regra tecnica

- Parte do comportamento operacional de pedido de merenda esta nas actions da tabela do Filament.
- `GestaoMargens` e `GestaoEstoque` sao paginas de leitura e monitoramento importante para debug operacional.

## Riscos e cuidados

- Qualquer alteracao em `ContratoItem`, `PedidoMerendaItem` ou `PedidoMerendaTable` pode desbalancear saldo.
- A ausencia de testes deixa alta a chance de regressao em calculos.
- Seeder de merenda altera diretamente reserva/utilizacao; mudancas de regra exigem revisar seeds tambem.

## Quando consultar

- Antes de mexer em contratos, itens, estoque ou pedido de merenda
- Antes de ajustar calculo de saldo ou cancelamento
