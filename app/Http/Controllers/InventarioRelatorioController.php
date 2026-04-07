<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\InventarioEstoque;
use App\Services\Inventario\InventarioContextService;
use App\Services\Relatorios\InventarioRelatorioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventarioRelatorioController extends Controller
{
    public function __construct(
        protected InventarioRelatorioService $service,
        protected InventarioContextService $contextService,
    ) {}

    public function exportarPdf(Request $request, Inventario $inventario)
    {
        $this->autorizarExportacao($inventario);
        $this->prepararExecucao();

        return $this->service->gerarPdfGeral($inventario, $request->all(), Auth::user());
    }

    public function exportarXlsx(Request $request, Inventario $inventario)
    {
        $this->autorizarExportacao($inventario);
        $this->prepararExecucao();

        return $this->service->gerarXlsxGeral($inventario, $request->all(), Auth::user());
    }

    public function exportarItemPdf(InventarioEstoque $estoque)
    {
        $estoque->loadMissing('inventario');
        $this->autorizarExportacao($estoque->inventario);
        $this->prepararExecucao();

        return $this->service->gerarPdfItem($estoque, Auth::user());
    }

    public function exportarItemXlsx(InventarioEstoque $estoque)
    {
        $estoque->loadMissing('inventario');
        $this->autorizarExportacao($estoque->inventario);
        $this->prepararExecucao();

        return $this->service->gerarXlsxItem($estoque, Auth::user());
    }

    protected function prepararExecucao(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);
    }

    protected function autorizarExportacao(?Inventario $inventario): void
    {
        $user = Auth::user();

        abort_unless($user?->hasPermissionTo('Exportar Relatórios'), 403);
        abort_unless($inventario !== null, 404);

        if ($this->contextService->ehGestorGeral($user)) {
            return;
        }

        abort_unless((int) $inventario->escola_id === (int) $user?->id_escola, 403);
    }
}
