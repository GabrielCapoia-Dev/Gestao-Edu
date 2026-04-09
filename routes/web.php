<?php

use App\Http\Controllers\FeedbackPedidoExportController;
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
use \App\Http\Controllers\PedidoRelatorioGeralController;
use App\Http\Controllers\BalancoInventarioRelatorioController;
use App\Http\Controllers\BalancoEstoqueRelatorioController;
use App\Http\Controllers\BaixasEstoqueRelatorioController;
use App\Http\Controllers\EstoqueRelatorioController;
use App\Http\Controllers\InventarioRelatorioController;
use App\Http\Controllers\InventarioRomaneioController;
use App\Http\Controllers\PedidoMerendaEmpenhoController;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return redirect()->route('filament.admin.auth.login');
});
Route::get('/background', function () {
    return view('background-page');
});
Route::get('/exemplo', function () {
    return view('exemplo');
});

Route::get('/escopo', function () {
    return view('escopo-merenda');
});

Route::get('/403', function () {
    return view('errors.403');
});

Route::get('/404', function () {
    return view('errors.404');
});

Route::get('/419', function () {
    return view('errors.419');
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

Route::get('/pedidos/relatorio-geral', [PedidoRelatorioGeralController::class, 'exportar'])
    ->name('pedidos.relatorio-geral');


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


        Route::post('/notifications/mark-all-read', function () {
            DB::table('notifications')
                ->where('notifiable_id', Auth::id())
                ->where('notifiable_type', User::class)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return response()->json(['ok' => true]);
        })->name('notifications.markAllRead');

        Route::post('/notifications/{id}/mark-read', function ($id) {
            DB::table('notifications')
                ->where('id', $id)
                ->where('notifiable_id', Auth::id())
                ->where('notifiable_type', User::class)
                ->update(['read_at' => now()]);

            return response()->json(['ok' => true]);
        })->name('notifications.markRead');


        Route::get('/pedidos/{pedido}/pdf', function (Pedido $pedido, PedidoRelatorioService $service) {
            return $service->gerar($pedido);
        })->name('pedidos.pdf');

        Route::get('/pedidos-merenda/{pedidoMerenda}/empenho', [PedidoMerendaEmpenhoController::class, 'exportar'])
            ->name('pedidos-merenda.exportar-empenho');

        Route::get(
            '/pedidos/arquivos/{pedidoArquivo}/download',
            [\App\Http\Controllers\PedidoArquivoController::class, 'download']
        )
            ->name('pedidos.arquivos.download')
            ->middleware('can:download,pedidoArquivo');

        Route::get('/estoque/baixas/relatorio', [BaixasEstoqueRelatorioController::class, 'exportar'])
            ->name('baixas-estoque.relatorio');

        Route::get('/estoque/relatorio/pdf', [EstoqueRelatorioController::class, 'exportarPdf'])
            ->name('gestao-estoque.relatorio.pdf');

        Route::get('/estoque/relatorio/xlsx', [EstoqueRelatorioController::class, 'exportarXlsx'])
            ->name('gestao-estoque.relatorio.xlsx');

        Route::get('/estoque/{estoque}/relatorio/pdf', [EstoqueRelatorioController::class, 'exportarItemPdf'])
            ->name('gestao-estoque.item-relatorio.pdf');

        Route::get('/estoque/{estoque}/relatorio/xlsx', [EstoqueRelatorioController::class, 'exportarItemXlsx'])
            ->name('gestao-estoque.item-relatorio.xlsx');

        Route::get('/inventarios/{inventario}/relatorio/pdf', [InventarioRelatorioController::class, 'exportarPdf'])
            ->name('gestao-inventario.relatorio.pdf');

        Route::get('/inventarios/{inventario}/relatorio/xlsx', [InventarioRelatorioController::class, 'exportarXlsx'])
            ->name('gestao-inventario.relatorio.xlsx');

        Route::get('/inventarios/relatorio/envios/pdf', [InventarioRelatorioController::class, 'exportarRedePdf'])
            ->name('inventarios.relatorio.envios.pdf');

        Route::get('/inventarios/relatorio/envios/xlsx', [InventarioRelatorioController::class, 'exportarRedeXlsx'])
            ->name('inventarios.relatorio.envios.xlsx');

        Route::get('/inventario-estoques/{estoque}/relatorio/pdf', [InventarioRelatorioController::class, 'exportarItemPdf'])
            ->name('gestao-inventario.item-relatorio.pdf');

        Route::get('/inventario-estoques/{estoque}/relatorio/xlsx', [InventarioRelatorioController::class, 'exportarItemXlsx'])
            ->name('gestao-inventario.item-relatorio.xlsx');

        Route::get('/inventario-romaneios/{romaneio}/relatorio/pdf', [InventarioRomaneioController::class, 'exportarPdf'])
            ->name('inventario-romaneios.relatorio.pdf');

        Route::get('/balancos-inventario/{balanco}/relatorio/pdf', [BalancoInventarioRelatorioController::class, 'exportarPdf'])
            ->name('balancos-inventario.relatorio.pdf');

        Route::get('/balancos-estoque/{balanco}/relatorio/pdf', [BalancoEstoqueRelatorioController::class, 'exportarPdf'])
            ->name('balancos-estoque.relatorio.pdf');
    });









Route::post('/notifications/{id}/read', function ($id) {
    $user = User::find(1);

    $user->unreadNotifications()
        ->where('id', $id)
        ->first()
        ?->markAsRead();
});

Route::prefix('admin/feedback-pedidos')
    ->middleware(['auth'])
    ->group(function () {

        // Relatório Geral (cards + gráficos + matriz + tabela)
        Route::get('/exportar-pdf/geral', [FeedbackPedidoExportController::class, 'exportarGeral'])
            ->name('feedback-pedidos.export-geral');

        // Relatório de Listagem (apenas tabela)
        Route::get('/exportar-pdf/listagem', [FeedbackPedidoExportController::class, 'exportarListagem'])
            ->name('feedback-pedidos.export-listagem');

        // Relatório de Gráficos (cards + gráficos + matriz)
        Route::get('/exportar-pdf/graficos', [FeedbackPedidoExportController::class, 'exportarGraficos'])
            ->name('feedback-pedidos.export-graficos');


        // Relatório de Avaliação de Empresas Terceirizadas
        Route::get('/exportar-pdf/terceirizada', [FeedbackPedidoExportController::class, 'exportarTerceirizada'])
            ->name('feedback-pedidos.export-terceirizada');
    });
