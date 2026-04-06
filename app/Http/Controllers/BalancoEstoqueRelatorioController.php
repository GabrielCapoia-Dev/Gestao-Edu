<?php

namespace App\Http\Controllers;

use App\Models\BalancoEstoque;
use App\Services\Relatorios\BalancoEstoqueRelatorioService;
use Illuminate\Support\Facades\Auth;

class BalancoEstoqueRelatorioController extends Controller
{
    public function __construct(
        protected BalancoEstoqueRelatorioService $service,
    ) {}

    public function exportarPdf(BalancoEstoque $balanco)
    {
        $this->autorizarVisualizacao();
        $this->prepararExecucao();

        return $this->service->gerarPdf($balanco, Auth::user());
    }

    protected function prepararExecucao(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);
    }

    protected function autorizarVisualizacao(): void
    {
        abort_unless(Auth::user()?->hasPermissionTo('Listar Balanços de Estoque'), 403);
    }
}
