# Cadastros Administrativos Base

- Dominio: cadastros de administracao do painel (usuarios, niveis de acesso, permissoes e dominios permitidos)
- Status: `draft`
- Fontes consultadas: `docs/context-skills/mapa-de-modulos.md`, `docs/context-skills/autenticacao-e-autorizacao.md`, `app/Filament/Admin/Resources/Users/UserResource.php`, `app/Filament/Admin/Resources/Roles/RoleResource.php`, `app/Filament/Admin/Resources/DominioEmails/DominioEmailResource.php`, `app/Policies/UserPolicy.php`, `app/Policies/RolePolicy.php`, `app/Policies/PermissionPolicy.php`, `app/Policies/DominioEmailPolicy.php`, `app/Providers/AppServiceProvider.php`, `app/Console/Commands/CriarPermissoes.php`, `database/seeders/DatabaseSeeder.php`, `app/Support/SecretarioPermissionPreset.php`, `docs/cross-cutting/fluxos-e-permissoes-do-painel-admin.md`

## Objetivo do dominio

Este dominio cobre os cadastros que governam o acesso ao sistema e ao painel: usuarios, niveis de acesso (roles), permissoes (Spatie) e dominios permitidos para onboarding por Google. Ele existe para que qualquer alteracao de permissao ou fluxo de onboarding seja feita com previsibilidade e sem "vazamento" de acesso.

O limite do dominio termina no cadastro e manutencao desses itens. A aplicacao de permissao em telas de negocio (pedidos, estoque, pedagogico etc) continua nos dominios funcionais.

## Entidades e tabelas principais

- `User` (`users`)
- `Role`, `Permission` e tabelas do Spatie (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`)
- `DominioEmail` (`dominio_emails`)

## Pontos de entrada do codigo

- Filament:
  - `app/Filament/Admin/Resources/Users/UserResource.php`
  - `app/Filament/Admin/Resources/Roles/RoleResource.php`
  - `app/Filament/Admin/Resources/DominioEmails/DominioEmailResource.php`
- Policies (capacidade ampla):
  - `app/Policies/UserPolicy.php`
  - `app/Policies/RolePolicy.php`
  - `app/Policies/PermissionPolicy.php`
  - `app/Policies/DominioEmailPolicy.php`
  - registro: `app/Providers/AppServiceProvider.php`
- Catalogo e presets:
  - `app/Console/Commands/CriarPermissoes.php`
  - `database/seeders/DatabaseSeeder.php`
  - `app/Support/SecretarioPermissionPreset.php`

## Fluxos principais

### 1) Gestao de usuarios

- listar usuarios (com filtros/escopo definidos no `UserService` quando aplicavel)
- criar usuario manualmente
- editar usuario (inclui atribuicao de escola e perfil, quando habilitado na UI)
- excluir usuario

### 2) Gestao de niveis de acesso (roles)

- criar/editar um nivel de acesso
- selecionar permissao por grupos e salvar
- (restricao operacional) algumas roles podem ser bloqueadas para edicao/exclusao via `RoleService`/UI

### 3) Gestao de permissoes (catalogo)

- o catalogo base e semeado/atualizado por `permissoes:criar`
- o painel trabalha em cima dessas strings literais

Observacao:

- Existe `PermissionPolicy`, mas nao foi encontrado `PermissionResource` no Filament nesta base. Validar se a gestao de permissoes ocorre apenas via roles.

### 4) Dominios permitidos (onboarding via Google)

- criar/ativar dominio permitido
- o onboarding via Google valida dominio antes de criar usuario

## Permissoes, policies e filtros de escopo

### Usuarios

- Policy: `UserPolicy`
- Pode:
  - Listar/Ver: `Listar UsuÃ¡rios`
  - Criar: `Criar UsuÃ¡rios`
  - Editar: `Editar UsuÃ¡rios`
  - Excluir: `Excluir UsuÃ¡rios`

### Niveis de acesso (roles)

- Policy: `RolePolicy`
- Pode:
  - Listar/Ver: `Listar NÃ­veis de Acesso`
  - Criar: `Criar NÃ­veis de Acesso`
  - Editar: `Editar NÃ­veis de Acesso`
  - Excluir: `Excluir NÃ­veis de Acesso`

### Permissoes de execucao (catalogo)

- Policy: `PermissionPolicy`
- Pode:
  - Listar/Ver: `Listar PermissÃµes de ExecuÃ§Ã£o`
  - Criar: `Criar PermissÃµes de ExecuÃ§Ã£o`
  - Editar: `Editar PermissÃµes de ExecuÃ§Ã£o`
  - Excluir: `Excluir PermissÃµes de ExecuÃ§Ã£o`

### Dominios permitidos

- Policy: `DominioEmailPolicy`
- Pode:
  - Listar/Ver: `Listar Dominios de Email`
  - Criar: `Criar Dominios de Email`
  - Editar: `Editar Dominios de Email`
  - Excluir: `Excluir Dominios de Email`
- Observacao (UI): bulk delete visivel apenas para role `Admin`.

## Seeders, enums e vocabulario funcional dependente

- `permissoes:criar` cria o catalogo base e sincroniza presets de roles.
- `DatabaseSeeder` cria users padrao:
  - `admin@admin.com` (role `Admin`)
  - `secretario@secretario.com` (role `SecretÃ¡rio`)
  - `administrativo@administrativo.com` (role `Administrativo`)
- `SecretarioPermissionPreset` concentra um conjunto de permissoes padrao para esse perfil.

## Pontos seguros para extensao

- Nova permissao: adicionar em `CriarPermissoes`, manter sincronizacao de roles e atualizar docs transversais.
- Nova regra de acesso ao painel: manter `User::canAccessPanel()` como gate final.
- Nova restricao operacional em roles: concentrar em `RoleService` e deixar claro na doc.

## Riscos de regressao

- Strings literais de permissao com variacoes de acentuacao podem quebrar acesso a telas.
- Misturar "esconder botao" com "permitir executar" sem policy/gate consistente pode abrir brecha de acesso.

## Testes existentes e lacunas

- Nao foram encontrados testes automatizados dedicados ao CRUD de usuarios/roles/dominios.
- Validacao manual minima recomendada:
  - acesso como `Admin`
  - acesso como `SecretÃ¡rio` (preset) e confirmar menu/acoes habilitados
  - tentativa de acesso sem permissoes (UI e policy devem negar)

