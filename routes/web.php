<?php

use App\Http\Controllers\RelatorioController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use App\Http\Controllers\LaudoArquivoController;
use App\Models\FeedbackPedido;
use App\Models\Pedido;
use App\Services\Relatorios\PedidoRelatorioService;
use App\Models\User;
use App\Notifications\SistemaNotification;
use App\Services\Relatorios\FeedbackPedidoRelatorioService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('home');
});

Route::get('/test', function () {
    return view('test');
});

Route::post('/test/notify', function () {
    $user = User::find(1);

    $user->notify(
        new SistemaNotification(
            titulo: 'Novo Pedido',
            mensagem: 'Um novo pedido foi criado.',
            url: route('filament.admin.resources.pedidos.index')
        )
    );

    return back()->with('success', 'Notificação enviada');
})->name('test.notify');
Route::get('/oauth/redirect/google', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/oauth/callback/google', [GoogleAuthController::class, 'callback'])->name('google.callback');

Route::prefix('admin')
    ->middleware(['web']) // sem 'auth' aqui
    ->group(function () {
        Route::get('/laudos/{alunoLaudo}', [LaudoArquivoController::class, 'show'])
            ->name('laudos.show')
            ->middleware('can:view,alunoLaudo'); // se não puder → 403

        Route::get('/laudos/{alunoLaudo}/download', [LaudoArquivoController::class, 'download'])
            ->name('laudos.download')
            ->middleware('can:download,alunoLaudo'); // se não puder → 403

        Route::get('/relatorios/ficha', [RelatorioController::class, 'ficha'])
            ->name('relatorios.ficha');

        Route::get('/relatorios/bulk-list', [RelatorioController::class, 'bulkList'])
            ->name('relatorios.bulkList');


        Route::get('/relatorios/bulk-ficha', [RelatorioController::class, 'bulkFicha'])
            ->name('relatorios.bulkFicha');

        Route::get(
            '/pedidos/arquivos/{pedidoArquivo}/download',
            [\App\Http\Controllers\PedidoArquivoController::class, 'download']
        )
            ->name('pedidos.arquivos.download')
            ->middleware('can:download,pedidoArquivo');

        Route::get('/pedidos/{pedido}/pdf', function (Pedido $pedido, PedidoRelatorioService $service) {
            return $service->gerar($pedido);
        })->name('pedidos.pdf');
    });

Route::post('/notifications/{id}/read', function ($id) {
    $user = User::find(1);

    $user->unreadNotifications()
        ->where('id', $id)
        ->first()
        ?->markAsRead();
});


Route::get('/admin/feedback-pedidos/exportar-pdf', function (Request $request) {
    ini_set('memory_limit', '1024M');

    try {
        /** @var \App\Models\User */
        $user = Auth::user();

        // Validar permissão
        if (!$user->hasPermissionTo('Visualizar Feedback de Pedidos')) {
            return abort(403, 'Sem permissão para exportar este relatório');
        }

        // Coletar filtros da query string
        $filters = [];
        if ($request->has('valor')) {
            $filters['valor'] = $request->input('valor');
        }
        if ($request->has('nivel_prioridade')) {
            $filters['nivel_prioridade'] = $request->input('nivel_prioridade');
        }
        if ($request->has('tipo_manutencao_id')) {
            $filters['tipo_manutencao_id'] = $request->input('tipo_manutencao_id');
        }
        if ($request->has('escola_id')) {
            $filters['escola_id'] = $request->input('escola_id');
        }
        if ($request->has('empresa_contratada_id')) {
            $filters['empresa_contratada_id'] = $request->input('empresa_contratada_id');
        }
        if ($request->has('mes')) {
            $filters['mes'] = $request->input('mes');
        }
        if ($request->has('data_inicio') && $request->has('data_fim')) {
            $filters['periodo'] = [
                'inicio' => $request->input('data_inicio'),
                'fim' => $request->input('data_fim'),
            ];
        }

        // Construir query com filtros
        $query = FeedbackPedido::with([
            'pedido.escola',
            'pedido.tipoManutencao',
        ]);

        // Aplicar filtros
        if (isset($filters['valor'])) {
            $query->where('valor', $filters['valor']);
        }
        if (isset($filters['nivel_prioridade'])) {
            $query->whereHas('pedido', fn($q) => $q->where('nivel_prioridade', $filters['nivel_prioridade']));
        }
        if (isset($filters['tipo_manutencao_id'])) {
            $query->whereHas('pedido', fn($q) => $q->where('tipo_manutencao_id', $filters['tipo_manutencao_id']));
        }
        if (isset($filters['escola_id'])) {
            $query->whereHas('pedido', fn($q) => $q->where('escola_id', $filters['escola_id']));
        }
        if (isset($filters['empresa_contratada_id'])) {
            $query->whereHas('pedido', fn($q) => $q->where('empresa_contratada_id', $filters['empresa_contratada_id']));
        }
        if (isset($filters['mes'])) {
            $query->whereMonth('created_at', $filters['mes']);
        }
        if (isset($filters['periodo'])) {
            if ($filters['periodo']['inicio']) {
                $query->whereDate('created_at', '>=', $filters['periodo']['inicio']);
            }
            if ($filters['periodo']['fim']) {
                $query->whereDate('created_at', '<=', $filters['periodo']['fim']);
            }
        }

        // Ordenar e coletar dados
        $feedbacks = $query->orderBy('created_at', 'desc')->get();

        // Calcular métricas
        $total = $feedbacks->count();
        $mediaGeral = $total > 0 ? round($feedbacks->avg('valor'), 2) : 0;
        $percentualSatisfacao = $total > 0
            ? round(($feedbacks->where('valor', '>=', 3)->count() / $total) * 100)
            : 0;

        // ===== GERAR GRÁFICOS =====
        $graficoService = app(\App\Services\Relatorios\FeedbackGraficoService::class);
        $chartRender = app(\App\Services\Relatorios\ChartRenderService::class);

        // Dados dos gráficos
        $dadosMediaMensal = $graficoService->gerarDadosMediaMensal($filters);
        $dadosPorNota = $graficoService->gerarDadosPorNota($filters);

        // Configurações Chart.js
        $configMediaMensal = $graficoService->gerarChartConfig('media_mensal', $dadosMediaMensal);
        $configPorNota = $graficoService->gerarChartConfig('por_nota', $dadosPorNota);

        // Renderizar gráficos (tenta API externa, se falhar usa local)
        $graficoMediaMensal = null;
        $graficoPorNota = null;

        // Tentar renderizar via QuickChart (melhor visual)
        $graficoMediaMensal = $chartRender->renderizarGrafico($configMediaMensal, 700, 350);
        $graficoPorNota = $chartRender->renderizarGrafico($configPorNota, 700, 350);

        // Se falhar, usar renderização local
        if (!$graficoMediaMensal) {
            $graficoMediaMensal = $chartRender->renderizarGraficoLocal($configMediaMensal);
        }
        if (!$graficoPorNota) {
            $graficoPorNota = $chartRender->renderizarGraficoLocal($configPorNota);
        }

        // Gerar PDF
        $service = app(FeedbackPedidoRelatorioService::class);

        return $service->gerarComGraficos(
            $mediaGeral,
            $total,
            $percentualSatisfacao,
            $feedbacks,
            $graficoMediaMensal,
            $graficoPorNota
        );
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Erro ao exportar PDF de Feedback', [
            'erro' => $e->getMessage(),
            'arquivo' => $e->getFile(),
            'linha' => $e->getLine(),
            'stack' => $e->getTraceAsString(),
        ]);

        if (config('app.debug')) {
            return response()->json([
                'erro' => $e->getMessage(),
                'arquivo' => $e->getFile(),
                'linha' => $e->getLine(),
            ], 500);
        }

        return abort(500, 'Erro ao gerar PDF');
    }
})->middleware(['auth'])
    ->name('feedback-pedidos.export-pdf');
