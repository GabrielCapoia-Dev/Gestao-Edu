<?php

namespace App\Http\Controllers;

use App\Models\PedidoMerenda;
use App\Services\Relatorios\PedidoMerendaEmpenhoExportService;
use Illuminate\Support\Facades\Auth;

class PedidoMerendaEmpenhoController extends Controller
{
    public function __construct(
        protected PedidoMerendaEmpenhoExportService $service,
    ) {}

    public function exportar(PedidoMerenda $pedidoMerenda)
    {
        abort_unless(Auth::user()?->can('view', $pedidoMerenda), 403);

        return $this->service->exportar($pedidoMerenda, Auth::user());
    }
}
