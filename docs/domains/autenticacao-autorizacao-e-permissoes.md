# Autenticacao, Autorizacao e Permissoes

## Metadados

- Dominio: autenticacao, onboarding, aprovacao de acesso, sessao e autorizacao do painel
- Status: `validated`
- Ultima revisao: 2026-07-17
- Responsavel: revisao assistida por codigo
- Fontes consultadas: `app/Http/Controllers/Auth/AdminLoginController.php`, `app/Http/Controllers/Auth/GoogleAuthController.php`, `app/Http/Controllers/Auth/ForcePasswordChangeController.php`, `app/Services/GoogleService.php`, `app/Services/DominioEmailService.php`, `app/Models/User.php`, `app/Providers/Filament/AdminPanelProvider.php`, `app/Providers/AppServiceProvider.php`, `app/Policies/UserPolicy.php`, `app/Models/Enums/ListaPermissoes.php`, `app/Console/Commands/CriarPermissoes.php`, `database/seeders/DatabaseSeeder.php`, `routes/web.php`

## Objetivo do dominio

Este dominio controla como uma identidade entra no sistema, quando o acesso ao painel e liberado e como as capacidades sao avaliadas em runtime. Ele cobre login manual, Google OAuth, aprovacao de email, troca obrigatoria de senha, roles, permissoes, policies, profile preview e os guardas comuns de sessao.

O limite deste dominio termina na decisao de identidade e capacidade. O filtro concreto de registros por escola, setor, turma ou inventario pertence aos dominios funcionais, embora utilize services transversais de escopo.

## Entidades e tabelas principais

- `User` (`users`)
- `Role` e `Permission`
- tabelas Spatie: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
- `DominioEmail` (`dominio_emails`)
- pivot `escola_user`, mantido como compatibilidade de vinculo escolar
- `notifications`, usada para avisos do fluxo de acesso

Campos de `users` relevantes:

- `email`, `password`, `remember_token`
- `email_approved`, `email_verified_at`
- `must_change_password`
- `google_id`, `google_email`, `avatar_url`
- `id_escola` e `setor_id` como compatibilidade/atalho operacional
- `last_login_at` e `last_seen_at`

Tokens Google antigos continuam ocultos no model, mas o fluxo atual de login nao depende deles e existe migracao para limpar tokens legados.

## Pontos de entrada do codigo

### Login manual

- `app/Http/Controllers/Auth/AdminLoginController.php`
- view `resources/views/auth/admin-login.blade.php`
- rota `POST /admin/login`

### Google OAuth

- `app/Http/Controllers/Auth/GoogleAuthController.php`
- `app/Services/GoogleService.php`
- `app/Services/DominioEmailService.php`
- rotas `/oauth/redirect/google` e `/oauth/callback/google`

### Gate e sessao

- `app/Models/User.php`
- `app/Http/Middleware/EnsurePasswordIsChanged.php`
- `app/Http/Middleware/EnforceAbsoluteSessionLifetime.php`
- `app/Http/Middleware/NormalizeSessionCookieDomain.php`
- `app/Http/Middleware/ApplyProfilePreviewUser.php`
- `app/Http/Middleware/BlockProfilePreviewWrites.php`

### Autorizacao

- `app/Providers/AppServiceProvider.php`
- `app/Policies/*`
- `app/Models/Enums/ListaPermissoes.php`
- `app/Console/Commands/CriarPermissoes.php`
- `database/seeders/DatabaseSeeder.php`
- presets em `app/Support/*PermissionPreset.php`

## Fluxos principais

### 1. Login manual

1. O controller valida email, senha e opcao de lembrar.
2. A chave de rate limiting combina email normalizado e IP.
3. Sao permitidas ate oito tentativas antes do bloqueio temporario.
4. `attemptWhen()` autentica apenas se o usuario tambem puder acessar o painel.
5. A sessao e regenerada apos sucesso.
6. Se `must_change_password` estiver ativo, o usuario vai para a tela obrigatoria de alteracao.
7. Caso contrario, segue para a URL pretendida ou para o painel.

O login manual atual nao usa `app/Livewire/LoginPage.php` como ponto canonico.

### 2. Google OAuth

O redirect solicita somente:

- `openid`
- `email`
- `profile`

Tambem usa `prompt=select_account`. Nao solicita Drive, Sheets ou acesso offline.

No callback:

1. Socialite obtem a identidade Google.
2. `GoogleService::registrarOuLogar()` localiza ou cria o usuario.
3. Cadastro automatico depende de dominio permitido.
4. Usuario novo nasce sem aprovacao administrativa.
5. `User::canAccessAdminPanel()` bloqueia o acesso ate a liberacao.
6. O login persistente e realizado somente depois do gate final.
7. O redirect informado e sanitizado para evitar open redirect.

### 3. Aprovacao de acesso

`User::canAccessAdminPanel()` permite acesso quando pelo menos uma condicao e verdadeira:

- `email_approved = true`
- permissao de acessar o painel
- role legada de acessar o painel

Quando `email_approved` muda para verdadeiro, o model preenche `email_verified_at` caso ainda esteja nulo.

### 4. Troca obrigatoria de senha

`must_change_password` e verificado:

- depois do login manual
- depois do login Google
- pelo middleware persistente do painel

A redefinicao administrativa de senha deve manter esse fluxo coerente para que a nova senha temporaria seja substituida pelo titular.

### 5. Profile preview

Usuarios autorizados podem navegar como outro usuario. O fluxo:

- preserva o usuario controlador real
- substitui o usuario efetivo para leitura de menus, policies e escopos
- bloqueia requisicoes de escrita
- exibe estado visual no painel
- permite restaurar o acesso original

Nao deve ser usado como impersonacao com capacidade de alteracao.

### 6. Presenca e sessao

- evento de login atualiza a presenca do usuario
- heartbeat do painel atualiza `last_seen_at`
- middleware de sessao absoluta impede sessoes indefinidas
- dominio do cookie e normalizado para evitar inconsistencias entre hosts configurados

## Permissoes e roles

### Catalogo

`ListaPermissoes` e o catalogo tipado preferencial para codigo novo. `CriarPermissoes` sincroniza as permissoes no banco e os presets mantem conjuntos padrao por perfil.

Ainda existem verificacoes legadas por strings literais, inclusive variantes com e sem acentuacao. Elas devem ser removidas gradualmente, sem renomear permissoes em producao sem migracao de dados.

### Roles

Roles representam niveis de acesso e podem possuir `setor_id` como fallback legado. A role `Admin` recebe capacidade global. Outros perfis dependem de presets e permissoes sincronizadas.

Cargo funcional e role nao sao a mesma coisa:

- cargo/vinculo vive em Pessoa e `servidor_funcao_administrativa`
- role/permissao vive em User e Spatie
- services de Pessoas podem sincronizar roles conforme o cargo, mas as duas estruturas possuem responsabilidades distintas

## Policies e filtros de escopo

Policies sao registradas explicitamente em `AppServiceProvider`. Elas devem responder a capacidade da acao.

O acesso aos registros ainda depende de services como:

- `PessoaScopeService`
- `UserSetorAccessService`
- `SetorHierarchyService`
- services especificos do dominio

Exemplo: `UserPolicy::view()` exige a permissao de listar usuarios e tambem verifica se o usuario alvo pertence a uma pessoa ou setor acessivel.

### Regras especiais de `UserPolicy`

- nao permite aplicar permissoes no proprio usuario nem em Admin
- aprovacao de email pela tabela e restrita a Admin
- redefinicao de senha nao pode atingir o proprio usuario, Admin ou o usuario protegido de id 1
- visualizacao de usuarios online e profile preview possuem permissoes proprias

## Middleware e rotas protegidas

O painel usa `Authenticate` do Filament e middlewares persistentes adicionais. Downloads sensiveis usam `can:` ou autorizacao explicita no controller.

Rotas administrativas fora do resource devem repetir os mesmos guardas necessarios. Estar sob o prefixo `/admin` nao substitui policy.

## Regras de negocio criticas

- autenticar credenciais nao significa possuir acesso ao painel
- cadastro por dominio permitido nao significa aprovacao automatica
- troca obrigatoria de senha deve ser aplicada nos dois tipos de login
- profile preview nunca pode permitir escrita
- Admin nao deve ser alterado por usuarios de menor privilegio
- policy e escopo precisam ser avaliados em conjunto
- cargo funcional e role nao devem ser confundidos
- nomes de permissoes existentes sao dados de producao, nao apenas labels

## Efeitos colaterais

- Historico: nao existe trilha de auditoria completa de mudancas de role/permissao; a rastreabilidade principal esta nas tabelas Spatie e timestamps.
- Notificacao: login pendente, login permitido e erros de autenticacao geram avisos visuais.
- Exportacao: sem exportacao propria.
- Arquivos: avatar pode ser URL externa, data URI, caminho absoluto ou arquivo no disk publico.
- Scheduler: nao possui comando exclusivo, mas presenca, sessao e notificacoes dependem da infraestrutura transversal.

## Seeders e vocabulario dependente

- roles e usuarios iniciais sao criados por `DatabaseSeeder`
- catalogo e sincronizado por `permissoes:criar`
- permissoes novas devem entrar primeiro em `ListaPermissoes` e no fluxo de sincronizacao
- dominios permitidos precisam estar ativos em `dominio_emails`
- presets de cargos e perfis precisam ser revisados quando uma permissao muda de significado

## Pontos seguros para extensao

- novo login externo: controller fino + service de identidade, mantendo `canAccessAdminPanel()` como gate final
- nova permissao: enum, sincronizacao, presets, policy, UI e testes
- nova restricao contextual: policy + query escopada + validacao backend
- nova action administrativa sobre User: implementar metodo de policy especifico, evitando apenas `visible()` na UI
- nova informacao de identidade funcional: preferir Pessoa; User deve continuar como identidade autenticavel

## Riscos de regressao

- reintroduzir `LoginPage` como fluxo paralelo ao controller atual
- liberar usuario Google antes de `email_approved`
- aceitar redirect externo no OAuth
- tratar `id_escola` ou `setor_id` do User como unica fonte de escopo, ignorando vinculos funcionais
- criar permissao apenas no enum sem sincronizar o banco
- alterar label/acentuacao sem migrar permissoes existentes
- esconder action, mas deixar endpoint executavel
- profile preview executar POST, PUT, PATCH ou DELETE

## Testes existentes e lacunas

Existem testes de policies e escopos em dominios funcionais, inclusive Pessoas, Turmas e Inventario. Ainda falta uma suite dedicada que cubra integralmente:

- login manual aprovado, pendente e com senha incorreta
- rate limiting
- Google OAuth com dominio permitido e bloqueado
- troca obrigatoria de senha nos dois logins
- profile preview e bloqueio de escrita
- expiracao absoluta de sessao
- sincronizacao completa do catalogo e presets

Validacao manual minima:

- Admin, usuario global, equipe gestora e usuario sem escopo
- login por senha e Google
- criacao de usuario pendente e aprovacao posterior
- redefinicao de senha temporaria
- tentativa de acessar e alterar usuario fora do escopo

## Divergencias corrigidas nesta revisao

- login manual atualizado de `LoginPage` para `AdminLoginController`
- documentados rate limiting, regeneracao de sessao e troca obrigatoria de senha
- documentado profile preview somente leitura
- separado cargo funcional de role Spatie
- atualizado o escopo de User para considerar Pessoa e setor
- removida a afirmacao de que nao havia qualquer teste relacionado a policies; a lacuna agora esta limitada ao fluxo completo de autenticacao
