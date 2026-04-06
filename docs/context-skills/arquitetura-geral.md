# Arquitetura Geral

## Objetivo

Explicar como o sistema esta montado, quais sao os pontos de entrada e onde ficam as responsabilidades principais.

## Onde isso vive no codigo

- `bootstrap/app.php`
- `routes/web.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Providers/AppServiceProvider.php`
- `docker-compose.yml`
- `Dockerfile`

## Comportamento e estrutura principal

- O projeto e um monolito Laravel 12 com painel administrativo Filament 5.
- A interface principal e servida pelo painel `admin`, configurado em `AdminPanelProvider`.
- O acesso usa o guard `web`, sessao de Laravel e auth middleware do Filament.
- O painel descobre recursos, paginas, clusters e widgets diretamente em `app/Filament/Admin`.
- O projeto usa Services para concentrar casos de uso relevantes, mas parte da regra ainda vive em Models, Policies, Observers e Schemas/Tables do Filament.
- `AppServiceProvider` registra policies, observer de `Pedido`, assets do painel e hooks de renderizacao.
- O scheduler e configurado em `bootstrap/app.php` e hoje executa notificacoes de pedidos atrasados e a vencer.
- A infraestrutura local usa Docker com container da aplicacao, MySQL, phpMyAdmin e um container separado rodando `php artisan schedule:run`.

## Regra de negocio x tecnica x infraestrutura

- Regra de negocio: fluxo de `Pedido`, `PedidoMerenda`, aprovacao de acesso, restricoes por escola.
- Regra tecnica: policies, observer, Livewire para notificacoes, organizacao Filament.
- Infraestrutura: Docker, scheduler em container `cron`, MySQL, disks de storage.

## Riscos e cuidados

- O README nao representa o escopo atual inteiro do sistema.
- O codigo esta fortemente orientado a Filament; alteracoes de comportamento muitas vezes passam por schema, table action e policy, nao apenas controller/service.
- Ha acoplamento a nomes de status e setores seedados, principalmente no fluxo de pedidos.

## Quando consultar

- Ao iniciar manutencao ampla
- Antes de reorganizar camadas ou criar modulo novo
- Quando for dificil localizar a origem de um comportamento
