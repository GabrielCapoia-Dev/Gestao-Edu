<?php

namespace App\Services\Relatorios;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class FeedbackPedidoRelatorioService
{
    public function __construct(
        protected RelatorioPdfRenderer $renderer,
    ) {}

    /**
     * Metodo com graficos, matrizes e tipo de relatorio.
     */
    public function gerarComGraficosEMatriz(
        float $mediaGeral,
        int $totalAvaliacoes,
        int $percentualSatisfacao,
        Collection $feedbacks,
        ?string $graficoMediaMensal = null,
        ?string $graficoPorNota = null,
        array $matrizNotasPorMes = [],
        array $matrizesAgrupadas = [],
        string $tipo = 'geral',
        array $matrizesEmpresa = [],
        array $reportFilters = [],
        ?User $usuarioExportacao = null,
        array $rankingEmpresas = [],
        array $rankingEscolas = [],
        array $distribuicaoNotas = [],
    ) {
        $usuario = $usuarioExportacao ?? Auth::user();
        $view = $tipo === FeedbackPedidoAnalyticsService::REPORT_EMPRESAS || $tipo === 'terceirizada'
            ? 'relatorios.FeedbackPedidos.relatorio-terceirizada'
            : 'relatorios.FeedbackPedidos.relatorio';

        return $this->renderer->stream($view, [
            'mediaGeral' => $mediaGeral,
            'totalAvaliacoes' => $totalAvaliacoes,
            'percentualSatisfacao' => $percentualSatisfacao,
            'feedbacks' => $feedbacks,
            'usuarioExportacao' => $usuario,
            'dataExportacao' => now(),
            'graficoMediaMensal' => $graficoMediaMensal,
            'graficoPorNota' => $graficoPorNota,
            'matrizNotasPorMes' => $matrizNotasPorMes,
            'matrizesAgrupadas' => $matrizesAgrupadas,
            'tipoRelatorio' => $tipo,
            'matrizesEmpresa' => $matrizesEmpresa,
            'reportTitle' => $this->tituloPorTipo($tipo),
            'reportSubtitle' => $this->subtituloPorTipo($tipo),
            'reportFilters' => $reportFilters,
            'rankingEmpresas' => $rankingEmpresas,
            'rankingEscolas' => $rankingEscolas,
            'distribuicaoNotas' => $distribuicaoNotas,
        ], 'Feedback-Pedidos-' . now()->format('d-m-Y-His') . '.pdf');
    }

    /**
     * Metodo com graficos.
     */
    public function gerarComGraficos(
        float $mediaGeral,
        int $totalAvaliacoes,
        int $percentualSatisfacao,
        Collection $feedbacks,
        ?string $graficoMediaMensal = null,
        ?string $graficoPorNota = null,
        array $reportFilters = [],
    ) {
        return $this->renderer->stream('relatorios.FeedbackPedidos.relatorio', [
            'mediaGeral' => $mediaGeral,
            'totalAvaliacoes' => $totalAvaliacoes,
            'percentualSatisfacao' => $percentualSatisfacao,
            'feedbacks' => $feedbacks,
            'usuarioExportacao' => Auth::user(),
            'dataExportacao' => now(),
            'graficoMediaMensal' => $graficoMediaMensal,
            'graficoPorNota' => $graficoPorNota,
            'matrizNotasPorMes' => [],
            'matrizesAgrupadas' => [],
            'tipoRelatorio' => 'graficos',
            'reportTitle' => $this->tituloPorTipo('graficos'),
            'reportSubtitle' => $this->subtituloPorTipo('graficos'),
            'reportFilters' => $reportFilters,
        ], 'Feedback-Pedidos-' . now()->format('d-m-Y-His') . '.pdf');
    }

    /**
     * Metodo original sem graficos.
     */
    public function gerar(
        float $mediaGeral,
        int $totalAvaliacoes,
        int $percentualSatisfacao,
        Collection $feedbacks,
        array $reportFilters = [],
    ) {
        return $this->renderer->stream('relatorios.FeedbackPedidos.relatorio', [
            'mediaGeral' => $mediaGeral,
            'totalAvaliacoes' => $totalAvaliacoes,
            'percentualSatisfacao' => $percentualSatisfacao,
            'feedbacks' => $feedbacks,
            'usuarioExportacao' => Auth::user(),
            'dataExportacao' => now(),
            'graficoMediaMensal' => null,
            'graficoPorNota' => null,
            'matrizNotasPorMes' => [],
            'matrizesAgrupadas' => [],
            'tipoRelatorio' => 'listagem',
            'reportTitle' => $this->tituloPorTipo('listagem'),
            'reportSubtitle' => $this->subtituloPorTipo('listagem'),
            'reportFilters' => $reportFilters,
        ], 'Feedback-Pedidos-' . now()->format('d-m-Y-His') . '.pdf');
    }

    protected function tituloPorTipo(string $tipo): string
    {
        return match ($tipo) {
            'terceirizada', FeedbackPedidoAnalyticsService::REPORT_EMPRESAS => 'Relatório de Desempenho das Empresas',
            FeedbackPedidoAnalyticsService::REPORT_ESCOLAS => 'Relatório de Satisfação das Escolas',
            FeedbackPedidoAnalyticsService::REPORT_LISTAGEM => 'Relatório Filtrado de Feedback de Pedidos',
            default => 'Relatório Geral de Satisfação dos Pedidos',
        };
    }

    protected function subtituloPorTipo(string $tipo): ?string
    {
        return match ($tipo) {
            'listagem', FeedbackPedidoAnalyticsService::REPORT_LISTAGEM => 'Listagem consolidada das avaliações registradas no período informado',
            'graficos', FeedbackPedidoAnalyticsService::REPORT_GERAL => 'Visão gráfica e estatística das avaliações no período informado',
            'terceirizada', FeedbackPedidoAnalyticsService::REPORT_EMPRESAS => 'Consolidado de satisfação por empresa contratada',
            FeedbackPedidoAnalyticsService::REPORT_ESCOLAS => 'Consolidado de satisfação por escola solicitante',
            default => 'Resumo geral com indicadores, listagem e análise visual',
        };
    }
}
