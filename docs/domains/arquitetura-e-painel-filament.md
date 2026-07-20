# Arquitetura e Painel Filament

## Metadados

- Dominio: arquitetura do monolito Laravel, painel `admin`, navegacao e pontos transversais de execucao
- Status: `validated`
- Ultima revisao: 2026-07-17
- Responsavel: revisao assistida por codigo
- Fontes consultadas: `composer.json`, `package.json`, `bootstrap/app.php`, `routes/web.php`, `routes/api.php`, `app/Providers/Filament/AdminPanelProvider.php`, `app/Providers/AppServiceProvider.php`, `app/Models/User.php`, `app/Http/Controllers/Auth/AdminLoginController.php`, `docker-compose.yml`, `phpunit.xml`, `docs/context-skills/arquitetura-geral.md`

## Objetivo do dominio

Este dominio descreve como o Gestao Edu e executado, como o painel administrativo e montado e onde ficam os controles comuns de acesso, sessao, assets, hooks, filas e scheduler. Ele serve como mapa para localizar o ponto de entrada correto antes de alterar uma tela ou comportamento transversal.

O sistema e um monolito Laravel. Os dominios funcionais convivem no mesmo painel e compartilham autenticacao, autorizacao, escopos, notificacoes, filas, exportacoes e infraestrutura. Este documento nao substitui as regras de cada dominio de negocio.

## Stack confirmada

- PHP `^8.4`
- Laravel `^12`
- Filament `5.0`
- Spatie Laravel Permission `^6.17`
- Laravel Socialite `^5.23`
- MySQL 8
- Redis 7 com Predis
- Tailwind CSS 4 e Vite 6
- DomPDF e PhpSpreadsheet
- PHPUnit 11

O `README.md` historico ainda descreve Filament 3 e PHP 8.2; para versoes e dependencias, `composer.json` e `package.json` sao as fontes de verdade.

## Entidades e tabelas transversais

- `User` e `users`: identidade autenticavel do painel, guard `web`
- roles e permissoes Spatie: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
- `notifications`: notificacoes Laravel por usuario
- `notificacao_envios`: registro operacional de envios manuais
- `export_requests`: fila e ciclo de vida das exportacoes assincronas
- tabelas de cache, sessao, jobs e failed jobs conforme configuracao Laravel/Redis

## Pontos de entrada do codigo

### Aplicacao e rotas

- `bootstrap/app.php`: grupos de middleware, aliases, health check e scheduler
- `routes/web.php`: paginas publicas, OAuth, login, rotas administrativas e downloads
- `routes/api.php`: API administrativa de manutencao
- `routes/mobile.php`: rotas do aplicativo/PWA quando carregadas por `web.php`

### Painel Filament

- `app/Providers/Filament/AdminPanelProvider.php`: configuracao do painel `admin`
- `app/Filament/Admin/Resources`: CRUDs e hubs operacionais
- `app/Filament/Admin/Pages`: paginas fora de resources
- `app/Filament/Admin/Clusters`: agrupamentos, quando existirem
- `app/Filament/Admin/Widgets`: widgets descobertos automaticamente

### Registro transversal

- `app/Providers/AppServiceProvider.php`: policies, gates, observers, listeners, assets e hooks globais
- `app/Models/User.php`: gate final do painel e helpers de acesso
- `app/Http/Controllers/Auth/AdminLoginController.php`: login manual atual
- `app/Http/Controllers/Auth/GoogleAuthController.php`: login Google

## Configuracao do painel `admin`

O painel e configurado com:

- id e path `admin`
- login customizado por `AdminLoginController`
- profile customizado pelo plugin de auth designer
- busca global desativada
- dark mode desativado
- descoberta automatica de resources, pages, clusters e widgets
- sidebar recolhivel no desktop
- paleta visual propria

### Middleware do painel

O pipeline do painel inclui, entre outros:

- normalizacao do dominio do cookie de sessao
- criptografia de cookies e inicio de sessao
- autenticacao persistente do Filament
- modo de visualizacao de perfil
- bloqueio de escritas no modo de visualizacao
- CSRF e bindings
- troca obrigatoria de senha
- bloqueio de professor com pendencia de transferencia

A ordem desses middlewares importa. Alterar apenas a rota ou apenas o resource pode nao reproduzir os mesmos guardas usados pelo painel.

## Hooks e comportamento global da interface

O painel injeta hooks para:

- notificacoes e usuarios online antes do menu do usuario
- indicador do modo de visualizacao no inicio do `body`
- heartbeat de presenca no final do `body`
- estilos de inventario e de sobreposicao de camadas no `head`
- estilos responsivos especificos para Pedidos e Alunos

`AppServiceProvider` tambem registra JS/CSS globais para selects em modal, colagem de datas, loading de actions e estilos gerais.

## Fluxos principais

### 1. Login e acesso efetivo

1. O login manual passa por `AdminLoginController`, com validacao, rate limiting e regeneracao da sessao.
2. O login Google passa por Socialite e `GoogleService`.
3. Nos dois fluxos, `User::canAccessAdminPanel()` e o gate final.
4. Se `must_change_password` estiver ativo, o usuario e redirecionado para a troca obrigatoria.
5. Depois da autenticacao, os middlewares persistentes continuam aplicando bloqueios contextuais.

### 2. Descoberta e navegacao

A descoberta automatica nao significa que toda classe aparece no menu. A navegacao depende de:

- policy do model
- `canAccess()` em Pages
- `shouldRegisterNavigation`
- `shouldRegisterNavigation = false` em resources mantidos apenas para rotas internas
- verificacoes adicionais de role ou permissao na propria UI

Exemplo atual: `UserResource` continua possuindo rotas e CRUD, mas foi retirado da navegacao porque a gestao operacional de acesso passou a convergir para o hub de Pessoas.

### 3. Policies, gates e escopo

- `AppServiceProvider` registra as policies explicitamente com `Gate::policy()`.
- Policies respondem pela capacidade ampla: listar, criar, editar, excluir ou executar uma acao.
- O escopo real normalmente e aplicado em services ou queries por escola, setor, pessoa ou papel.
- Esconder uma action no Filament nao substitui policy nem validacao no backend.

### 4. Observers e listeners

Observers registrados globalmente incluem:

- `PedidoObserver`
- `ProfessorObserver`
- `TurmaComponenteProfessorObserver`
- `AvaliacaoDashboardSourceObserver` para Avaliacao, Pauta e Turma

Listeners atualizam presenca no login e invalidam cache da contagem de notificacoes quando uma notificacao de banco e enviada.

### 5. Scheduler

O scheduler atual executa:

- notificacao de pedidos atrasados a cada minuto
- notificacao de pedidos a vencer diariamente as 08:00
- notificacao de balancos de estoque vencidos a cada hora
- notificacao de alunos pendentes de transferencia diariamente as 08:00
- limpeza de exportacoes diariamente as 02:30
- monitoramento de exportacoes travadas a cada cinco minutos

Todos usam `withoutOverlapping()` com janela propria.

## Infraestrutura Docker

A composicao de producao possui:

- `app`: imagem Laravel com Nginx/PHP e health check baseado nos caches de runtime
- `cron`: executa `schedule:run` a cada 60 segundos
- `queue-notifications`: processa `notifications,default`
- `queue`: processa `imports,exports,default` com prioridade reduzida e timeout maior
- `db`: MySQL 8 com volume local
- `redis`: cache, sessao e filas, com persistencia AOF e `noeviction`
- `phpmyadmin`: administracao do banco

Cache, sessao, permission cache e queues usam Redis em producao. Os workers possuem limites separados de CPU, memoria, tentativas e timeout.

## Permissoes, policies e filtros de escopo

### Gate do painel

Ponto canonico: `User::canAccessAdminPanel()`.

O acesso e liberado por `email_approved`, pela permissao de acesso ao painel ou pela role legada correspondente. Esse metodo tambem e usado pelo login Google.

### Escopos

Os principais services transversais sao:

- `PessoaScopeService`: pessoas, escolas e setores derivados dos vinculos funcionais
- `UserSetorAccessService`: acesso operacional por setor
- `SetorHierarchyService`: ancestralidade e descendencia de setores
- services de cada dominio para filtros adicionais

O comportamento esperado e falhar fechado quando o usuario nao possui escopo valido.

## Efeitos colaterais

- Historico: observers e services funcionais podem registrar historico fora da UI.
- Notificacao: login, exportacoes, pedidos, transferencias e balancos podem gerar notificacoes.
- Exportacao: processamentos pesados usam filas separadas e `export_requests`.
- Arquivos: storage publico e privado sao usados conforme o fluxo; downloads sensiveis devem passar por policy.
- Scheduler: depende do container `cron` estar ativo e do cache de locks funcionar.

## Pontos seguros para extensao

- Nova tela: criar Resource/Page e definir policy ou `canAccess()` antes de expor a navegacao.
- Nova regra de negocio: preferir service/model canonico, mantendo a action Filament como adaptador.
- Nova permissao: adicionar ao enum/catalogo, sincronizar o banco e revisar presets.
- Novo escopo: combinar policy, query escopada e validacao backend.
- Novo processamento pesado: usar job/queue e, quando gerar arquivo, integrar com o subsistema de exportacoes.
- Novo comportamento global de UI: registrar hook ou asset no provider; evitar duplicacao por pagina.

## Riscos de regressao

- regra importante implementada apenas em action ou schema Filament
- divergencia entre policy e query escopada
- mudanca na ordem dos middlewares persistentes
- dependencias de strings seedadas para roles, permissoes, status e setores
- teste SQLite passar enquanto MySQL, Redis ou concorrencia real falham
- worker ou scheduler parado, deixando notificacoes e exportacoes sem processamento
- alterar hooks globais e afetar telas nao relacionadas

## Testes existentes e lacunas

A suite usa SQLite em memoria, cache e sessao em array e fila `sync`. Isso oferece testes rapidos, mas nao reproduz integralmente:

- MySQL e seus indices/constraints
- Redis e locks distribuidos
- concorrencia entre workers
- timeouts reais de exportacao
- comportamento do container Nginx/PHP-FPM

Ha testes de feature extensos em dominios como Pessoas, Turmas, Avaliacoes, Inventario e Alunos. Permanecem lacunas transversais em login completo, middlewares persistentes, profile preview e inicializacao dos containers.

Validacao minima para alteracoes arquiteturais:

- login manual e Google
- usuario aprovado, pendente e com troca obrigatoria de senha
- modo de visualizacao sem escrita
- menu e actions com e sem permissao
- query de usuario global, por escola, por setor e sem escopo
- execucao real de uma notificacao e uma exportacao em Redis
- `schedule:list` e uma rodada do scheduler

## Divergencias corrigidas nesta revisao

- substituido `LoginPage` pelo `AdminLoginController` como login manual atual
- atualizadas as versoes para PHP 8.4, Laravel 12 e Filament 5
- documentados profile preview, heartbeat, troca obrigatoria de senha e bloqueio por transferencia
- documentadas as filas separadas, Redis e o scheduler completo
- removida a afirmacao de ausencia geral de testes do painel; a lacuna agora e descrita apenas para fluxos transversais especificos
