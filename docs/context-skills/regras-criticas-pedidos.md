# Regras Criticas de Pedidos

## Objetivo

Destacar os pontos mais sensiveis do fluxo de manutencao para reduzir regressao em mudancas futuras.

## Onde isso vive no codigo

- `app/Services/PedidoService.php`
- `app/Observers/PedidoObserver.php`
- `app/Models/Pedido.php`
- `app/Models/PedidoArquivo.php`
- `database/seeders/TipoStatusSeeder.php`
- `database/seeders/SetorSeeder.php`

## Regras principais

- O fluxo depende de strings seedadas:
  - `Em Aberto`
  - `Em Análise`
  - `Encaminhado ao Setor`
  - `Em Manutenção`
  - `Concluído`
  - `Reaberto`
  - `Educação`
  - `Obras`
- O badge e as querys de pedidos novos tambem dependem dessas strings e do perfil do usuario.
- A conclusao do pedido nao e apenas trocar status:
  - pode gravar `data_entrega`
  - pode criar feedback
  - pode anexar fotos de conclusao
  - pode disparar notificacoes
- A reabertura por nota `1` e regra de negocio, nao workaround de interface.
- Alteracoes em arquivo geram historico automaticamente.

## Impacto de mudancas

- Renomear status ou setor sem revisar services, observer, seeders e filtros pode quebrar o fluxo.
- Alterar protocolo pode afetar ordenacao, identificacao humana e relatios.
- Mexer em `PedidoArquivo` altera rastreabilidade de historico.
- Mexer em `avaliarPedido()` altera fechamento, reabertura e qualidade dos dados de feedback.

## Pontos de atencao

- O sistema mistura regra de dominio com comportamento de Filament.
- Policies de `Pedido` sao amplas; o filtro real por escola/perfil depende tambem do service.
- A falta de testes automatizados aumenta o risco de regressao silenciosa.

## Quando consultar

- Antes de mexer em qualquer status
- Antes de alterar notificacoes, feedback ou historico
- Antes de refatorar `PedidoService` ou observer
