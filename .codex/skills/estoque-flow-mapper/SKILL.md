---
name: estoque-flow-mapper
description: Use para analisar ou evoluir contratos, itens, estoque central, merenda, inventarios escolares, balancos, baixas, pedidos internos, romaneios, relatorios e regras de saldo no Gestao-Edu.
---

# Estoque Flow Mapper

Use esta skill antes de mexer em contratos, estoque, merenda, inventarios escolares, balancos, pedidos internos ou romaneios.

## Objetivo

Mapear rapidamente o comportamento atual do estoque da matriz para evitar regressao em:

- entradas e saidas de estoque
- baixas operacionais
- balancos de estoque
- pedidos de merenda e seus reflexos em saldo
- relatórios e exportacoes
- escopo de acesso por escola ou por gestor geral

## Sequencia recomendada

1. Ler [`docs/context-skills/fluxo-estoque-atual.md`](../../../docs/context-skills/fluxo-estoque-atual.md).
2. Confirmar a modelagem principal:
   - `app/Models/Contrato.php`
   - `app/Models/ContratoItem.php`
   - `app/Models/Item.php`
   - `app/Models/Estoque.php`
   - `app/Models/EstoqueMovimentacao.php`
   - `app/Models/BaixasEstoques.php`
   - `app/Models/PedidoMerenda.php`
   - `app/Models/PedidoMerendaItem.php`
   - `app/Models/Inventario.php`
   - `app/Models/InventarioEstoque.php`
   - `app/Models/InventarioMovimentacao.php`
   - `app/Models/InventarioPedido.php`
   - `app/Models/InventarioRomaneio.php`
3. Revisar a orquestracao das telas e consultas:
   - `app/Filament/Admin/Pages/GestaoEstoque.php`
   - `app/Services/Estoque/GestaoEstoqueDataService.php`
   - `app/Filament/Admin/Resources/BaixasEstoque`
   - `app/Filament/Admin/Resources/BalancosEstoque`
   - `app/Filament/Admin/Pages/GestaoInventario.php`
   - `app/Services/Inventario/InventarioDataService.php`
   - `app/Services/Inventario/InventarioPedidoService.php`
   - `app/Services/Inventario/BalancoInventarioService.php`
4. Validar exportacoes e rotas:
   - `app/Services/Relatorios/EstoqueRelatorioService.php`
   - `app/Services/Relatorios/InventarioRelatorioService.php`
   - `app/Services/Relatorios/InventarioRomaneioRelatorioService.php`
   - `app/Http/Controllers/EstoqueRelatorioController.php`
   - `routes/web.php`
5. Se tocar exportacao em fila, acionar `$gestao-edu-exportacoes-flow`.
6. Se a mudanca tocar fluxo escola -> gestor geral, revisar tambem:
   - `app/Models/User.php`
   - `app/Services/PedidoService.php`
   - `app/Console/Commands/CriarPermissoes.php`

## Checklist de analise

- Identificar onde o saldo fisico e alterado.
- Identificar onde historico e rastreado.
- Confirmar se existe bloqueio por balanco em andamento.
- Separar rigorosamente saldo do estoque central de saldo do inventario escolar.
- Preservar eventos e snapshots de balanco; nao recalcular historico como se fosse estado atual.
- Confirmar se a permissao depende de role, permissao Spatie, `id_escola` ou combinacao disso.
- Listar impactos em PDF/XLSX antes de mudar colunas ou nomes.
- Verificar se a regra nova precisa considerar `quantidade_reservada`, `saldo_disponivel` ou aprovacao parcial.

## Saida esperada

Ao final da analise, registrar:

- entidades e tabelas afetadas
- regras de negocio atuais
- pontos de extensao seguros
- riscos de regressao
- testes que precisam ser criados ou atualizados
