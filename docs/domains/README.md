# Domains

Esta pasta contem a documentacao canonica por dominio funcional. O objetivo e evitar documentacao por arquivo ou por tela isolada, mantendo contexto suficiente para futuras features sem gerar duplicidade.

## Cobertura atual

| Dominio | Documento alvo | Fonte semente principal | Agente sugerido | Status |
| --- | --- | --- | --- | --- |
| Arquitetura geral e painel Filament | `docs/domains/arquitetura-e-painel-filament.md` | `docs/context-skills/arquitetura-geral.md` | `documentation-architecture-analyst` | planejado |
| Autenticacao, autorizacao e permissoes | [`docs/domains/autenticacao-autorizacao-e-permissoes.md`](./autenticacao-autorizacao-e-permissoes.md) | `docs/context-skills/autenticacao-e-autorizacao.md` | `documentation-auth-access-analyst` | piloto pronto |
| Cadastros administrativos base | `docs/domains/cadastros-administrativos-base.md` | `docs/context-skills/mapa-de-modulos.md` | `documentation-admin-cadastros-analyst` | planejado |
| Estrutura escolar e pedagogica | `docs/domains/estrutura-escolar-e-pedagogica.md` | `docs/context-skills/mapa-de-modulos.md` | `documentation-school-structure-analyst` | planejado |
| Alunos, laudos e retencao | `docs/domains/alunos-laudos-e-retencao.md` | `docs/context-skills/mapa-de-modulos.md` | `documentation-student-support-analyst` | planejado |
| Pedidos de manutencao | `docs/domains/pedidos-de-manutencao.md` | `docs/context-skills/fluxo-pedidos-manutencao.md` | `documentation-maintenance-requests-analyst` | planejado |
| Merenda, contratos e estoque | [`docs/domains/merenda-contratos-e-estoque.md`](./merenda-contratos-e-estoque.md) | `docs/context-skills/merenda-contratos-e-estoque.md` | `documentation-school-meals-contracts-stock-analyst` | piloto pronto |
| Estoque da matriz | `docs/domains/estoque-da-matriz.md` | `docs/context-skills/fluxo-estoque-atual.md` | `documentation-stock-analyst` | planejado |
| Inventario escolar e pedidos internos | `docs/domains/inventario-escolar-e-pedidos-internos.md` | `docs/context-skills/inventarios-escolares-e-pedidos-internos.md` | `documentation-school-inventory-analyst` | planejado |
| Relatorios, exportacoes e arquivos | `docs/domains/relatorios-exportacoes-e-arquivos.md` | `docs/context-skills/padrao-relatorios-pdf.md` e `docs/context-skills/persistencia-e-arquivos.md` | `documentation-reports-files-analyst` | planejado |
| Integracoes, notificacoes e scheduler | `docs/domains/integracoes-notificacoes-e-scheduler.md` | `docs/context-skills/testes-e-debug.md` e `docs/context-skills/arquitetura-geral.md` | `documentation-integrations-notifications-analyst` | planejado |

## Regras para novos documentos

- seguir o contrato em [`docs/templates/domain-doc-template.md`](../templates/domain-doc-template.md)
- registrar arquivos e fluxos principais, nao lista exaustiva de tudo
- apontar o ponto canonico da regra quando houver comportamento espalhado
- referenciar a documentacao transversal aplicavel

## Pilotos iniciais

- [`autenticacao-autorizacao-e-permissoes.md`](./autenticacao-autorizacao-e-permissoes.md)
- [`merenda-contratos-e-estoque.md`](./merenda-contratos-e-estoque.md)
