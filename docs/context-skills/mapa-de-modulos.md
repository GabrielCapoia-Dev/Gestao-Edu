# Mapa de Modulos

## Objetivo

Dar um panorama rapido dos dominios do sistema e de onde cada responsabilidade principal vive.

## Onde isso vive no codigo

- `app/Models`
- `app/Filament/Admin/Resources`
- `app/Filament/Admin/Pages`
- `app/Services`
- `app/Policies`
- `app/Http/Controllers`

## Modulos principais

### Acesso e administracao

- `User`, `Role`, `Permission`, `DominioEmail`, `IgnoredUser`
- CRUDs e gestao via Filament em usuarios, roles e dominios
- Login manual e login Google

### Estrutura escolar e pedagogica

- `Escola`, `Setor`, `Serie`, `Turma`, `Professor`, `ComponenteCurricular`, `Servidor`, `FuncaoAdministrativa`
- Relacoes entre escola, serie, turma, professor e componente vivem em models e resources do Filament

### Manutencao predial

- `Pedido`, `TipoStatus`, `TipoManutencao`, `PedidoHistorico`, `PedidoArquivo`, `FeedbackPedido`, `EmpresaContratada`
- `PedidoService` concentra criacao, mudanca de status, avaliacao e historico
- Observer e commands completam notificacoes e comportamento assicrono

### Alimentacao escolar

- `Contrato`, `ContratoItem`, `Item`, `PedidoMerenda`, `PedidoMerendaItem`, `Estoque`, `EstoqueMovimentacao`
- Ha paginas e resources para contratos, pedidos, gestao de margens e estoque

### Relatorios e exportacoes

- Controllers e services em `app/Services/Relatorios`
- Views em `resources/views/relatorios`
- Relatorios misturam PDF, dashboards e paginas Filament

## Responsabilidades por camada

- Models: relacoes, casts, helpers e partes da regra
- Services: casos de uso relevantes, principalmente `PedidoService` e Google
- Policies: autorizacao por permissao e, em alguns casos, por escola
- Resources/Pages Filament: UX administrativa e parte importante da regra operacional
- Controllers: downloads, OAuth, exportacoes e endpoints auxiliares

## Riscos e cuidados

- O sistema nao esta separado em modulos isolados; os dominios convivem no mesmo monolito.
- Algumas regras importantes estao na interface administrativa, nao apenas na camada de dominio.
- `Pedido` e `PedidoMerenda` sao os centros de maior acoplamento funcional hoje.

## Quando consultar

- Antes de decidir em qual modulo implementar uma mudanca
- Para localizar rapidamente classes e arquivos relevantes
