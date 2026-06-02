# Teste de carga com K6 e Grafana

Esta pasta contem uma suite HTTP de carga para o Gestao Edu. O fluxo padrao faz login em `/admin/login` com usuarios nao-admin e navega por telas do painel Filament.

O K6 nao abre um navegador real. Ele mede requisicoes HTTP do backend: login, redirects, cookies de sessao e GETs das telas. Isso e ideal para achar gargalos de servidor, banco, Redis e permissoes. Para medir renderizacao visual ou cliques reais no DOM, use uma suite separada com browser automation.

## Arquivos locais

Crie ou mantenha estes arquivos fora do Git:

- `load-tests/data/users.local.csv`: usuarios sinteticos de teste gerados pelo comando `loadtest:users`.
- `load-tests/.env.local`: overrides opcionais de URL, portas e perfil.
- `load-tests/results/`: saidas locais de execucoes.

Formato do CSV. O campo `profile` define qual fluxo de telas o usuario percorre:

```csv
email,password,profile
loadtest-secretario+001@loadtest.local,Mudar@1234,secretario
```

Antes de gerar a massa, sincronize as permissoes e niveis de acesso do ambiente de teste:

```bash
php artisan permissoes:criar
```

Para gerar uma massa local de 300 usuarios sinteticos de carga:

```bash
php artisan loadtest:users --count=300 --output=load-tests/data/users.local.csv
```

O comando cria usuarios com e-mails `loadtest-secretario+NNN@loadtest.local`, senha padrao `Mudar@1234`, roles `Acessar Painel` e `Secretário`, `must_change_password=false`, e vinculo aleatorio com escolas ativas existentes. O vinculo e gravado em `users.id_escola` e tambem no pivot `escola_user`.

O perfil `secretario` navega por:

- `/admin/dashboard`
- `/admin/professores`
- `/admin/turmas`

## Comandos

Subir Grafana e InfluxDB:

```bash
npm run k6:up
```

Rodar smoke leve, com 3 usuarios virtuais por 1 minuto:

```bash
npm run k6:smoke
```

Rodar carga conservadora, com rampa ate 20 usuarios virtuais:

```bash
npm run k6:load
```

Rodar carga media, com rampa ate 50 usuarios virtuais:

```bash
npm run k6:medium
```

Rodar rampas maiores:

```bash
npm run k6:100
npm run k6:150
npm run k6:300
```

Abrir o Grafana em `http://localhost:3001`, usando `admin` / `admin`, e acessar o dashboard `Gestao Edu - K6 Overview`.

O dashboard mostra os principais sinais para encontrar gargalos:

- tempo de login p95 por usuario e tempo de login geral;
- tempo p95 e tempo medio por tela;
- requisicoes por rota;
- erros agrupados por status HTTP;
- taxa de 403 por tela;
- VUs ativos;
- duracao da iteracao completa;
- paginas mais lentas por p95;
- sucesso de login e sucesso de carregamento das paginas.

Encerrar a stack local:

```bash
npm run k6:down
```

## Configuracoes uteis

Variaveis principais:

- `K6_BASE_URL`: URL alvo. Padrao: `https://edu.hubdetestes.online`.
- `K6_USERS_FILE`: caminho do CSV dentro do container. Padrao: `/scripts/data/users.local.csv`.
- `K6_PROFILE`: `smoke`, `conservative`, `medium`, `large`, `xlarge` ou `target300`.
- `K6_NAV_PATHS`: override global das telas no formato `nome:/rota;nome:/rota`.
- `K6_PROFILE_PATHS`: override por perfil no formato `school=dashboard:/admin/dashboard,alunos:/admin/alunos;staff=dashboard:/admin/dashboard,usuarios:/admin/usuarios`.
- `K6_INCLUDE_HEARTBEAT`: `true` para simular o POST de presenca.
- `K6_PAGE_DELAY_MIN` e `K6_PAGE_DELAY_MAX`: pausa entre telas.

Exemplo para testar apenas dashboard e perfil:

```bash
docker compose -f docker-compose.k6.yml run --rm -e K6_NAV_PATHS="dashboard:/admin/dashboard;profile:/admin/profile" k6
```

## Observacoes

- Cada VU faz login uma vez e reutiliza a sessao nas iteracoes seguintes.
- Cada perfil navega apenas nas telas configuradas para ele.
- `403` agora falha o roteiro, porque indica que o perfil esta tentando acessar uma tela fora do fluxo real.
- Erros de login, `419`, `500`, falhas de rede e lentidao acima do baseline continuam fazendo o teste falhar.
- O baseline padrao falha se `p95` das paginas passar de 2 segundos ou se falhas HTTP inesperadas passarem de 1%.
