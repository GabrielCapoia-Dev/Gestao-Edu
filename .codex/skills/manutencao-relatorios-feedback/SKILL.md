---
name: manutencao-relatorios-feedback
description: Use para analisar ou evoluir relatorios, PDFs, exportacoes, dashboards, graficos, filtros, anexos, fotos e feedbacks de pedidos de manutencao no Gestao-Edu.
---

# Manutencao Relatorios e Feedbacks

Use esta skill antes de alterar PDFs, exportacoes, filtros, dashboards, graficos, feedbacks, avaliacao por problema ou exibicao de anexos dos pedidos de manutencao.

## Objetivo

Preservar consistencia entre manutencao operacional e saidas de relatorio em:

- PDF do pedido e relatorio geral
- feedback agregado e feedback por problema
- pedidos principais e adicionais
- fotos, anexos e evidencias de conclusao
- dashboards, graficos e filtros
- exportacoes sincronas ou em fila

## Sequencia recomendada

1. Ler [`docs/context-skills/fluxo-pedidos-manutencao.md`](../../../docs/context-skills/fluxo-pedidos-manutencao.md).
2. Ler [`docs/context-skills/regras-criticas-pedidos.md`](../../../docs/context-skills/regras-criticas-pedidos.md).
3. Ler [`docs/context-skills/padrao-relatorios-pdf.md`](../../../docs/context-skills/padrao-relatorios-pdf.md).
4. Se a saida depender de permissao ou escopo, acionar `$gestao-edu-acesso-permissoes-flow`.
5. Se a saida usar `ExportRequest` ou fila, acionar `$gestao-edu-exportacoes-flow`.
6. Confirmar models e services:
   - `app/Models/FeedbackPedido.php`
   - `app/Models/FeedbackPedidoItem.php`
   - `app/Models/PedidoArquivo.php`
   - `app/Services/Relatorios/PedidoRelatorioService.php`
   - `app/Services/Relatorios/PedidoRelatorioGeralService.php`
   - `app/Services/Relatorios/FeedbackPedidoRelatorioService.php`
   - `app/Services/Relatorios/FeedbackPedidoAnalyticsService.php`
   - `app/Services/Relatorios/FeedbackGraficoService.php`
   - `app/Services/Relatorios/RelatorioPdfRenderer.php`
7. Revisar controllers, pages e views:
   - `app/Http/Controllers/PedidoRelatorioGeralController.php`
   - `app/Http/Controllers/FeedbackPedidoExportController.php`
   - `app/Filament/Admin/Pages/FeedbackPedido.php`
   - `resources/views/relatorios/Manutencao/pedido.blade.php`
   - `resources/views/relatorios/Manutencao/geral-pedidos.blade.php`
   - `resources/views/relatorios/FeedbackPedidos/relatorio.blade.php`
   - `resources/views/relatorios/FeedbackPedidos/relatorio-terceirizada.blade.php`

## Checklist de analise

- Confirmar se feedback representa problemas do pedido principal e dos adicionais.
- Confirmar se filtros de periodo, tipo, opcao, resultado, prioridade, escola e setor batem com as queries do painel.
- Confirmar se relatorios preservam protocolo, escola, setor, tipo, problemas, empresa, datas, status e resultado.
- Para Dompdf, preferir `Storage::disk('public')` com conteudo em base64/data URI para imagens locais.
- Separar grade de imagens de tabela de arquivos nao-imagem quando houver anexos variados.
- Confirmar que relationships necessarios foram carregados no service, nao apenas usados na view.
- Preservar o contrato de `ExportFileResult`, nome de arquivo, disk e autorizacao do download.

## Saida esperada

Ao final da analise, registrar:

- relatorios, exports e views afetados
- filtros e queries compartilhadas
- impacto em feedbacks, graficos e pedidos adicionais
- regra de exibicao de fotos e arquivos
- testes focados e verificacao manual de PDF/exportacao
