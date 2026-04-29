# Fluxo de Pedidos de Manutencao

## Objetivo

Documentar o ciclo de vida de `Pedido`, incluindo criacao, protocolo, historico, anexos, avaliacao e notificacoes.

## Onde isso vive no codigo

- `app/Models/Pedido.php`
- `app/Models/PedidoHistorico.php`
- `app/Models/PedidoArquivo.php`
- `app/Models/FeedbackPedido.php`
- `app/Services/PedidoService.php`
- `app/Observers/PedidoObserver.php`
- `app/Filament/Admin/Resources/Pedidos`
- `database/seeders/TipoStatusSeeder.php`

## Comportamento e regras principais

- `Pedido` gera `numero_protocolo` automaticamente no `creating`, baseado no ano e no maior protocolo existente.
- A criacao de pedido usa `PedidoService::criarPedido()`.
- O status inicial esperado e `Em Aberto`.
- O setor inicial esperado e `Educação`.
- Arquivos enviados na criacao entram como `FOTOS_PROBLEMA`.
- Toda mudanca relevante deve deixar rastreabilidade em `PedidoHistorico`.
- O botao `Gerenciar` pode assumir o pedido e mover para `Em Análise` dependendo do status atual e do setor do usuario.
- A avaliacao do pedido cria `FeedbackPedido`; nota `1` reabre automaticamente o pedido, outras notas concluem.
- `PedidoArquivo` registra historico automaticamente ao criar, atualizar ou remover arquivo.
- `PedidoObserver` dispara notificacoes quando prioridade vira emergencial e quando o status muda.

## Regra de negocio

- Pedido pertence a uma escola, um solicitante e um setor atual.
- O fluxo usa status nomeados e entendidos pelo negocio, como `Em Aberto`, `Em Análise`, `Em Manutenção`, `Concluído` e `Reaberto`.
- Pedido concluido pode ser reaberto por avaliacao ruim.
- Historico e anexos fazem parte do fluxo funcional, nao sao apenas auditoria tecnica.

## Regra tecnica

- Querys e visibilidade variam por perfil em `PedidoService`.
- Parte importante do comportamento mora em actions e forms do Filament, nao apenas no service.
- Notificações são gravadas em banco, não enfileiradas em jobs customizados.

## Infraestrutura relacionada

- Scheduler roda commands de notificacao de vencimento e atraso.
- Downloads de arquivos de pedido passam por rota protegida e policy.

## Riscos e cuidados

- O fluxo depende de nomes exatos de status e setores seedados.
- Observer, service e table actions propagam efeitos colaterais diferentes; alterar so um ponto pode quebrar a consistencia.
- Existe trecho suspeito no observer de reabertura notificando o solicitante dentro do loop de usuarios, o que merece cuidado em futuras alteracoes.

## Quando consultar

- Antes de mudar `Pedido`, `TipoStatus`, `PedidoArquivo`, `FeedbackPedido` ou a UI de manutencao
- Antes de alterar notificacoes ou historico
