# Documento Mestre do Sistema

Este e o ponto de entrada canonico para entendimento rapido do `Gestao-Edu` no formato novo de governanca documental.

## Resumo

O projeto e um monolito Laravel 12 com painel Filament e forte concentracao de regra em models, services, policies, observers e actions da interface administrativa. O sistema mistura varios dominios de negocio no mesmo painel: acesso e administracao, estrutura escolar, manutencao predial, alimentacao escolar, inventario, relatorios e notificacoes.

O objetivo desta governanca e fazer com que toda alteracao futura tenha:

- dominio principal definido
- documentacao de apoio consistente
- ponto canonico da regra escolhido antes da implementacao
- revisao obrigatoria de permissao, escopo e efeitos colaterais

## Fontes de verdade

1. codigo, migrations e seeders
2. documentos em `docs/domains/`
3. documentos em `docs/cross-cutting/`
4. este consolidado em `docs/system/`
5. material semente em `docs/context-skills/`

## Mapa atual de dominios

| Dominio | Status documental | Observacao |
| --- | --- | --- |
| Arquitetura geral e painel Filament | planejado | ha base em `docs/context-skills/arquitetura-geral.md` |
| Autenticacao, autorizacao e permissoes | piloto pronto | cobre onboarding, gate do painel, roles, permissoes e policies |
| Cadastros administrativos base | planejado | ainda depende de consolidacao por dominio |
| Estrutura escolar e pedagogica | planejado | mapeado no contexto atual, falta contrato final |
| Alunos, laudos e retencao | planejado | exige revisao combinada de policy e storage |
| Pedidos de manutencao | planejado | ja possui skill semente forte |
| Merenda, contratos e estoque | piloto pronto | cobre contratos, pedidos, saldo e estoque central |
| Estoque da matriz | planejado | parte do piloto atual, mas ainda pede aprofundamento proprio |
| Inventario escolar e pedidos internos | planejado | possui bastante codigo e testes, falta consolidacao final |
| Relatorios, exportacoes e arquivos | planejado | tema transversal com controllers e services dedicados |
| Integracoes, notificacoes e scheduler | planejado | depende de leitura cruzada de observer, cron e notificacoes |

## Hotspots tecnicos atuais

- catalogo de permissoes disperso e parcialmente duplicado
- mistura de policy com filtro por escola e setor fora da policy
- regras importantes ainda espalhadas entre service, model, observer e UI do Filament
- baixa cobertura de testes em fluxos sensiveis, especialmente acesso, pedidos e merenda
- dependencia de strings seedadas para status, roles, setores e permissoes

## Fluxo obrigatorio para futuras features

1. abrir o documento deste dominio
2. ler os docs transversais aplicaveis
3. preencher o feature brief
4. decidir o ponto canonico da regra
5. implementar
6. atualizar a documentacao do dominio
7. registrar validacao automatizada ou manual

## Entregas implementadas nesta primeira rodada

- estrutura nova de `docs/domains`, `docs/cross-cutting`, `docs/system` e `docs/templates`
- templates padronizados para domain doc e feature brief
- documentos transversais de permissao, escopo, ponto canonico, testes e checklist
- pilotos de dominio para acesso/permissoes e merenda/contratos/estoque
- pipeline documental definido para agentes
