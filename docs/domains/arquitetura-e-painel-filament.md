# Arquitetura e Painel Filament

- Dominio: arquitetura do painel `admin`, navegacao e pontos de controle de acesso na UI
- Status: `draft`
- Fontes consultadas: `docs/context-skills/arquitetura-geral.md`, `docs/context-skills/autenticacao-e-autorizacao.md`, `app/Providers/Filament/AdminPanelProvider.php`, `app/Providers/AppServiceProvider.php`, `app/Models/User.php`, `app/Livewire/LoginPage.php`, `app/Filament/Admin/Pages/Dashboard.php`, `app/Console/Commands/CriarPermissoes.php`, `docs/cross-cutting/matriz-permissoes-e-escopo.md`

## Objetivo do dominio

Este dominio explica como o painel administrativo Filament esta configurado, quais sao os pontos de entrada do codigo e como o acesso a telas e acoes e controlado. Ele serve como guia para localizar rapidamente "onde muda a UI", "onde o menu aparece/some" e "qual permissao bloqueia um fluxo".

Este documento nao substitui os dominios de negocio (manutencao, merenda/estoque, pedagogico etc). Ele cobre a infraestrutura da interface e os controles comuns.

## Entidades e tabelas principais

- `User` (`users`) como identidade principal do painel (`guard=web`)
- Roles/permissoes (Spatie): `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
- `notifications` quando usado para efeitos de UX (avisos no acesso ao painel)

## Pontos de entrada do codigo

- Painel e hooks: `app/Providers/Filament/AdminPanelProvider.php`
- Registro de policies/gates: `app/Providers/AppServiceProvider.php`
- Gate final de acesso ao painel: `app/Models/User.php` (`canAccessPanel()` / `canAccessAdminPanel()`)
- Login custom: `app/Livewire/LoginPage.php`
- Pagina inicial: `app/Filament/Admin/Pages/Dashboard.php`
- Catalogo de permissoes/roles: `app/Console/Commands/CriarPermissoes.php` e `database/seeders/DatabaseSeeder.php`

## Fluxos principais

### 1) Login e acesso efetivo ao painel

1. Usuario autentica via Filament (login manual ou Google, conforme dominio de autenticacao).
2. Mesmo autenticado, o gate final `User::canAccessPanel()` pode negar o acesso (ex.: `email_approved = false`).
3. Quando negado, o sistema faz logout e redireciona para a pagina de login com notificacao.

### 2) Descoberta de telas e menu (Filament)

- O painel `admin` descobre resources/pages/clusters/widgets automaticamente a partir de `app/Filament/Admin/*`.
- A visibilidade no menu depende de uma combinacao de:
  - policy do model (quando resource)
  - `canAccess()` / `shouldRegisterNavigation` (quando definido na propria classe)
  - filtros adicionais na UI (ex.: bulk actions visiveis apenas para `Admin`)

### 3) Topbar de notificacoes

- O painel injeta um hook antes do menu do usuario para exibir notificacoes.
- Esse hook e exibido apenas para usuarios com permissao `Visualizar Notificacoes` (com tolerancia a variacoes de acentuacao observadas no codigo).

### 4) Dashboard e acoes globais

- A pagina `Dashboard` existe como entrada principal, mas `Dashboard::canAccess()` exige a permissao `Visualizar Tela de Inicio`.
- Acoes do header (ex.: "Instalar app mobile") dependem da permissao `Baixar App`.

## Permissoes, policies e filtros de escopo

### Gate do painel

- Ponto canonico: `app/Models/User.php`
- Acesso efetivo combina `email_approved`, permissao `Acessar Painel` e/ou role `Acessar Painel`.

### Policies (capacidade ampla)

- Policies sao registradas manualmente em `app/Providers/AppServiceProvider.php` via `Gate::policy(...)`.
- A policy responde a "pode listar/criar/editar/excluir", mas nao garante sozinha o escopo real (ver `docs/cross-cutting/matriz-permissoes-e-escopo.md`).

### Escopo contextual

- Alguns fluxos restringem por `id_escola`/`setor`/papel (ex.: `AlunoPolicy` tem escopo para professor e para escola).
- Quando existir service/query de escopo, ele deve ser documentado no dominio funcional correspondente.

## Efeitos colaterais

- Assets do painel: `AppServiceProvider` registra JS/CSS do Filament.
- Hooks de renderizacao: `AppServiceProvider` injeta um listener global no `BODY_END`.
- Notificacoes: a UX de acesso ao painel usa `Filament\Notifications\Notification`.

## Seeders, enums e vocabulario funcional dependente

- O catalogo de permissoes base e semeado em `permissoes:criar` (`CriarPermissoes`).
- `DatabaseSeeder` cria users padrao e roles (`Admin`, `SecretÃ¡rio`, `Administrativo`) e sincroniza permissao.
- O preset do `Secretario` vive em `app/Support/SecretarioPermissionPreset.php`.

## Pontos seguros para extensao

- Nova tela no painel: criar Resource/Page em `app/Filament/Admin` e garantir policy/permissao (ou `canAccess()` se for uma Page).
- Nova permissao: adicionar no catalogo em `CriarPermissoes` e manter sincronizacao de roles, seeders e docs.
- Nova restricao de escopo: combinar policy + filtro de query/service; nao depender apenas de esconder botao na UI.

## Riscos de regressao

- Catalogo de permissoes e roles depende de strings literais; variacoes de acentuacao podem causar inconsistencias.
- Policy pode dar "ok" mas a query/service pode restringir visibilidade; isso precisa estar alinhado e documentado.

## Testes existentes e lacunas

- Nao foram encontrados testes automatizados dedicados ao painel e ao gate final de acesso (login/aprovacao).
- Validacao manual minima recomendada:
  - login com usuario aprovado vs nao aprovado
  - acesso como `Admin` vs usuario restrito
  - verificacao de menu/acoes com e sem permissao (`Visualizar Tela de Inicio`, `Baixar App`, `Visualizar Notificacoes`)

