---
name: manutencao-cadastros-tipos
description: Use para analisar ou evoluir cadastros de manutencao no Gestao-Edu, incluindo TipoManutencao, opcoes, TipoStatus, Setor, empresas contratadas, seeders, permissoes, roles e resources Filament.
---

# Manutencao Cadastros, Tipos e Status

Use esta skill antes de alterar cadastros que sustentam manutencao: tipos, opcoes, status, setores, empresas contratadas, seeders, roles ou permissoes.

## Objetivo

Garantir que mudancas em cadastros nao quebrem:

- ciclo de vida de `Pedido`
- filtros e actions do painel
- relatorios e dashboards
- historico operacional
- permissoes Spatie e roles com setor
- escopo de empresas por setor

## Sequencia recomendada

1. Ler [`docs/context-skills/fluxo-pedidos-manutencao.md`](../../../docs/context-skills/fluxo-pedidos-manutencao.md).
2. Ler [`docs/context-skills/regras-criticas-pedidos.md`](../../../docs/context-skills/regras-criticas-pedidos.md).
3. Se envolver permissoes, roles ou setor, acionar `$gestao-edu-acesso-permissoes-flow`.
4. Confirmar models e services:
   - `app/Models/TipoManutencao.php`
   - `app/Models/TipoManutencaoOpcao.php`
   - `app/Models/TipoStatus.php`
   - `app/Models/Setor.php`
   - `app/Models/EmpresaContratada.php`
   - `app/Services/TipoManutencaoService.php`
   - `app/Services/TipoStatusService.php`
   - `app/Services/SetorService.php`
   - `app/Services/EmpresaContratadaService.php`
5. Revisar seeders e permissoes:
   - `database/seeders/TipoManutencaoSeeder.php`
   - `database/seeders/TipoStatusSeeder.php`
   - `database/seeders/SetorSeeder.php`
   - `app/Console/Commands/CriarPermissoes.php`
6. Revisar resources Filament:
   - `app/Filament/Admin/Resources/TipoManutencaos`
   - `app/Filament/Admin/Resources/Setors`
   - `app/Filament/Admin/Resources/EmpresaContratadas`
   - `app/Filament/Admin/Resources/Pedidos/Schemas`
   - `app/Filament/Admin/Resources/Pedidos/Tables`

## Checklist de analise

- Confirmar se status e setores seedados continuam coerentes com `PedidoService`.
- Confirmar se tipos e opcoes inativas somem de novos pedidos, mas continuam legiveis em historico e relatorio.
- Confirmar se `Em Andamento` permanece ativo ou inativo conforme a regra atual.
- Confirmar se empresas contratadas respeitam setor quando usadas em envio para empresa.
- Confirmar se role com `setor_id`, permissao global e escola podem se combinar no mesmo usuario.
- Evitar excluir registros operacionais; preferir inativar quando houver historico dependente.

## Saida esperada

Ao final da analise, registrar:

- cadastros e seeders afetados
- dependencias com status, setor, empresa e tipo de manutencao
- permissoes, roles e policies que precisam mudar
- impactos em pedidos antigos, filtros e relatorios
- testes focados e validacao manual por perfil
