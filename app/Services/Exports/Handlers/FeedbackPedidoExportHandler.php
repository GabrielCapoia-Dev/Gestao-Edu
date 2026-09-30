<?php

namespace App\Services\Exports\Handlers;

use App\Contracts\Exports\ExportHandler;
use App\Exceptions\Exports\ExportPermanentException;
use App\Models\ExportRequest;
use App\Services\Exports\ExportFileResult;
use App\Services\Exports\ExportFileStorage;
use App\Services\Exports\ChunkedPdfExportService;
use App\Services\Relatorios\ChartRenderService;
use App\Services\Relatorios\FeedbackGraficoService;
use App\Services\Relatorios\FeedbackPedidoAnalyticsService;
use App\Services\Relatorios\FeedbackPedidoRelatorioService;

class FeedbackPedidoExportHandler implements ExportHandler
{
    public function __construct(
        private readonly FeedbackPedidoAnalyticsService $analytics,
        private readonly FeedbackPedidoRelatorioService $relatorio,
        private readonly FeedbackGraficoService $graficos,
        private readonly ChartRenderService $chartRender,
        private readonly ExportFileStorage $storage,
        private readonly ChunkedPdfExportService $chunkedPdf,
    ) {}

    public function handle(ExportRequest $exportRequest): ExportFileResult
    {
        $user = $exportRequest->user;

        if (! $user) {
            throw new ExportPermanentException('Usuário da exportação não encontrado.');
        }

        $filters = $this->analytics->normalizeFilters($exportRequest->filters ?? []);
        $this->analytics->assertReportFilters($filters, $user);

        $exportRequest->updateProgress(10, 100, 'Preparando feedbacks.');

        $query = $this->analytics->query($filters, $user);
        $reportType = $filters['report_type'] ?? FeedbackPedidoAnalyticsService::REPORT_GERAL;

        if ($reportType === FeedbackPedidoAnalyticsService::REPORT_LISTAGEM) {
            $total = (clone $query)->count();
            $reportFilters = $this->analytics->formatFilters($filters);

            return $this->chunkedPdf->export(
                exportRequest: $exportRequest,
                query: (clone $query)->orderBy('id'),
                view: 'relatorios.FeedbackPedidos.listagem',
                mapper: fn (\App\Models\FeedbackPedido $feedback): array => $this->analytics->mapFeedbackListagem($feedback),
                viewData: fn (array $feedbacks): array => [
                    'feedbacks' => $feedbacks,
                    'totalFeedbacks' => $total,
                    'reportFilters' => $reportFilters,
                    'reportTitle' => 'Listagem de Feedbacks de Pedidos',
                    'reportSubtitle' => 'Avaliações detalhadas conforme os filtros selecionados',
                    'usuarioExportacao' => $user,
                    'dataExportacao' => now(),
                ],
                fileName: 'feedback-pedidos-listagem.pdf',
                zipFileName: 'feedback-pedidos-listagem-partes.zip',
                chunkIdColumn: 'feedback_pedidos.id',
            );
        }

        $metrics = $this->analytics->metrics(clone $query);

        $exportRequest->updateProgress(45, 100, 'Montando indicadores.');

        $graficoMediaMensal = null;
        $graficoPorNota = null;

        if ($reportType !== FeedbackPedidoAnalyticsService::REPORT_LISTAGEM) {
            $monthlyConfig = $this->graficos->gerarChartConfig('media_mensal', $this->analytics->monthlyChartDataQuery(clone $query));
            $noteConfig = $this->graficos->gerarChartConfig('por_nota', $this->analytics->noteChartData(clone $query));

            $graficoMediaMensal = $this->chartRender->renderizarGraficoLocal($monthlyConfig);
            $graficoPorNota = $this->chartRender->renderizarGraficoLocal($noteConfig);

            if (config('exports.feedback_external_charts', false)) {
                $graficoMediaMensal ??= $this->chartRender->renderizarGrafico($monthlyConfig, 700, 350);
                $graficoPorNota ??= $this->chartRender->renderizarGrafico($noteConfig, 700, 350);
            }
        }

        $exportRequest->updateProgress(70, 100, 'Gerando PDF.');

        $response = $this->relatorio->gerarComGraficosEMatriz(
            mediaGeral: $metrics['media'],
            totalAvaliacoes: $metrics['total'],
            percentualSatisfacao: $metrics['satisfacao'],
            feedbacks: new \Illuminate\Database\Eloquent\Collection(),
            graficoMediaMensal: $graficoMediaMensal,
            graficoPorNota: $graficoPorNota,
            matrizNotasPorMes: [],
            matrizesAgrupadas: $this->analytics->matrizNotasPorMesQuery(clone $query),
            tipo: $reportType,
            matrizesEmpresa: $this->analytics->matrizPorEmpresaQuery(clone $query),
            reportFilters: $this->analytics->formatFilters($filters),
            usuarioExportacao: $user,
            rankingEmpresas: $this->analytics->rankingEmpresasQuery(clone $query),
            rankingEscolas: $this->analytics->rankingEscolasQuery(clone $query),
            distribuicaoNotas: $this->analytics->noteDistribution(clone $query),
        );

        $exportRequest->updateProgress(90, 100, 'Salvando PDF em armazenamento privado.');

        return $this->storage->storeResponse(
            $exportRequest,
            $response,
            'feedback-pedidos.pdf',
            'application/pdf',
        );
    }
}
