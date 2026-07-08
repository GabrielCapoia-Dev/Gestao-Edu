<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Relatorios\BaixasEstoqueRelatorioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BaixasEstoqueRelatorioController extends Controller
{
    public function __construct(
        protected BaixasEstoqueRelatorioService $service
    ) {}

    public function exportar(Request $request)
    {
        $this->authorize('exportReports', User::class);

        ini_set('memory_limit', '512M');
        set_time_limit(120);

        return $this->service->gerar($request->all(), Auth::user());
    }
}
