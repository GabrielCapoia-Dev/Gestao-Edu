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

## Perfil legado

- `estoque-inventario-analyst`: agente exploratorio mantido, agora ajustado para conversar com a estrutura documental nova
