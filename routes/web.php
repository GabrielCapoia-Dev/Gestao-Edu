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
use Illuminate\Http\Request;
use App\Services\Relatorios\FeedbackPedidoRelatorioService;
use App\Services\Relatorios\FeedbackGraficoService;
use App\Services\Relatorios\ChartRenderService;
use Illuminate\Support\Facades\Auth;

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
    ini_set('memory_limit', '512M');

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
        $graficoService = app(FeedbackGraficoService::class);
        $chartRender = app(ChartRenderService::class);

        // Dados dos gráficos
        $dadosMediaMensal = $graficoService->gerarDadosMediaMensal($filters);
        $dadosPorNota = $graficoService->gerarDadosPorNota($filters);

        // Configurações Chart.js
        $configMediaMensal = $graficoService->gerarChartConfig('media_mensal', $dadosMediaMensal);
        $configPorNota = $graficoService->gerarChartConfig('por_nota', $dadosPorNota);

        // Renderizar gráficos
        $graficoMediaMensal = $chartRender->renderizarGrafico($configMediaMensal, 700, 350);
        $graficoPorNota = $chartRender->renderizarGrafico($configPorNota, 700, 350);

        // Se falhar, usar renderização local
        if (!$graficoMediaMensal) {
            $graficoMediaMensal = $chartRender->renderizarGraficoLocal($configMediaMensal);
        }
        if (!$graficoPorNota) {
            $graficoPorNota = $chartRender->renderizarGraficoLocal($configPorNota);
        }

        // ===== GERAR MATRIZ - TODOS OS ANOS COM TODOS OS MESES DISPONÍVEIS =====
        $matrizesAgrupadas = [];
        
        if (!$feedbacks->isEmpty()) {
            $mesesLabels = [
                '01' => 'Janeiro',
                '02' => 'Fevereiro',
                '03' => 'Março',
                '04' => 'Abril',
                '05' => 'Maio',
                '06' => 'Junho',
                '07' => 'Julho',
                '08' => 'Agosto',
                '09' => 'Setembro',
                '10' => 'Outubro',
                '11' => 'Novembro',
                '12' => 'Dezembro',
            ];

            // Agrupar feedbacks por mês-ano
            $feedbacksAgrupados = $feedbacks
                ->groupBy(function ($item) {
                    return $item->created_at->format('Y-m');
                });

            // Separar por ano
            $porAno = [];
            foreach ($feedbacksAgrupados as $mesChave => $registros) {
                $ano = substr($mesChave, 0, 4);
                $mesNum = substr($mesChave, 5, 2);
                $mesLabel = $mesesLabels[$mesNum] . '/' . substr($mesChave, 2, 2);

                if (!isset($porAno[$ano])) {
                    $porAno[$ano] = [];
                }

                if (!isset($porAno[$ano][$mesLabel])) {
                    $porAno[$ano][$mesLabel] = [
                        'mes_numero' => (int)$mesNum,  // Adicionar número do mês para ordenação
                        1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0,
                    ];
                }

                foreach ($registros as $registro) {
                    $porAno[$ano][$mesLabel][$registro->valor]++;
                }
            }

            // Ordenar anos
            ksort($porAno);

            // Ordenar meses dentro de cada ano (por número do mês, não alfabeticamente)
            foreach ($porAno as $ano => &$meses) {
                uasort($meses, function ($a, $b) {
                    return $a['mes_numero'] <=> $b['mes_numero'];
                });
                
                // Remover o campo mes_numero após ordenação
                foreach ($meses as $mesLabel => &$dados) {
                    unset($dados['mes_numero']);
                }
            }

            // Atribuir à variável de matriz
            $matrizesAgrupadas = $porAno;
        }

        // Gerar PDF
        $service = app(FeedbackPedidoRelatorioService::class);
        
        return $service->gerarComGraficosEMatriz(
            $mediaGeral,
            $total,
            $percentualSatisfacao,
            $feedbacks,
            $graficoMediaMensal,
            $graficoPorNota,
            [],
            $matrizesAgrupadas
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
