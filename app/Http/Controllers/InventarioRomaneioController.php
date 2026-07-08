<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\InventarioRomaneio;
use App\Services\Relatorios\InventarioRomaneioRelatorioService;
use Illuminate\Support\Facades\Auth;

class InventarioRomaneioController extends Controller
{
    public function __construct(
        protected InventarioRomaneioRelatorioService $service,
    ) {}

    public function exportarPdf(InventarioRomaneio $romaneio)
    {
        $user = Auth::user();

        $this->authorize('exportRomaneio', Inventario::class);

        ini_set('memory_limit', '512M');
        set_time_limit(180);

        return $this->service->gerarPdf($romaneio, $user);
    }
}
