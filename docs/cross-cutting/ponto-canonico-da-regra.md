# Ponto Canonico da Regra

Este documento define onde novas regras devem viver para reduzir duplicidade, inconsistencias e bugs de fluxo.

## Matriz de decisao

| Camada | Quando usar | Exemplos do projeto | Nao usar para |
| --- | --- | --- | --- |
| Model | invariantes do proprio agregado, helpers de estado, calculos derivados locais | `Estoque::entrada()`, `Estoque::saida()`, regras de saldo em agregados | orquestracao de varios modelos, autorizacao ampla |
| Service | caso de uso com varios modelos, transacao, fluxo operacional, query por perfil | `PedidoService`, `BalancoEstoqueService`, `GestaoEstoqueDataService` | validacao puramente visual |
| Policy | autorizacao ampla do recurso | `PedidoPolicy`, `PedidoMerendaPolicy`, `UserPolicy` | filtro completo por escola ou setor quando a query tambem precisa restringir |
| Observer | efeito colateral apos persistencia | `PedidoObserver` | regra principal de dominio ou ramificacao critica dificil de seguir |
| Resource/Page/Action do Filament | experiencia da tela, montagem de formulario, acao de interface e delegacao | tabelas, actions, schemas, dashboards | unica fonte da regra de negocio |
| Controller | endpoints HTTP, downloads, OAuth, exportacoes | controladores de relatorio e Google OAuth | regra operacional central do dominio |

## Regras praticas

- Se a regra precisa valer em mais de um ponto de entrada, ela nao deve ficar apenas na UI do Filament.
- Se a regra muda mais de uma entidade ou exige transacao, prefira service.
- Se a regra e autorizacao ampla, use policy.
- Se a regra e visibilidade por perfil, complemente policy com query ou service.
- Se a regra e apenas efeito colateral depois da mudanca, observer pode ser aceitavel.

## Checklist antes de implementar

- esta regra precisa existir fora da tela atual?
- ela mexe em mais de um model?
- depende de perfil, escola ou setor?
- gera historico, notificacao, arquivo ou exportacao?
- outro modulo pode chamar o mesmo comportamento?

## Anti-padroes a evitar

- service vazio com regra duplicada na action do Filament
- policy fazendo papel de query complexa
- model disparando muitos efeitos colaterais externos
- observer escondendo a regra principal de negocio
