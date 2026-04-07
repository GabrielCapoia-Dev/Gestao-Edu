<?php

namespace App\Http\Controllers;

use App\Models\InventarioRomaneio;
use App\Services\Inventario\InventarioContextService;
use App\Services\Relatorios\InventarioRomaneioRelatorioService;
use Illuminate\Support\Facades\Auth;

class InventarioRomaneioController extends Controller
{
    public function __construct(
        protected InventarioRomaneioRelatorioService $service,
        protected InventarioContextService $contextService,
    ) {}

    public function exportarPdf(InventarioRomaneio $romaneio)
    {
        $user = Auth::user();

        abort_unless($user?->hasPermissionTo('Exportar Relatórios'), 403);
        abort_unless($this->contextService->ehGestorGeral($user), 403);

        ini_set('memory_limit', '512M');
        set_time_limit(180);

        return $this->service->gerarPdf($romaneio, $user);
    }
}
