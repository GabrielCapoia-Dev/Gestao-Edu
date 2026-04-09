# Matriz Minima de Testes

Esta matriz define o minimo esperado de cobertura por dominio para reduzir regressao operacional.

## Estado atual resumido

| Dominio | Cobertura atual observada | Risco atual |
| --- | --- | --- |
| Autenticacao e permissoes | sem testes dedicados | alto |
| Pedidos de manutencao | sem cobertura forte do fluxo principal | alto |
| Merenda, contratos e estoque | balanco e importacao de contrato cobertos parcialmente | medio-alto |
| Inventario escolar | melhor cobertura relativa entre os modulos novos | medio |
| Relatorios PDF | renderer e alguns controllers cobertos | medio |

## Minimo recomendado por dominio

| Dominio | Testes minimos |
| --- | --- |
| Autenticacao e permissoes | login manual aprovado e nao aprovado, login Google com dominio permitido e nao permitido, policy critica, escopo por escola ou setor |
| Pedidos de manutencao | criacao, assumir pedido, troca de status, historico, anexos, feedback, reabertura, notificacao |
| Merenda, contratos e estoque | importacao de contrato, reserva de saldo, entrega parcial, entrega total, cancelamento, baixa operacional, balanco |
| Inventario escolar | pedido interno, aprovacao parcial, romaneio, conferencia, entrada no inventario, balanco e relatorios |
| Relatorios e arquivos | download protegido, PDF/XLSX principais, falhas por permissao |

## Testes existentes relevantes

- `tests/Feature/Contratos/ContratoItemSpreadsheetServiceTest.php`
- `tests/Feature/Estoque/BalancoEstoqueTest.php`
- `tests/Feature/Inventario/InventarioPedidoServiceTest.php`
- `tests/Feature/Inventario/InventarioRelatorioControllerTest.php`
- `tests/Feature/Inventario/BalancoInventarioTest.php`
- `tests/Unit/Relatorios/RelatorioPdfRendererTest.php`

## Quando nao houver teste automatizado

Registrar no feature brief:

- perfil usado na validacao manual
- estado inicial do dado
- transicao principal testada
- efeito colateral confirmado
- risco residual aceito
