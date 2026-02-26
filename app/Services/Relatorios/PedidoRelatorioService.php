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
        
        $nomeArquivo = str_replace(['/', '\\'], '-', $pedido->numero_protocolo);

        return response($dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=Pedido-{$nomeArquivo}.pdf");
    }
}
