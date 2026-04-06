# Checklist para Alterar Logica Sensivel

## Objetivo

Servir como roteiro curto antes de mexer em fluxos que geram efeitos colaterais ou dependem de dados seedados.

## Onde isso costuma impactar

- `Pedido`
- `PedidoMerenda`
- permissao/policy
- seeders de status, setores e permissoes
- upload/download protegido

## Checklist

- Identifique se a regra esta em model, service, observer, policy ou action do Filament.
- Revise se o comportamento depende de nome seedado de status, setor ou permissao.
- Verifique se ha historico, notificacao, anexo ou data derivada sendo atualizada junto.
- Confirme se existe filtro por escola ou perfil alem da policy.
- Revise seeders e massa de dados que assumem o comportamento atual.
- Monte um roteiro manual minimo de validacao antes de editar.
- Se tocar em `Pedido`, valide:
  - criacao
  - troca de status
  - historico
  - notificacoes
  - anexos
  - avaliacao e reabertura
- Se tocar em `PedidoMerenda`, valide:
  - reserva
  - entrega parcial
  - entrega total
  - cancelamento
  - saldo do contrato
  - reflexo em estoque

## Quando consultar

- Imediatamente antes de alterar regra central
- Em PRs com risco operacional
