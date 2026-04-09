# Governanca Documental do Gestao-Edu

Esta pasta organiza a documentacao operacional do projeto em um formato reutilizavel por pessoas e por agentes. O objetivo e reduzir conflitos na implementacao de novas features, deixar explicito onde cada regra deve viver e consolidar a leitura do sistema por dominio.

## Estrutura

- [`docs/domains/`](./domains/README.md): documentacao por dominio funcional.
- [`docs/cross-cutting/`](./cross-cutting/README.md): regras transversais que atravessam varios modulos.
- [`docs/system/`](./system/README.md): visao consolidada do sistema e pipeline de agentes.
- [`docs/templates/`](./templates/domain-doc-template.md): contratos de escrita para domain docs e feature briefs.
- [`docs/context-skills/`](./context-skills/README.md): material semente ja existente, mantido como base de contexto e apoio.

## Hierarquia de leitura

1. [`docs/system/documento-mestre.md`](./system/documento-mestre.md)
2. [`docs/domains/README.md`](./domains/README.md)
3. documentacao do dominio principal da tarefa
4. documentacao transversal aplicavel
5. templates e checklists de execucao

## Regras de uso

- Toda feature nova deve identificar primeiro um dominio principal.
- Nenhuma regra nova deve ser adicionada sem validar permissao, escopo por `id_escola` ou `setor` e efeitos colaterais.
- Toda alteracao relevante deve atualizar a documentacao do dominio afetado no mesmo ciclo da implementacao.
- `docs/context-skills/` continua versionado, mas a base canonica de governanca passa a ser `docs/domains`, `docs/cross-cutting` e `docs/system`.

## Status inicial

- Estrutura documental: pronta
- Templates operacionais: prontos
- Documentacao transversal: pronta
- Piloto de dominios: autenticacao/autorizacao/permissoes e merenda/contratos/estoque
- Consolidado de sistema: iniciado
- Dominios restantes: mapeados e aguardando preenchimento incremental
