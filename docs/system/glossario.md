# Glossario

## Termos de acesso

- `role`: papel amplo atribuido ao usuario via Spatie Permission
- `permission`: capacidade fina, normalmente nomeada por verbo e recurso
- `policy`: camada de autorizacao ampla por recurso
- `id_escola`: chave de escopo usada para restringir visibilidade por unidade
- `setor`: agrupamento funcional usado em fluxos como manutencao
- `email_approved`: gate final para liberar o painel ao usuario autenticado

## Termos de manutencao

- `Pedido`: solicitacao de manutencao predial
- `PedidoHistorico`: trilha de alteracoes de status e autoria
- `FeedbackPedido`: avaliacao do pedido apos conclusao

## Termos de merenda e estoque

- `ContratoItem`: item contratado com saldo total, reservado e utilizado
- `saldo_disponivel`: quantidade ainda disponivel no contrato para reserva ou consumo
- `PedidoMerenda`: pedido operacional de merenda vinculado a contratos
- `quantidade_reservada`: quantidade comprometida, mas ainda nao consumida
- `quantidade_utilizada`: quantidade ja efetivamente consumida ou entregue
- `Estoque`: saldo fisico centralizado por item na matriz
- `EstoqueMovimentacao`: trilha de entradas e saidas do estoque
- `balanco`: processo de contagem e ajuste de saldo com snapshot e bloqueio
- `romaneio`: consolidacao logistica de itens para envio, especialmente no fluxo de inventario escolar

## Termos de documentacao

- `domain doc`: documento canonico de um dominio funcional
- `feature brief`: resumo de desenho obrigatorio antes de implementar
- `ponto canonico da regra`: camada principal onde a regra deve viver
- `docs/context-skills`: base semente de contexto que alimenta a documentacao canonica
