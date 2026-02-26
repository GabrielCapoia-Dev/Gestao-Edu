<?php

namespace App\Services\Relatorios;

use App\Models\Pedido;
use App\Services\PedidoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class PedidoRelatorioService
{
    public function gerar(Pedido $pedido)
    {
        $pedido->load([
            'tipoManutencao',
            'tipoStatus',
            'escola',
            'setor',
            'empresaContratada',
            'solicitante',
            'responsavel',
            'historicos.statusAnterior',
            'historicos.statusNovo',
            'historicos.usuario',
            'historicos.setor',
            'arquivos',
        ]);

        $usuario = Auth::user();

        app(PedidoService::class)->registrarHistorico(
            $pedido,
            $pedido->tipo_status_id,
            $pedido->tipo_status_id,
            $usuario,
            "PDF do pedido exportado por {$usuario->name}"
        );

        $pdf = Pdf::loadView('relatorios.Manutencao.pedido', [
            'pedido' => $pedido,
            'usuarioExportacao' => $usuario,
            'dataExportacao' => now(),
        ]);

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();


        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');

        $size = 8;
        $text = "Página {PAGE_NUM} de {PAGE_COUNT}";

        $width  = $canvas->get_width();
        $height = $canvas->get_height();

        /*
|--------------------------------------------------------------------------
| CONTROLE MANUAL
|--------------------------------------------------------------------------
| Ajuste esses valores até alinhar perfeito
*/
        $offsetLeft  = 45;   // move para direita (aumente)
        $offsetRight = 0;   // move para esquerda (aumente)
        $offsetY     = 20;  // distância da borda inferior

        /*
|--------------------------------------------------------------------------
| Cálculo base
|--------------------------------------------------------------------------
*/
        $textWidth = $fontMetrics->getTextWidth($text, $font, $size);

        /* Centro matemático */
        $centerX = ($width - $textWidth) / 2;

        /* Aplica ajustes manuais */
        $x = $centerX + $offsetLeft - $offsetRight;

        /* Altura */
        $fontHeight = $fontMetrics->getFontHeight($font, $size);
        $y = $height - $offsetY - $fontHeight;

        $canvas->page_text(
            $x,
            $y,
            $text,
            $font,
            $size,
            [0.42, 0.45, 0.50]
        );


        $nomeArquivo = str_replace(['/', '\\'], '-', $pedido->numero_protocolo);

        return $pdf->stream("Pedido-{$nomeArquivo}.pdf");
    }
}
