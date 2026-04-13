# Autenticacao, Autorizacao e Permissoes

- Dominio: autenticacao, onboarding, aprovacao de acesso e autorizacao no painel administrativo
- Status: `pilot`
- Fontes consultadas: `docs/context-skills/autenticacao-e-autorizacao.md`, `app/Providers/Filament/AdminPanelProvider.php`, `app/Providers/AppServiceProvider.php`, `app/Livewire/LoginPage.php`, `app/Http/Controllers/Auth/GoogleAuthController.php`, `app/Services/GoogleService.php`, `app/Services/DominioEmailService.php`, `app/Models/User.php`, `app/Console/Commands/CriarPermissoes.php`, `database/seeders/DatabaseSeeder.php`

## Objetivo do dominio

Este dominio controla como o usuario entra no sistema, quando o acesso e efetivamente liberado e como a autorizacao e distribuida no painel. Ele cobre login manual, login Google, aprovacao de email, papeis, permissoes Spatie, policies e parte do escopo contextual por escola e setor.

O limite deste dominio termina na autorizacao e no enquadramento do usuario. Regras especificas de cada modulo continuam nos dominios funcionais, mas toda feature nova precisa passar por este dominio para evitar vazamento de acesso.

## Entidades e tabelas principais

- `User` e tabela `users`
- `Role`, `Permission` e tabelas do pacote Spatie (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`)
- `DominioEmail` e tabela `dominio_emails`
- notificacoes em `notifications`, quando usadas como efeito do acesso ao painel

## Pontos de entrada do codigo

- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Providers/AppServiceProvider.php`
- `app/Livewire/LoginPage.php`
- `app/Http/Controllers/Auth/GoogleAuthController.php`
- `app/Services/GoogleService.php`
- `app/Services/DominioEmailService.php`
- `app/Models/User.php`
- `app/Policies`
- `app/Filament/Admin/Resources/Users`
- `app/Filament/Admin/Resources/Roles`
- `app/Filament/Admin/Resources/Permissions`
- `app/Console/Commands/CriarPermissoes.php`
- `database/seeders/DatabaseSeeder.php`

## Fluxos principais

### Login manual

- o painel `admin` usa o guard `web`
- a pagina de login customizada e `LoginPage`
- autenticar com email e senha nao basta por si so; o acesso final ainda depende de `User::canAccessPanel()`

### Login Google e onboarding

- `GoogleAuthController::redirect()` inicia OAuth com escopos de perfil, email, Drive metadata e leitura de Sheets
- `GoogleService::registrarOuLogar()` encontra o usuario por email ou `google_email`
- se o usuario nao existir, `GoogleService::registroGoogle()` valida o dominio com `DominioEmailService`
- se o dominio estiver autorizado, o usuario e criado com `email_approved = false`
- depois da autenticacao, `User::canAccessPanel()` barra o painel ate aprovacao e redireciona para o login com notificacao

### Seed inicial de acesso

- `DatabaseSeeder` cria roles principais, chama `permissoes:criar`, sincroniza users padrao e semeia dominios de email
- `CriarPermissoes` concentra boa parte do catalogo de permissoes base do sistema

### Autorizacao em runtime

- `AppServiceProvider` registra policies manualmente
- o painel e as rotas usam combinacao de permissao Spatie, policy e filtros contextuais por `id_escola` ou `setor`
- alguns elementos de UI, como notificacoes da topbar, ainda verificam nomes de permissao alternativos para tolerar inconsistencias de acentuacao

## Permissoes, policies e filtros de escopo

### Permissoes Spatie

- controle fino por verbos como `Listar`, `Criar`, `Editar`, `Excluir`, `Exportar` e `Visualizar`
- o catalogo principal esta em `CriarPermissoes`, com sincronizacao adicional em `DatabaseSeeder`
- o papel `Admin` recebe todas as permissoes seedadas; `Secretario` usa preset dedicado; `Administrativo` recebe subconjunto manual

### Policies

- policies sao registradas explicitamente em `AppServiceProvider`
- exemplos: `UserPolicy`, `RolePolicy`, `PermissionPolicy`, `PedidoPolicy`, `PedidoMerendaPolicy`
- policy costuma responder a capacidade ampla do recurso, nao ao filtro contextual completo

### Filtros de escopo

- `id_escola` restringe visibilidade em varios modulos, principalmente consultas de pedidos e inventario
- `setor_id` tambem participa do acesso operacional em fluxos como manutencao
- varias telas usam services e queries para refinar visibilidade alem da policy

### Middleware e guardas de rota

- rotas especificas usam `can:` em downloads sensiveis, como arquivos de pedido
- o acesso ao painel depende de `Authenticate` do Filament e do gate final em `canAccessPanel`

## Regras de negocio criticas

- usuario autenticado, mas sem `email_approved`, continua sem acesso ao painel
- dominio de email autorizado e requisito para cadastro automatico via Google
- policy sozinha nao define a visibilidade final de varios modulos; filtros por escola e setor tambem sao obrigatorios
- alterar nomes seedados de permissao pode quebrar UI, policies e leitura do painel

## Efeitos colaterais

- Historico: nao ha historico dedicado de autorizacao; a rastreabilidade principal esta em dados de usuario, roles e permissoes
- Notificacao: acesso pendente e acesso liberado geram notificacoes visuais no fluxo de autenticacao
- Exportacao: nao possui exportacao propria
- Arquivos: nao possui arquivos proprios
- Scheduler: nao possui scheduler proprio

## Seeders, enums e vocabulario funcional dependente

- roles seedadas: `Admin`, `Secretario` e `Administrativo`
- permissao depende de strings literais, algumas com variantes de acentuacao no codigo legado
- dominios autorizados sao seedados em `DatabaseSeeder`
- o preset do `Secretario` vive em `App\Support\SecretarioPermissionPreset`

## Pontos seguros para extensao

- nova capacidade de acesso: adicionar permissao em `CriarPermissoes`, refletir sincronizacao de roles e atualizar a documentacao transversal
- nova restricao contextual: combinar policy com filtro de query ou service, nao empurrar tudo para a policy
- novo fluxo de login ou identidade externa: concentrar em service/controller e manter `canAccessPanel()` como gate final do painel

## Riscos de regressao

- catalogo de permissoes disperso entre comando, seeders e verificacoes manuais
- mistura de policy ampla com filtros contextuais pode dar falsa sensacao de seguranca
- alteracoes em login Google afetam onboarding, vinculacao de conta e persistencia de token
- variacoes de acentuacao em nomes de permissao podem esconder bugs de acesso

## Testes existentes e lacunas

- nao foram encontrados testes dedicados para login, aprovacao de email, roles, policies ou matriz de permissao
- validacao manual minima recomendada:
  - login manual com usuario aprovado e nao aprovado
  - login Google com dominio permitido e nao permitido
  - acesso como `Admin`
  - acesso como usuario restrito por escola ou setor
  - rota protegida por policy com `can:`
