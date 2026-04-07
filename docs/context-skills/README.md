# Skills de Contexto do Projeto

Base versionada de contexto operacional do `Gestao-Edu`, pensada para acelerar manutencao, debugging, revisao de logica e evolucao de features sem depender de releitura completa do repositorio.

## Visao curta do sistema

O projeto e um monolito Laravel 12 com painel Filament 5 e foco em gestao educacional. O codigo atual cobre:

- acesso, usuarios, papeis e permissoes
- manutencao predial escolar com fluxo de pedidos
- alimentacao escolar com contratos, margem e estoque
- estrutura pedagogica, alunos, turmas, professores e laudos
- relatorios, exportacoes e notificacoes internas

O README do projeto esta parcialmente defasado. Estas skills refletem o estado real do codigo.

## Como usar este indice

Consulte a skill pelo dominio da tarefa antes de alterar codigo sensivel. Em mudancas grandes, leia primeiro `arquitetura-geral.md`, `mapa-de-modulos.md` e a skill especifica do fluxo afetado.

## Skills disponiveis

| Skill | Objetivo | Quando consultar |
| --- | --- | --- |
| [arquitetura-geral.md](./arquitetura-geral.md) | Explicar entrypoints, stack, camadas, infraestrutura e composicao geral do monolito. | Inicio de onboarding, investigacao ampla, refactor estrutural. |
| [mapa-de-modulos.md](./mapa-de-modulos.md) | Mapear os dominios funcionais e onde cada responsabilidade vive no codigo. | Descobrir onde mexer antes de abrir arquivos. |
| [autenticacao-e-autorizacao.md](./autenticacao-e-autorizacao.md) | Resumir login Filament, Google OAuth, aprovacao de email, roles, permissoes e policies. | Bugs de acesso, login, permissao e visibilidade. |
| [fluxo-estoque-atual.md](./fluxo-estoque-atual.md) | Mapear o estoque da matriz, balancos, baixas, relatorios e riscos do fluxo atual. | Antes de estender ou refatorar a logica do estoque principal. |
| [fluxo-pedidos-manutencao.md](./fluxo-pedidos-manutencao.md) | Documentar o ciclo de vida de `Pedido`, historico, anexos, feedback e notificacoes. | Qualquer alteracao em manutencao, status ou historico. |
| [regras-criticas-pedidos.md](./regras-criticas-pedidos.md) | Destacar dependencias, strings seedadas, efeitos colaterais e riscos do fluxo de pedidos. | Antes de mudar status, setores, notificacoes ou observer. |
| [merenda-contratos-e-estoque.md](./merenda-contratos-e-estoque.md) | Explicar reserva, utilizacao, saldo, entregas, cancelamento e impacto em estoque. | Alteracoes em merenda, contratos, margem ou estoque. |
| [inventarios-escolares-e-pedidos-internos.md](./inventarios-escolares-e-pedidos-internos.md) | Explicar como encaixar inventarios por escola, pedidos internos e romaneios no modulo atual. | Inicio de implementacao ou manutencao do novo fluxo de inventarios. |
| [persistencia-e-arquivos.md](./persistencia-e-arquivos.md) | Resumir migrations-chave, seeders estruturantes, storage e downloads protegidos. | Mudancas de banco, upload/download, seed ou migracao. |
| [padrao-relatorios-pdf.md](./padrao-relatorios-pdf.md) | Definir o layout, renderer e metadados obrigatorios dos relatorios PDF. | Criacao ou manutencao de relatorios e exportacoes PDF. |
| [testes-e-debug.md](./testes-e-debug.md) | Registrar estado atual de testes, passos de reproducao local e pontos de observabilidade. | Debugging, validacao manual e estrategia de teste. |
| [pontos-frageis-e-divida-tecnica.md](./pontos-frageis-e-divida-tecnica.md) | Consolidar riscos reais, inconsistencias e areas com maior chance de regressao. | Planejamento de refactor, review e analise de risco. |
| [checklist-alterar-logica-sensivel.md](./checklist-alterar-logica-sensivel.md) | Checklist curto antes de mexer em fluxos que propagam efeitos colaterais. | Toda alteracao em regras centrais. |
| [checklist-nova-feature.md](./checklist-nova-feature.md) | Checklist para adicionar funcionalidade sem quebrar comportamento existente. | Inicio de novas features ou extensoes de modulo. |

## Ordem sugerida de leitura

1. `arquitetura-geral.md`
2. `mapa-de-modulos.md`
3. skill especifica do dominio
4. `pontos-frageis-e-divida-tecnica.md`
5. um dos checklists operacionais
