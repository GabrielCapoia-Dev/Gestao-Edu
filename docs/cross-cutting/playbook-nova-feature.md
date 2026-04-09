# Playbook de Nova Feature

Toda feature nova deve seguir este fluxo. O objetivo e evitar criacao de regra paralela, conflito entre camadas e regressao silenciosa.

## Passo a passo

1. Classifique a feature em um dominio principal.
2. Leia a documentacao do dominio e os docs transversais aplicaveis.
3. Preencha o [`feature brief`](../templates/feature-brief-template.md).
4. Defina o ponto canonico da regra antes de escrever codigo.
5. Revise permissao, policy, `id_escola`, `setor` e efeitos colaterais.
6. Implemente sem duplicar comportamento em camada paralela.
7. Atualize a documentacao do dominio e registre a validacao.

## Definicao de pronto

- regra implementada no ponto canonico
- permissao e escopo revisados
- testes automatizados criados ou roteiro manual registrado
- documentacao do dominio atualizada
- impacto transversal revisado

## Sinais de alerta

- a feature cria novo status, permissao ou seed sem documentacao
- a mesma regra aparece em model, service e action sem uma fonte clara
- a policy libera o recurso, mas a query nao restringe o escopo
- a feature altera saldo, historico ou exportacao sem revisao do dominio completo

## Leitura minima obrigatoria

- [`docs/system/documento-mestre.md`](../system/documento-mestre.md)
- domain doc relevante
- [`matriz-permissoes-e-escopo.md`](./matriz-permissoes-e-escopo.md)
- [`ponto-canonico-da-regra.md`](./ponto-canonico-da-regra.md)
- [`checklist-alteracao-sensivel.md`](./checklist-alteracao-sensivel.md), quando aplicavel
