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
        $usuario = Auth::user();

        abort_unless(
            $usuario && $this->pedidoService->podeListarRegistro($pedido, $usuario),
            403,
        );

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
            'arquivos.usuario',
            'fotos',
            'fotosConclusao',
            'problemas',
            'pedidosAdicionais.tipoManutencao',
            'pedidosAdicionais.tipoStatus',
            'pedidosAdicionais.problemas',
            'pedidosAdicionais.arquivos.usuario',
            'pedidosAdicionais.feedbackItens.problema',
            'ultimoFeedback.itens.problema',
        ]);

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
            'reportTitle' => 'Relatório Técnico de Manutenção',
            'reportSubtitle' => "Protocolo {$pedido->numero_protocolo}",
        ], "Pedido-{$nomeArquivo}.pdf");
    }
}
