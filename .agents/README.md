# Agentes de Documentacao

Esta pasta agrupa os perfis de agentes usados para produzir e revisar a documentacao do projeto.

## Regra geral

- agentes de dominio escrevem em `docs/domains/`
- agente revisor cruza dominios e ajusta `docs/cross-cutting/`
- agente consolidado atualiza `docs/system/`
- todos devem seguir `docs/templates/domain-doc-template.md`

## Perfis padronizados

- `documentation-architecture-analyst`
- `documentation-auth-access-analyst`
- `documentation-admin-cadastros-analyst`
- `documentation-school-structure-analyst`
- `documentation-student-support-analyst`
- `documentation-maintenance-requests-analyst`
- `documentation-school-meals-contracts-stock-analyst`
- `documentation-stock-analyst`
- `documentation-school-inventory-analyst`
- `documentation-reports-files-analyst`
- `documentation-integrations-notifications-analyst`
- `documentation-cross-reviewer`
- `documentation-system-consolidator`

## Agentes operacionais

- `maintenance-flow-manager`: agente para evoluir o modulo de manutencao. Usa `$manutencao-fluxo-pedidos`, `$manutencao-cadastros-tipos` e `$manutencao-relatorios-feedback` para orientar mudancas em pedidos, cadastros, relatorios e feedbacks.
- `manutencao-fluxo-pedidos-manager`: usa `$manutencao-fluxo-pedidos` para pedidos, status, historico, anexos, setores, pedidos adicionais, notificacoes e escopo.
- `manutencao-cadastros-tipos-manager`: usa `$manutencao-cadastros-tipos` para tipos de manutencao, opcoes, status, setores, empresas, seeders, roles e permissoes.
- `manutencao-relatorios-feedback-manager`: usa `$manutencao-relatorios-feedback` para PDFs, exportacoes, dashboards, graficos, filtros, fotos, anexos e feedbacks.

## Kit Gestao-Edu

Agents sao entradas de trabalho para a UI e para prompts padronizados. Skills sao guias operacionais carregados sob demanda, com contexto, sequencia de leitura, checklist e validacao.

- `gestao-edu-architect`: usa `$gestao-edu-kit` para escolher skills por dominio e organizar mudancas amplas no Laravel/Filament do projeto.
- `gestao-edu-access-manager`: usa `$gestao-edu-acesso-permissoes-flow` para login, Google OAuth, roles, permissoes, policies, menus e escopo por escola ou setor.
- `gestao-edu-pedagogical-structure-manager`: usa `$gestao-edu-professores-turmas-componentes-flow` para professores, turmas, series, componentes e vinculos professor-componente-turma.
- `gestao-edu-evaluations-manager`: usa `$gestao-edu-avaliacoes-flow` para avaliacoes, pautas, alternativas, respostas, dashboards e pareceres.
- `gestao-edu-student-movement-manager`: usa `$gestao-edu-transferencia-remanejamento-flow` para transferencia, remanejamento, matricula pendente, bloqueios avaliativos e parecer de transferencia.
- `estoque-flow-mapper`: permanece como skill especializada para estoque, merenda, inventarios, reservas e romaneios.
- `maintenance-flow-manager`: permanece como agent operacional composto para o modulo de manutencao, acionando as tres skills especializadas de manutencao.

## Perfil legado

- `estoque-inventario-analyst`: agente exploratorio mantido, agora ajustado para conversar com a estrutura documental nova
