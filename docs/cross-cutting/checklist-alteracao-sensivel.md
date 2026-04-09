# Checklist de Alteracao Sensivel

Use este roteiro imediatamente antes de alterar regra central.

## Checklist

- identifique se a regra atual vive em model, service, observer, policy ou action do Filament
- revise se o comportamento depende de nome seedado de status, setor, role ou permissao
- confirme se existe filtro por escola, perfil ou setor alem da policy
- verifique se a mudanca atualiza tambem historico, notificacao, anexo, exportacao ou data derivada
- revise seeders, enums e massa de dados que assumem o comportamento atual
- monte um roteiro manual minimo antes de editar

## Validacao extra por fluxo

### Pedido

- criacao
- troca de status
- historico
- notificacoes
- anexos
- avaliacao e reabertura

### PedidoMerenda e estoque

- reserva
- entrega parcial
- entrega total
- cancelamento
- saldo do contrato
- reflexo em estoque

## Fonte semente

Este documento consolida o roteiro ja existente em [`docs/context-skills/checklist-alterar-logica-sensivel.md`](../context-skills/checklist-alterar-logica-sensivel.md).
