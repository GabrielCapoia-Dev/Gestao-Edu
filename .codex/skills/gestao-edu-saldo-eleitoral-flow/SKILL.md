---
name: gestao-edu-saldo-eleitoral-flow
description: Use para analisar ou evoluir saldo eleitoral de servidores no Gestao-Edu, incluindo solicitações da equipe gestora, aprovação do RH, saldo disponível, descontos, histórico e relatórios.
---

# Gestao-Edu Saldo Eleitoral

## Objetivo

Manter o saldo de cada servidor auditável, impedir uso acima do disponível e separar solicitação da decisão de RH.

## Pontos canônicos

- `app/Models/SaldoEleitoral.php` e `app/Services/SaldoEleitoralService.php`
- `app/Filament/Admin/Resources/Servidores/ServidorResource.php` e a tabela Livewire `app/Livewire/Pessoas/ServidoresTable.php`
- `app/Filament/Admin/Resources/SaldosEleitorais`
- `app/Models/Enums/ListaPermissoes.php`, `EquipeGestoraPermissionPreset` e `RhPermissionPreset`
- `tests/Feature/Pessoas/SaldoEleitoralServiceTest.php`

## Regras críticas

- Registros são append-only: adições, usos aprovados, recusas, descontos manuais e estornos formam o histórico; não editar nem apagar lançamentos decididos.
- O saldo líquido considera somente adições e estornos aprovados como créditos e usos aprovados como débitos. Para novos pedidos, usos pendentes também reservam dias até aprovação ou recusa.
- Estorno é um novo movimento pendente vinculado a um uso aprovado original; exigir justificativa e aprovação do RH. O pedido não altera o saldo antes da aprovação, e o lançamento de uso original nunca é reescrito. Somar estornos pendentes/aprovados para limitar o total ao uso original; quando ele tiver datas, permitir estornar somente um subconjunto ainda não reservado dessas datas. Usos legados sem datas podem ser estornados por quantidade, sempre dentro do limite original.
- Estorno aprovado devolve dias ao saldo líquido; estorno rejeitado não altera saldo. Solicitações de estorno devem aparecer junto aos usos na aba de uso do RH, com badges de pendência e justificativa preservada.
- Criar pedidos e descontos sob lock transacional do servidor. Validar o saldo novamente no servidor no momento da aprovação e do desconto manual; saldo nunca pode ficar negativo.
- Uso de saldo registra as datas efetivamente solicitadas em `saldos_eleitorais.datas`; a quantidade deve coincidir com `dias`. O calendário bloqueia fins de semana e feriados nacionais, estaduais aplicáveis no Paraná e municipais de Umuarama. Repetir a validação no servidor, impedir sobreposição com usos pendentes/aprovados e guardar as datas no histórico/relatório.
- Manter as regras de feriados do calendário e da validação centralizadas em `SaldoEleitoralCalendarService`; não tratar ponto facultativo como feriado. Ao alterar o escopo legal/local, revisar testes e as duas representações (interface e backend).
- Equipe gestora pode solicitar adição ou uso; RH decide os pedidos e pode registrar desconto manual. A UI e cada action devem validar a permissão do lado servidor.
- Mudanças no preset RH/equipe gestora precisam atualizar o catálogo/presets sem conceder autorização a cargos não relacionados.
- O filtro da listagem significa saldo líquido positivo, não existência de qualquer histórico ou pedido pendente.

## Validação focada

Cobrir saldo aprovado, reserva de usos pendentes, bloqueio de pedido acima do disponível, aprovação e desconto até zero; para uso, verificar também correspondência entre datas e quantidade, fins de semana, feriados e sobreposição de datas. Validar o painel e permissões no Hub de Testes, de acordo com as instruções do projeto; não executar testes localmente.
