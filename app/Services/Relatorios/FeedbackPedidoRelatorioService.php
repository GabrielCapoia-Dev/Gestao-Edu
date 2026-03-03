<?php

namespace App\Services\Relatorios;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;

class FeedbackPedidoRelatorioService
{
    /**
     * Método com gráficos, matrizes e tipo de relatório
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
        string $tipo = 'geral'
    ) {
        ini_set('memory_limit', '512M');

        $usuario = Auth::user();

        $pdf = Pdf::loadView('relatorios.FeedbackPedidos.relatorio', [
            'mediaGeral'            => $mediaGeral,
            'totalAvaliacoes'       => $totalAvaliacoes,
            'percentualSatisfacao'  => $percentualSatisfacao,
            'feedbacks'             => $feedbacks,
            'usuarioExportacao'     => $usuario,
            'dataExportacao'        => now(),
            'graficoMediaMensal'    => $graficoMediaMensal,
            'graficoPorNota'        => $graficoPorNota,
            'matrizNotasPorMes'     => $matrizNotasPorMes,
            'matrizesAgrupadas'     => $matrizesAgrupadas,
            'tipoRelatorio'         => $tipo,
        ]);

        $nomeArquivo = 'Feedback-Pedidos-' . now()->format('d-m-Y-His');

        return $pdf->stream("{$nomeArquivo}.pdf");
    }

    /**
     * Método com gráficos (sem matriz)
     */
    public function gerarComGraficos(
        float $mediaGeral,
        int $totalAvaliacoes,
        int $percentualSatisfacao,
        Collection $feedbacks,
        ?string $graficoMediaMensal = null,
        ?string $graficoPorNota = null
    ) {
        ini_set('memory_limit', '512M');

        $usuario = Auth::user();

        $pdf = Pdf::loadView('relatorios.FeedbackPedidos.relatorio', [
            'mediaGeral'            => $mediaGeral,
            'totalAvaliacoes'       => $totalAvaliacoes,
            'percentualSatisfacao'  => $percentualSatisfacao,
            'feedbacks'             => $feedbacks,
            'usuarioExportacao'     => $usuario,
            'dataExportacao'        => now(),
            'graficoMediaMensal'    => $graficoMediaMensal,
            'graficoPorNota'        => $graficoPorNota,
            'matrizNotasPorMes'     => [],
            'matrizesAgrupadas'     => [],
            'tipoRelatorio'         => 'geral',
        ]);

        $nomeArquivo = 'Feedback-Pedidos-' . now()->format('d-m-Y-His');

        return $pdf->stream("{$nomeArquivo}.pdf");
    }

    /**
     * Método original (sem gráficos)
     */
    public function gerar(
        float $mediaGeral,
        int $totalAvaliacoes,
        int $percentualSatisfacao,
        Collection $feedbacks
    ) {
        ini_set('memory_limit', '512M');

        $usuario = Auth::user();

        $pdf = Pdf::loadView('relatorios.FeedbackPedidos.relatorio', [
            'mediaGeral'            => $mediaGeral,
            'totalAvaliacoes'       => $totalAvaliacoes,
            'percentualSatisfacao'  => $percentualSatisfacao,
            'feedbacks'             => $feedbacks,
            'usuarioExportacao'     => $usuario,
            'dataExportacao'        => now(),
            'graficoMediaMensal'    => null,
            'graficoPorNota'        => null,
            'matrizNotasPorMes'     => [],
            'matrizesAgrupadas'     => [],
            'tipoRelatorio'         => 'geral',
        ]);

        $nomeArquivo = 'Feedback-Pedidos-' . now()->format('d-m-Y-His');

        return $pdf->stream("{$nomeArquivo}.pdf");
    }
}