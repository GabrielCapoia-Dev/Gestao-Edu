---
name: gestao-edu-acesso-permissoes-flow
description: Use para analisar ou evoluir login, Google OAuth, aprovacao de acesso, Spatie roles e permissions, policies, User::canAccessPanel, menus Filament, filtros por escola ou setor e catalogo permissoes:criar no Gestao-Edu.
---

# Gestao-Edu Acesso e Permissoes Flow

Use esta skill antes de mexer em autenticacao, autorizacao, usuarios, roles, permissoes, policies, dominios de email ou escopo de acesso.

## Objetivo

Mapear rapidamente o controle de acesso para evitar regressao em:

- login manual e login Google
- aprovacao de usuario e acesso ao painel
- catalogo Spatie de roles e permissoes
- policies e registro em provider
- menus e acoes Filament
- filtros por escola, setor e professor

## Sequencia recomendada

1. Ler [`docs/context-skills/autenticacao-e-autorizacao.md`](../../../docs/context-skills/autenticacao-e-autorizacao.md).
2. Ler [`docs/domains/autenticacao-autorizacao-e-permissoes.md`](../../../docs/domains/autenticacao-autorizacao-e-permissoes.md).
3. Confirmar o gate e a identidade do usuario:
   - `app/Models/User.php`
   - `app/Livewire/LoginPage.php`
   - `app/Http/Controllers/Auth/GoogleAuthController.php`
   - `app/Services/GoogleService.php`
   - `app/Services/DominioEmailService.php`
4. Revisar catalogo, presets e policies:
   - `app/Console/Commands/CriarPermissoes.php`
   - `database/seeders/DatabaseSeeder.php`
   - `app/Support/SecretarioPermissionPreset.php`
   - `app/Providers/AppServiceProvider.php`
   - `app/Policies`
5. Se a mudanca tocar visibilidade de dados, revisar tambem:
   - `app/Services/UserService.php`
   - `app/Services/UserSetorAccessService.php`
   - `docs/cross-cutting/fluxos-e-permissoes-do-painel-admin.md`

## Checklist de analise

- Confirmar se o usuario precisa de role, permissao direta ou `email_approved`.
- Confirmar se a permissao existe em `permissoes:criar`.
- Confirmar se a policy esta registrada em `AppServiceProvider`.
- Confirmar se a UI apenas esconde a acao ou se a execucao tambem e bloqueada.
- Confirmar se o escopo final depende de `id_escola`, pivot `escola_user`, `setor_id` ou professor vinculado.
- Revisar nomes de permissoes com acentuacao, mojibake ou busca por fragmento.

## Saida esperada

Ao final da analise, registrar:

- permissoes, roles e policies afetadas
- ponto canonico da regra de acesso
- filtros de escopo aplicados
- riscos de vazamento ou bloqueio indevido
- testes ou validacao manual por perfil de usuario
