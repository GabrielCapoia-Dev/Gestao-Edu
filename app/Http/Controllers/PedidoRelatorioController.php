<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Services\Relatorios\PedidoRelatorioService;

class PedidoRelatorioController extends Controller
{
    public function __invoke(Pedido $pedido, PedidoRelatorioService $service)
    {
        $this->authorize('view', $pedido);

        return $service->gerar($pedido);
    }
}
