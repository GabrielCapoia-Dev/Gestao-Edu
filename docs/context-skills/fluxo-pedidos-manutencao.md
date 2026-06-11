# Fluxo de Pedidos de Manutencao

## Objetivo

Documentar o ciclo de vida de `Pedido`, incluindo criacao, setor operacional, problemas segmentados, adicionais, historico, anexos, avaliacao por problema e notificacoes.

## Onde isso vive no codigo

- `app/Models/Pedido.php`
- `app/Models/PedidoProblema.php`
- `app/Models/TipoManutencaoOpcao.php`
- `app/Models/FeedbackPedido.php`
- `app/Models/FeedbackPedidoItem.php`
- `app/Services/PedidoService.php`
- `app/Observers/PedidoObserver.php`
- `app/Filament/Admin/Resources/Pedidos`
- `database/seeders/TipoStatusSeeder.php`

## Comportamento e regras principais

- `Pedido` gera `numero_protocolo` automaticamente no `creating`, baseado no ano e no maior protocolo existente.
- A criacao usa `PedidoService::criarPedido()`, exige `data_identificacao_problema` e salva uma ou mais frases/opcoes em `pedido_problemas`.
- Tipo de manutencao e opcoes inativas nao entram em novos pedidos, mas continuam disponiveis para historico e relatorios.
- O status inicial e `Em Aberto`; o setor inicial e `Educacao`.
- O botao `Gerenciar` assume o pedido e move `Em Aberto`, `Encaminhado ao Setor` ou `Reaberto` para `Em Analise`, respeitando o setor operacional do usuario.
- Educacao pode mover para `Em Manutencao`, `Cancelado` ou encaminhar para Obras.
- Encaminhar para Obras atualiza o status atual para `Encaminhado ao Setor`, muda o setor operacional e grava historico do encaminhamento.
- Obras pode mover para `Em Manutencao`, `Enviado para Empresa` ou `Cancelado`; empresa responsavel so e exibida/grava para usuario do setor Obras.
- `Em Andamento` fica inativo. `Concluido`, `Reaberto` e `Pedido Adicional` permanecem como status filtraveis.
- Pedidos adicionais nascem com status `Pedido Adicional`, `is_pedido_adicional = true` e `pedido_principal_id`.
- A avaliacao cria um `FeedbackPedido` agregado e itens em `feedback_pedido_itens`, um para cada problema do pedido principal e dos adicionais.
- Nota `1` nao reabre automaticamente. A reabertura depende do botao global `Reabrir pedido`.
- Se `Reabrir pedido` estiver marcado, o status final e `Reaberto`; caso contrario, `Concluido` e grava `data_entrega`.

## Escopo de setor

- `users.setor_id` define o setor operacional principal; `roles.setor_id` permanece como fallback legado.
- `setor_acessos` define a matriz entre setor de origem e setor alvo para listar, editar, cancelar e encaminhar pedidos.
- O proprio setor recebe listar, editar e cancelar automaticamente; encaminhar para ele mesmo permanece bloqueado.
- Permissoes gerais do usuario e capacidade contextual do setor sao obrigatorias em conjunto.
- `Listar Todos os Pedidos` continua liberando visao global.
- Sem setor operacional, o usuario cai para escopo por escola (`id_escola`) quando aplicavel.
- Usuarios vinculados a escola continuam limitados as escolas vinculadas antes da avaliacao da matriz.

## Riscos e cuidados

- O fluxo depende de nomes seedados de status e setores; use `PedidoService::statusPorNome()` para lidar com aliases legados.
- Observer, service e table actions disparam historico/notificacao; alterar so um ponto pode quebrar a consistencia.
- Pedidos adicionais sao ocultos da fila principal por filtro/tab, mas devem aparecer no vinculo, relatorio e filtro proprio.
- Relatorios precisam carregar data de identificacao, problemas, adicionais, protocolo original, empresa e resultados por problema.

## Quando consultar

- Antes de mudar `Pedido`, `TipoStatus`, `Setor`, `TipoManutencao`, `FeedbackPedido` ou a UI de manutencao.
- Antes de alterar notificacoes, historico, relatorios ou escopo por role/setor.
