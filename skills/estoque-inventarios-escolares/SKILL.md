---
name: estoque-inventarios-escolares
description: Use when working on stock, school inventory, internal supply orders, romaneios, or school-scoped food logistics in this repository. It guides how to map the existing matrix stock flow before changing inventory, reservations, balances, school permissions, or reports.
---

# Estoque, Inventarios e Pedidos Internos

Use esta skill antes de alterar o modulo de alimentacao escolar quando a tarefa envolver inventario por escola, reserva de saldo da matriz, pedidos internos ou romaneios.

## Fluxo recomendado

1. Leia primeiro:
   - `docs/context-skills/merenda-contratos-e-estoque.md`
   - `docs/context-skills/autenticacao-e-autorizacao.md`
   - `docs/context-skills/inventarios-escolares-e-pedidos-internos.md`
2. Confirme no codigo os pontos centrais:
   - `app/Models/Estoque.php`
   - `app/Services/Estoque/GestaoEstoqueDataService.php`
   - `app/Services/Estoque/BalancoEstoqueService.php`
   - `app/Services/PedidoService.php`
   - `app/Models/User.php`
3. Mapeie sempre estes eixos antes de editar:
   - saldo fisico da matriz
   - saldo reservado da matriz
   - saldo do inventario da escola
   - escopo do usuario por `id_escola`
   - exportacoes e historico de movimentacoes

## Regras que nao podem ser quebradas

- O estoque atual continua sendo a matriz logistica.
- Usuario com `id_escola` nao pode acessar inventario de outra escola.
- Reserva de matriz acontece no romaneio, nao na solicitacao.
- Conferencia com divergencia exige observacao.
- Baixa sempre precisa deixar rastreabilidade de saldo anterior e posterior.
- Balanco precisa bloquear movimentacoes do contexto em contagem.

## Estrategia de implementacao

- Prefira services transacionais para regras de aprovacao, romaneio e entrega.
- Prefira resources/pages do Filament ja alinhados com o projeto em vez de inventar um fluxo paralelo.
- Reaproveite o padrao de relatorios do modulo de estoque atual.
- Ao introduzir novos estados, use enums e um metodo de transicao central no service.

## Validacao minima

- Cobrir com teste o fluxo pedido -> aprovacao -> romaneio -> conferencia -> entrega.
- Cobrir com teste baixa e consumo de reserva na matriz.
- Cobrir com teste o escopo por escola para inventario e pedidos.
