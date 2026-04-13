# Matriz de Permissoes e Escopo

Este documento explica como o acesso e montado no projeto. A regra pratica e: quase nunca existe uma unica camada resolvendo tudo.

## Camadas de acesso atuais

| Camada | Responsabilidade | Fonte principal |
| --- | --- | --- |
| Autenticacao | identificar o usuario e abrir sessao | Filament `admin`, `LoginPage`, Google OAuth |
| Gate de painel | permitir ou negar acesso ao painel depois do login | `User::canAccessPanel()` |
| Capacidade ampla | dizer se o usuario pode listar, criar, editar ou excluir um recurso | Spatie Permission e policies |
| Escopo contextual | limitar quais registros o usuario realmente ve ou gerencia | queries, services e `id_escola` ou `setor` |
| Guardas de rota | proteger downloads e endpoints especificos | middleware `can:` e auth |

## Matriz resumida por exemplo

| Fluxo | Permissao principal | Policy | Escopo adicional | Observacao |
| --- | --- | --- | --- | --- |
| Painel admin | usuario autenticado | nao se aplica | `email_approved = true` | acesso ao painel pode ser negado mesmo apos login |
| Usuarios | `Listar Usuarios`, `Criar Usuarios`, `Editar Usuarios`, `Excluir Usuarios` | `UserPolicy` | pode haver restricoes de tela e fluxo operacional | policy cobre capacidade ampla |
| Pedidos de manutencao | `Listar Pedidos`, `Criar Pedidos`, `Editar Pedidos` | `PedidoPolicy` | `PedidoService` filtra por `id_escola`, `setor` e perfil | policy sozinha nao define visibilidade real |
| Pedidos de merenda | `Listar Pedidos: Merenda`, `Criar Pedidos: Merenda`, `Editar Pedidos: Merenda` | `PedidoMerendaPolicy` | sem escopo por escola no fluxo atual | risco ao evoluir inventario escolar |
| Estoque matriz | permissoes de gestao e balanco | geralmente direta na tela/acao | sem `id_escola` hoje | fluxo centralizado na matriz |

## Regras de decisao para novas features

1. Defina a permissao funcional principal.
2. Defina se o recurso precisa de policy dedicada.
3. Defina o escopo real de leitura e escrita por `role`, `permission`, `id_escola` e `setor`.
4. Proteja downloads ou endpoints fora do Filament com middleware explicito.
5. Valide com pelo menos um perfil amplo e um perfil restrito.

## Anti-padroes a evitar

- confiar somente na policy quando o modulo depende de filtro por escola ou setor
- esconder acao na UI sem proteger query, policy ou rota
- adicionar permissao nova em um ponto e esquecer seeders, role sync ou documentacao
- criar nomes novos de permissao sem revisar padrao atual de vocabulario

## Checklist minimo

- a permissao existe no catalogo?
- a policy responde a capacidade ampla correta?
- a query restringe registros quando necessario?
- rotas externas ao Filament estao protegidas?
- a documentacao do dominio foi atualizada?
