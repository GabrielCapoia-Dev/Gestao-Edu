<?php

namespace App\Http\Controllers;

use App\Models\Estoque;
use App\Services\Relatorios\EstoqueRelatorioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EstoqueRelatorioController extends Controller
{
    public function __construct(
        protected EstoqueRelatorioService $service,
    ) {}

    public function exportarPdf(Request $request)
    {
        $this->autorizarExportacao();
        $this->prepararExecucao();

        return $this->service->gerarPdfGeral($request->all(), Auth::user());
    }

    public function exportarXlsx(Request $request)
    {
        $this->autorizarExportacao();
        $this->prepararExecucao();

        return $this->service->gerarXlsxGeral($request->all(), Auth::user());
    }

    public function exportarItemPdf(Estoque $estoque)
    {
        $this->autorizarExportacao();
        $this->prepararExecucao();

        return $this->service->gerarPdfItem($estoque, Auth::user());
    }

    public function exportarItemXlsx(Estoque $estoque)
    {
        $this->autorizarExportacao();
        $this->prepararExecucao();

        return $this->service->gerarXlsxItem($estoque, Auth::user());
    }

    protected function prepararExecucao(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);
    }

    protected function autorizarExportacao(): void
    {
        abort_unless(Auth::user()?->hasPermissionTo('Exportar Relatórios'), 403);
    }
}
