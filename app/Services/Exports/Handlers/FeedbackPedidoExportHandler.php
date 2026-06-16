<?php

namespace App\Services\Exports\Handlers;

use App\Contracts\Exports\ExportHandler;
use App\Models\ExportRequest;
use App\Services\Exports\ExportFileResult;
use App\Services\Exports\ExportFileStorage;
use App\Services\Relatorios\ChartRenderService;
use App\Services\Relatorios\FeedbackGraficoService;
use App\Services\Relatorios\FeedbackPedidoAnalyticsService;
use App\Services\Relatorios\FeedbackPedidoRelatorioService;
use RuntimeException;

class FeedbackPedidoExportHandler implements ExportHandler
{
    public function __construct(
        private readonly FeedbackPedidoAnalyticsService $analytics,
        private readonly FeedbackPedidoRelatorioService $relatorio,
        private readonly FeedbackGraficoService $graficos,
        private readonly ChartRenderService $chartRender,
        private readonly ExportFileStorage $storage,
    ) {}

    public function handle(ExportRequest $exportRequest): ExportFileResult
    {
        $user = $exportRequest->user;

        if (! $user) {
            throw new RuntimeException('Usuário da exportação não encontrado.');
        }

        $filters = $this->analytics->normalizeFilters($exportRequest->filters ?? []);
        $this->analytics->assertReportFilters($filters);

        $exportRequest->updateProgress(10, 100, 'Preparando feedbacks.');

        $query = $this->analytics->query($filters);
        $feedbacks = (clone $query)->orderByDesc('created_at')->get();
        $metrics = $this->analytics->metrics(clone $query);
        $reportType = $filters['report_type'] ?? FeedbackPedidoAnalyticsService::REPORT_GERAL;

        $exportRequest->updateProgress(45, 100, 'Montando indicadores.');

        $graficoMediaMensal = null;
        $graficoPorNota = null;

        if ($reportType !== FeedbackPedidoAnalyticsService::REPORT_LISTAGEM) {
            $monthlyConfig = $this->graficos->gerarChartConfig('media_mensal', $this->analytics->monthlyChartData($feedbacks));
            $noteConfig = $this->graficos->gerarChartConfig('por_nota', $this->analytics->noteChartData(clone $query));

            $graficoMediaMensal = $this->chartRender->renderizarGrafico($monthlyConfig, 700, 350)
                ?: $this->chartRender->renderizarGraficoLocal($monthlyConfig);
            $graficoPorNota = $this->chartRender->renderizarGrafico($noteConfig, 700, 350)
                ?: $this->chartRender->renderizarGraficoLocal($noteConfig);
        }

        $exportRequest->updateProgress(70, 100, 'Gerando PDF.');

        $response = $this->relatorio->gerarComGraficosEMatriz(
            mediaGeral: $metrics['media'],
            totalAvaliacoes: $metrics['total'],
            percentualSatisfacao: $metrics['satisfacao'],
            feedbacks: $feedbacks,
            graficoMediaMensal: $graficoMediaMensal,
            graficoPorNota: $graficoPorNota,
            matrizNotasPorMes: [],
            matrizesAgrupadas: $this->analytics->matrizNotasPorMes($feedbacks),
            tipo: $reportType,
            matrizesEmpresa: $this->analytics->matrizPorEmpresa($feedbacks),
            reportFilters: $this->analytics->formatFilters($filters),
            usuarioExportacao: $user,
            rankingEmpresas: $this->analytics->rankingEmpresas($feedbacks),
            rankingEscolas: $this->analytics->rankingEscolas($feedbacks),
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
