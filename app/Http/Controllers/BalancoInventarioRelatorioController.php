<?php

namespace App\Http\Controllers;

use App\Models\BalancoInventario;
use App\Services\Relatorios\BalancoInventarioRelatorioService;
use Illuminate\Support\Facades\Auth;

class BalancoInventarioRelatorioController extends Controller
{
    public function __construct(
        protected BalancoInventarioRelatorioService $service,
    ) {}

    public function exportarPdf(BalancoInventario $balanco)
    {
        $this->autorizarVisualizacao($balanco);
        $this->prepararExecucao();

        return $this->service->gerarPdf($balanco, Auth::user());
    }

    protected function prepararExecucao(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);
    }

    protected function autorizarVisualizacao(BalancoInventario $balanco): void
    {
        $this->authorize('view', $balanco);
    }
}
