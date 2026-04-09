# Template de Domain Doc

Use este contrato para qualquer nova documentacao de dominio. O objetivo e manter a mesma profundidade e o mesmo formato, evitando lacunas e sobreposicoes.

## Metadados

- Dominio:
- Status: `draft`, `pilot`, `validated`, `needs-review`
- Ultima revisao:
- Responsavel:
- Fontes consultadas:

## Objetivo do dominio

Explique em 3 a 6 linhas:

- qual problema de negocio o dominio resolve
- quais atores usam esse fluxo
- qual o limite do dominio e o que fica fora dele

## Entidades e tabelas principais

Liste:

- models centrais
- tabelas relevantes
- enums, statuses ou vocabulario funcional indispensavel

## Pontos de entrada do codigo

Liste os arquivos mais importantes do dominio:

- models
- services
- resources/pages do Filament
- controllers, policies, observers, jobs ou commands, quando existirem

## Fluxos principais

Descreva os fluxos que realmente movem o dominio:

- criacao
- alteracao
- aprovacoes ou transicoes
- leitura operacional
- exportacoes, anexos ou sincronizacoes, se houver

## Permissoes, policies e filtros de escopo

Documente separadamente:

- permissao Spatie
- policy
- filtro de consulta por `id_escola`, `setor` ou papel
- middleware de rota, se existir

## Regras de negocio criticas

Liste os invariantes que nao podem ser quebrados.

## Efeitos colaterais

Cubra sempre estes itens, mesmo que seja para registrar "nao possui":

- historico
- notificacao
- exportacao
- arquivos
- scheduler

## Seeders, enums e vocabulario funcional dependente

Registre strings, nomes ou seeds dos quais o fluxo depende.

## Pontos seguros para extensao

Explique onde uma feature nova deve encaixar sem duplicar regra.

## Riscos de regressao

Liste os pontos que mais facilmente quebram o fluxo.

## Testes existentes e lacunas

Liste:

- testes automatizados que ja cobrem o dominio
- validacoes manuais minimas quando ainda nao houver cobertura suficiente

## Regras de escrita

- nao inventar comportamento que nao esteja sustentado pelo codigo ou por seeders
- chamar incertezas explicitamente
- apontar o ponto canonico da regra quando houver comportamento espalhado
- preferir arquivos e fluxos principais em vez de inventario exaustivo de classes
