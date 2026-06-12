# Autenticacao e Autorizacao

## Objetivo

Explicar como usuarios entram no sistema, como o acesso e aprovado e como a autorizacao e distribuida.

## Onde isso vive no codigo

- `app/Providers/Filament/AdminPanelProvider.php`
- `config/auth.php`
- `app/Livewire/LoginPage.php`
- `app/Http/Controllers/Auth/GoogleAuthController.php`
- `app/Services/GoogleService.php`
- `app/Models/User.php`
- `app/Policies`
- `database/seeders/DatabaseSeeder.php`
- `app/Console/Commands/CriarPermissoes.php`

## Comportamento e regras principais

- O painel principal usa o guard `web` e autentica via sessao.
- O login manual e customizado no Filament com `LoginPage`.
- O login Google usa Socialite e pede apenas os escopos de identidade `openid`, `email` e `profile`.
- O OAuth nao solicita acesso offline, nao agrega escopos concedidos anteriormente e nao persiste access token ou refresh token.
- Uma migracao de dados limpa credenciais antigas que tenham sido armazenadas antes da remocao das integracoes com APIs Google.
- Se o usuario ainda nao existe, `GoogleService` tenta registrar novo usuario.
- O registro via Google depende de dominio autorizado em `DominioEmailService`.
- Mesmo apos autenticacao, `User::canAccessPanel()` exige `email_approved = true`; caso contrario, faz logout e redireciona para login com notificacao.
- Roles e permissoes usam Spatie Permission.
- Parte da autorizacao e global por permissao; parte e contextual, por exemplo pedidos filtrados por escola.

## Regras de negocio

- Usuario novo por Google pode existir sem acesso efetivo ate ser aprovado.
- Usuarios vinculados a escola tendem a ver apenas dados da propria escola.
- Alguns recursos exigem permissao explicita para listar, criar, editar, exportar ou visualizar historico.

## Regras tecnicas

- Policies sao registradas manualmente em `AppServiceProvider`.
- O comando `permissoes:criar` e o `DatabaseSeeder` definem boa parte do catalogo de permissoes.
- O painel injeta notificacoes no topo apenas para usuarios com permissao `Visualizar Notificações`.

## Riscos e cuidados

- O catalogo de permissoes esta espalhado entre `DatabaseSeeder` e `CriarPermissoes`.
- Existe mistura de nomes de role com acentuacao e sem acentuacao em seeders.
- Mudancas em login Google podem afetar onboarding, identidade do usuario e aprovacao de acesso ao painel.

## Quando consultar

- Bug de login
- Ajuste de papel/permissao
- Restricao por escola
- Mudanca em policies ou aprovacao de usuario
