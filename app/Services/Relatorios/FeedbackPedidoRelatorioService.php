<?php

namespace App\Services\Relatorios;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;

class FeedbackPedidoRelatorioService
{
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
        ]);

        $nomeArquivo = 'Feedback-Pedidos-' . now()->format('d-m-Y-His');

        return $pdf->stream("{$nomeArquivo}.pdf");
    }

    /**
     * Método novo (com gráficos)
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
        ]);

        $nomeArquivo = 'Feedback-Pedidos-' . now()->format('d-m-Y-His');

        return $pdf->stream("{$nomeArquivo}.pdf");
    }
}