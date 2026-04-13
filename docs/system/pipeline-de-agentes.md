# Pipeline de Agentes para Documentacao

Este pipeline transforma a documentacao em processo repetivel. O agente final nao inventa comportamento; ele consolida o que foi confirmado pelos agentes de dominio e pelo revisor cruzado.

## Principios

- um agente por dominio ou macrodominio relevante
- nada de agente por arquivo ou tela isolada
- todo agente escreve no mesmo contrato de domain doc
- contradicoes e lacunas devem ser explicitadas antes da consolidacao

## Etapas

### 1. Agentes de dominio

Cada agente:

- le os documentos semente e o codigo do dominio
- preenche ou atualiza o arquivo alvo em `docs/domains/`
- registra regras criticas, permissoes, escopo, efeitos colaterais e lacunas de teste

### 2. Agente revisor cruzado

O revisor cruza os documentos de dominio para encontrar:

- sobreposicao
- contradicao
- regra transversal faltando
- duplicidade de responsabilidade

O output esperado e ajuste em `docs/cross-cutting/` e uma lista objetiva de conflitos a resolver.

### 3. Agente consolidado do sistema

O consolidado:

- atualiza o `documento-mestre`
- atualiza glossario, mapa de dominios e hotspots
- garante consistencia entre dominios e docs transversais

## Topologia recomendada

| Agente | Escopo | Saida principal |
| --- | --- | --- |
| `documentation-architecture-analyst` | arquitetura geral e painel | `docs/domains/arquitetura-e-painel-filament.md` |
| `documentation-auth-access-analyst` | autenticacao, autorizacao e permissoes | `docs/domains/autenticacao-autorizacao-e-permissoes.md` |
| `documentation-admin-cadastros-analyst` | cadastros administrativos base | `docs/domains/cadastros-administrativos-base.md` |
| `documentation-school-structure-analyst` | estrutura escolar e pedagogica | `docs/domains/estrutura-escolar-e-pedagogica.md` |
| `documentation-maintenance-requests-analyst` | pedidos de manutencao | `docs/domains/pedidos-de-manutencao.md` |
| `documentation-school-meals-contracts-stock-analyst` | merenda, contratos e estoque | `docs/domains/merenda-contratos-e-estoque.md` |
| `documentation-stock-analyst` | estoque da matriz | `docs/domains/estoque-da-matriz.md` |
| `documentation-school-inventory-analyst` | inventario escolar e pedidos internos | `docs/domains/inventario-escolar-e-pedidos-internos.md` |
| `documentation-reports-files-analyst` | relatorios, exportacoes e arquivos | `docs/domains/relatorios-exportacoes-e-arquivos.md` |
| `documentation-integrations-notifications-analyst` | integracoes, notificacoes e scheduler | `docs/domains/integracoes-notificacoes-e-scheduler.md` |
| `documentation-cross-reviewer` | revisao cruzada | docs transversais e apontamento de lacunas |
| `documentation-system-consolidator` | consolidacao final | `docs/system/documento-mestre.md` e `docs/system/glossario.md` |

## Regra de qualidade

- o agente deve citar explicitamente as fontes usadas
- se uma regra parecer espalhada, o agente deve apontar o ponto canonico ou registrar a ambiguidade
- se nao houver teste suficiente, o agente deve registrar a lacuna e sugerir validacao manual minima
