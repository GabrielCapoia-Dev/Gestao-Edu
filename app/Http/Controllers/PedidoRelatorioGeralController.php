<?php

namespace App\Http\Controllers;

use App\Services\Relatorios\PedidoRelatorioGeralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PedidoRelatorioGeralController extends Controller
{
    public function __construct(
        protected PedidoRelatorioGeralService $service
    ) {}

    public function exportar(Request $request)
    {
        // Precisa estar aqui — esta requisição é HTTP normal, não Livewire
        ini_set('memory_limit', '2048M');
        set_time_limit(180);

        try {
            $filtros = $request->only([
                'data_inicio',
                'data_fim',
                'escola_id',
                'tipo_status_id',
                'tipo_manutencao_id',
                'nivel_prioridade',
            ]);

            return $this->service->gerar($filtros, Auth::user());

        } catch (\Throwable $e) {
            return response('ERRO: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine(), 500);
        }
    }
}