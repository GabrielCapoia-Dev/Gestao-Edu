---
name: manutencao-cadastros-tipos
description: Use quando for preciso analisar ou evoluir cadastros de manutencao deste projeto, incluindo TipoManutencao, opcoes, TipoStatus, Setor, empresas contratadas, seeders, permissoes e resources Filament.
---

# Manutencao: Cadastros, Tipos e Status

Use esta skill antes de alterar cadastros que sustentam o fluxo de manutencao: tipos, opcoes, status, setores, empresas contratadas, seeders, roles ou permissoes.

## Objetivo

Garantir que mudancas em cadastros nao quebrem o ciclo de vida de `Pedido`, filtros do painel, relatorios, permissoes ou dados historicos.

## Fluxo recomendado

1. Leia primeiro:
   - `docs/context-skills/fluxo-pedidos-manutencao.md`
   - `docs/context-skills/regras-criticas-pedidos.md`
   - `docs/context-skills/autenticacao-e-autorizacao.md`, se envolver permissoes, roles ou setor
2. Confirme no codigo os pontos centrais:
   - `app/Models/TipoManutencao.php`
   - `app/Models/TipoManutencaoOpcao.php`
   - `app/Models/TipoStatus.php`
   - `app/Models/Setor.php`
   - `app/Models/EmpresaContratada.php`
   - `app/Services/TipoManutencaoService.php`
   - `app/Services/TipoStatusService.php`
   - `app/Services/SetorService.php`
   - `database/seeders/TipoManutencaoSeeder.php`
   - `database/seeders/TipoStatusSeeder.php`
   - `database/seeders/SetorSeeder.php`
3. Revise tambem os resources Filament relacionados:
   - `app/Filament/Admin/Resources/TipoManutencaos`
   - `app/Filament/Admin/Resources/EmpresaContratadas`
   - `app/Filament/Admin/Resources/Pedidos/Schemas`
   - `app/Filament/Admin/Resources/Pedidos/Tables`

## Regras sensiveis

- Status e setores seedados sao dependencias do fluxo; renomear exige revisar service, observer, filtros, relatorios e testes.
- `Em Andamento` deve permanecer inativo quando a regra atual exigir compatibilidade historica.
- Tipos e opcoes inativas devem sumir de novas selecoes, mas permanecer legiveis em pedidos antigos.
- Empresas contratadas devem respeitar setor quando usadas no fluxo de envio para empresa.
- Permissoes Spatie, roles com `setor_id` e escopo por escola podem se combinar no mesmo usuario.

## Estrategia de implementacao

- Prefira service dedicado para regras de cadastro, historico e inativacao.
- Use seeders idempotentes para status, setores, tipos padrao e permissoes novas.
- Evite apagar registros operacionais; prefira inativar quando historico ou relatorio depender do cadastro.
- Ao criar permissao nova, revisar policies, resources, actions, presets e testes de acesso.

## Validacao minima

- Cobrir que tipos/opcoes inativas nao aparecem em novos pedidos.
- Cobrir que status criticos existem e continuam ativos/inativos conforme a regra.
- Cobrir escopo de empresas por setor quando a mudanca tocar envio para empresa.
- Rodar testes de manutencao e, se houver permissao nova, testes de acesso relacionados.
