---
name: manutencao-relatorios-feedback
description: Use quando for preciso analisar ou evoluir relatorios, exportacoes, dashboards, graficos, filtros e feedbacks de pedidos de manutencao deste projeto, incluindo FeedbackPedido, FeedbackPedidoItem e views PDF.
---

# Manutencao: Relatorios e Feedbacks

Use esta skill antes de alterar PDFs, exportacoes, filtros, dashboards, graficos, feedbacks ou avaliacao por problema dos pedidos de manutencao.

## Objetivo

Preservar consistencia entre dados operacionais de manutencao, relatorios PDF, exportacoes, graficos e feedbacks vinculados ao pedido principal e seus adicionais.

## Fluxo recomendado

1. Leia primeiro:
   - `docs/context-skills/fluxo-pedidos-manutencao.md`
   - `docs/context-skills/regras-criticas-pedidos.md`
   - `docs/context-skills/padrao-relatorios-pdf.md`
   - `docs/context-skills/autenticacao-e-autorizacao.md`, se a saida depender de permissao ou escopo
2. Confirme no codigo os pontos centrais:
   - `app/Models/FeedbackPedido.php`
   - `app/Models/FeedbackPedidoItem.php`
   - `app/Services/Relatorios/PedidoRelatorioService.php`
   - `app/Services/Relatorios/PedidoRelatorioGeralService.php`
   - `app/Services/Relatorios/FeedbackPedidoRelatorioService.php`
   - `app/Services/Relatorios/FeedbackGraficoService.php`
   - `app/Http/Controllers/PedidoRelatorioGeralController.php`
   - `app/Http/Controllers/FeedbackPedidoExportController.php`
   - `app/Filament/Admin/Pages/FeedbackPedido.php`
3. Revise as views quando alterar formato visual ou campos:
   - `resources/views/relatorios/Manutencao/pedido.blade.php`
   - `resources/views/relatorios/Manutencao/geral-pedidos.blade.php`
   - `resources/views/relatorios/FeedbackPedidos/relatorio.blade.php`
   - `resources/views/relatorios/FeedbackPedidos/relatorio-terceirizada.blade.php`

## Regras sensiveis

- Feedback e itens precisam representar problemas do pedido principal e dos adicionais.
- Relatorios devem preservar protocolo, escola, setor, tipo, problemas, empresa, datas, status e resultado do atendimento quando aplicavel.
- Filtros de periodo, tipo, opcao, resultado, prioridade, escola e setor precisam bater com as queries do painel.
- Alterar nomes de status ou tipos pode impactar agrupamentos, graficos e exportacoes.
- Arquivos e fotos de conclusao podem ser parte da evidenciacao do atendimento.

## Estrategia de implementacao

- Reaproveite `RelatorioPdfRenderer` e services de relatorio existentes.
- Centralize query e filtros em service quando a mesma regra alimentar tela, PDF e exportacao.
- Evite duplicar calculos de graficos em controller e page; prefira service reutilizavel.
- Ao adicionar campo operacional, atualizar relatorio detalhado, relatorio geral, filtros e feedbacks quando fizer sentido.

## Validacao minima

- Cobrir filtros principais do relatorio geral quando a query mudar.
- Cobrir feedback por problema e pedidos adicionais quando a avaliacao mudar.
- Validar que views PDF recebem todos os dados esperados e nao dependem de relacionamento nao carregado.
- Fazer uma verificacao manual de PDF/exportacao se a mudanca tocar layout ou renderizacao.
