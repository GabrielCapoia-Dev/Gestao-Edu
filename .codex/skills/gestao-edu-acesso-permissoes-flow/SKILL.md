---
name: gestao-edu-acesso-permissoes-flow
description: Use para analisar ou evoluir login, Google OAuth, troca obrigatoria de senha, aprovacao de acesso, Spatie roles e permissions, policies, preview de perfil, menus Filament e escopos por escola, setor, pessoa ou professor no Gestao-Edu.
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
- troca obrigatoria de senha, sessao e preview de perfil sem escrita

## Sequencia recomendada

1. Ler [`docs/context-skills/autenticacao-e-autorizacao.md`](../../../docs/context-skills/autenticacao-e-autorizacao.md).
2. Ler [`docs/domains/autenticacao-autorizacao-e-permissoes.md`](../../../docs/domains/autenticacao-autorizacao-e-permissoes.md).
3. Confirmar o gate e a identidade do usuario:
   - `app/Models/User.php`
   - `app/Livewire/LoginPage.php`
   - `app/Http/Controllers/Auth/GoogleAuthController.php`
   - `app/Services/GoogleService.php`
   - `app/Services/DominioEmailService.php`
   - `app/Http/Middleware/EnsurePasswordIsChanged.php`
   - `app/Http/Middleware/ApplyProfilePreviewUser.php`
   - `app/Http/Middleware/BlockProfilePreviewWrites.php`
4. Revisar catalogo, presets e policies:
   - `app/Console/Commands/CriarPermissoes.php`
   - `database/seeders/DatabaseSeeder.php`
   - `app/Support/SecretarioPermissionPreset.php`
   - `app/Providers/AppServiceProvider.php`
   - `app/Policies`
5. Se a mudanca tocar visibilidade de dados, revisar tambem:
   - `app/Services/UserService.php`
   - `app/Services/UserSetorAccessService.php`
   - `app/Services/SetorPedidoAccessService.php`
   - `app/Services/PessoaScopeService.php`
   - `docs/cross-cutting/fluxos-e-permissoes-do-painel-admin.md`

## Checklist de analise

- Confirmar se o usuario precisa de role, permissao direta ou `email_approved`.
- Confirmar se a permissao existe em `permissoes:criar`.
- Confirmar se a policy esta registrada em `AppServiceProvider`.
- Confirmar se a UI apenas esconde a acao ou se a execucao tambem e bloqueada.
- Confirmar se o escopo final depende de `id_escola`, pivot `escola_user`, `setor_id` ou professor vinculado.
- Confirmar heranca de setores pela hierarquia e capacidades de `SetorAccessCapability`.
- No preview de perfil, preservar bloqueio de escrita no servidor; esconder botoes na UI nao e controle suficiente.
- Revisar nomes de permissoes com acentuacao, mojibake ou busca por fragmento.
- Separar permissao de leitura global da autorizacao de escrita: nunca amplie `canAccessEscola()` para liberar consultas de rede; crie/ use escopo de leitura e confirme policies de mutacao independentemente.
- Assessoria Pedagógica e RH podem consultar escola/dados de rede por vínculo funcional, sem receber `accessGlobalScope`; RH tem preset próprio sincronizado com o cargo funcional `rh`.
- Ao editar vínculos de Assessoria Pedagógica, RH com `Gerenciar Vínculos Estruturais de Pessoas` precisa receber todas as escolas ativas como opções cadastrais. Esse vínculo é informativo e não concede nem restringe acesso aos dados das escolas; mantenha a autorização estrutural separada do escopo de leitura.
- Restringir edicao de eventos/avisos/reservas próprios no backend por `criado_por_id`/proprietário, inclusive ações secundárias (publicar, desativar, público-alvo), não só esconder ações na interface.

## Saida esperada

Ao final da analise, registrar:

- permissoes, roles e policies afetadas
- ponto canonico da regra de acesso
- filtros de escopo aplicados
- riscos de vazamento ou bloqueio indevido
- testes ou validacao manual por perfil de usuario
