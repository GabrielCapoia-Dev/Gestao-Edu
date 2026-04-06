<?php

namespace App\Services\Relatorios;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

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
    ) {
        ini_set('memory_limit', '512M');

        $usuario = Auth::user();
        $view = $tipo === 'terceirizada'
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
        ini_set('memory_limit', '512M');

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
        ini_set('memory_limit', '512M');

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
        return $tipo === 'terceirizada'
            ? 'Relatorio de Avaliacao de Empresas Terceirizadas'
            : 'Relatorio de Feedback de Pedidos';
    }

    protected function subtituloPorTipo(string $tipo): ?string
    {
        return match ($tipo) {
            'listagem' => 'Listagem consolidada das avaliacoes registradas',
            'graficos' => 'Visao grafica e estatistica das avaliacoes',
            'terceirizada' => 'Consolidado de satisfacao por empresa terceirizada',
            default => 'Resumo geral com indicadores, listagem e analise visual',
        };
    }
}
