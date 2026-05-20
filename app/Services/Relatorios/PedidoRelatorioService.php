<?php

namespace App\Services\Relatorios;

use App\Models\Pedido;
use App\Services\PedidoService;
use Illuminate\Support\Facades\Auth;

class PedidoRelatorioService
{
    public function __construct(
        protected RelatorioPdfRenderer $renderer,
        protected PedidoService $pedidoService,
    ) {}

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
            'fotos',
            'fotosConclusao',
            'problemas',
            'pedidosAdicionais.tipoManutencao',
            'pedidosAdicionais.tipoStatus',
            'pedidosAdicionais.problemas',
            'ultimoFeedback.itens.problema',
        ]);

        $usuario = Auth::user();
        $nomeUsuario = $usuario?->name ?? 'Sistema';

        $this->pedidoService->registrarHistorico(
            $pedido,
            $pedido->tipo_status_id,
            $pedido->tipo_status_id,
            $usuario,
            "PDF do pedido exportado por {$nomeUsuario}"
        );

        $nomeArquivo = str_replace(['/', '\\'], '-', $pedido->numero_protocolo);

        return $this->renderer->stream('relatorios.Manutencao.pedido', [
            'pedido' => $pedido,
            'usuarioExportacao' => $usuario,
            'dataExportacao' => now(),
            'reportTitle' => 'Relatorio Tecnico de Manutencao',
            'reportSubtitle' => "Protocolo {$pedido->numero_protocolo}",
        ], "Pedido-{$nomeArquivo}.pdf");
    }
}
