<?php

use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\AvaliacaoDocumentoExportController;
use App\Http\Controllers\AvaliacaoRespostaAutosaveController;
use App\Http\Controllers\BaixasEstoqueRelatorioController;
use App\Http\Controllers\BalancoEstoqueRelatorioController;
use App\Http\Controllers\BalancoInventarioRelatorioController;
use App\Http\Controllers\CalendarExportController;
use App\Http\Controllers\EstoqueRelatorioController;
use App\Http\Controllers\EventoCalendarioLocalizacaoController;
use App\Http\Controllers\Exports\ExportRequestController;
use App\Http\Controllers\FeedbackPedidoExportController;
use App\Http\Controllers\InventarioRelatorioController;
use App\Http\Controllers\InventarioRomaneioController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\PedidoArquivoController;
use App\Http\Controllers\PedidoMerendaEmpenhoController;
use App\Http\Controllers\PedidoRelatorioController;
use App\Http\Controllers\PedidoRelatorioGeralController;
use App\Http\Controllers\ProfilePreviewController;
use App\Http\Controllers\ServidorDocumentoController;
use App\Http\Controllers\UserPresenceController;
use App\Http\Middleware\ApplyProfilePreviewUser;
use App\Http\Middleware\BlockProfilePreviewWrites;
use App\Http\Middleware\EnsurePasswordIsChanged;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('public.home');
Route::view('/politica-de-privacidade', 'public.privacy')->name('public.privacy');
Route::view('/termos-de-servico', 'public.terms')->name('public.terms');
Route::get('/pedidos/relatorio-geral', [PedidoRelatorioGeralController::class, 'exportar'])
    ->middleware('auth')
    ->name('pedidos.relatorio-geral');
Route::get('/pedidos/relatorio-listagem', [PedidoRelatorioGeralController::class, 'exportarListagem'])
    ->middleware('auth')
    ->name('pedidos.relatorio-listagem');

Route::get('/oauth/redirect/google', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/oauth/callback/google', [GoogleAuthController::class, 'callback'])->name('google.callback');
Route::post('/admin/login', [AdminLoginController::class, 'store'])
    ->middleware(['web', 'throttle:20,1'])
    ->name('admin.login.store');

Route::prefix('admin')
    ->middleware(['web', 'auth', ApplyProfilePreviewUser::class, BlockProfilePreviewWrites::class, EnsurePasswordIsChanged::class])
    ->group(function () {
        Route::post('/profile-preview/start', [ProfilePreviewController::class, 'start'])
            ->name('profile-preview.start');

        Route::post('/profile-preview/stop', [ProfilePreviewController::class, 'stop'])
            ->name('profile-preview.stop');

        Route::get('/alterar-senha-obrigatoria', [ForcePasswordChangeController::class, 'edit'])
            ->name('auth.force-password.edit');

        Route::post('/alterar-senha-obrigatoria/salvar', [ForcePasswordChangeController::class, 'update'])
            ->name('auth.force-password.update');

        Route::get('/notifications/center', [NotificationCenterController::class, 'index'])
            ->name('notifications.center');

        Route::get('/notifications/unread-count', [NotificationCenterController::class, 'unreadCount'])
            ->name('notifications.unreadCount');

        Route::post('/presence/heartbeat', [UserPresenceController::class, 'heartbeat'])
            ->name('presence.heartbeat');

        Route::post('/notifications/send', [NotificationCenterController::class, 'send'])
            ->name('notifications.send');

        Route::post('/notifications/mark-all-read', [NotificationCenterController::class, 'markAllRead'])
            ->name('notifications.markAllRead');

        Route::delete('/notifications/delete-all', [NotificationCenterController::class, 'deleteAll'])
            ->name('notifications.deleteAll');

        Route::post('/notifications/{id}/mark-read', [NotificationCenterController::class, 'markRead'])
            ->name('notifications.markRead');

        Route::post('/notifications/{id}/mark-unread', [NotificationCenterController::class, 'markUnread'])
            ->name('notifications.markUnread');

        Route::delete('/notifications/{id}', [NotificationCenterController::class, 'delete'])
            ->name('notifications.delete');

        Route::get('/exports/{exportRequest}/download', [ExportRequestController::class, 'download'])
            ->name('exports.download')
            ->middleware('can:download,exportRequest');

        Route::get('/calendario/exportar', CalendarExportController::class)
            ->name('dashboard.calendar.export');

        Route::get('/servidores/{servidor}/historico.csv', [ServidorDocumentoController::class, 'historico'])
            ->whereNumber('servidor')->name('admin.servidores.historico.exportar');
        Route::get('/servidores/{servidor}/ficha.csv', [ServidorDocumentoController::class, 'ficha'])
            ->whereNumber('servidor')->name('admin.servidores.ficha.exportar');

        Route::get('/eventos-calendario/localizacoes', [EventoCalendarioLocalizacaoController::class, 'buscar'])
            ->middleware('throttle:30,1')->name('eventos-calendario.localizacoes.buscar');
        Route::get('/eventos-calendario/localizacoes/reverter', [EventoCalendarioLocalizacaoController::class, 'reverter'])
            ->middleware('throttle:30,1')->name('eventos-calendario.localizacoes.reverter');

        Route::post('/exports/{exportRequest}/cancel', [ExportRequestController::class, 'cancel'])
            ->name('exports.cancel')
            ->middleware('can:cancel,exportRequest');

        Route::get('/pedidos/{pedido}/pdf', PedidoRelatorioController::class)
            ->name('pedidos.pdf');

        Route::get('/pedidos/{pedido}/imagens.zip', [PedidoArquivoController::class, 'exportImages'])
            ->name('pedidos.imagens.export');

        Route::get('/pedidos-merenda/{pedidoMerenda}/empenho', [PedidoMerendaEmpenhoController::class, 'exportar'])
            ->name('pedidos-merenda.exportar-empenho');

        Route::get('/avaliacoes/documento/pdf', [AvaliacaoDocumentoExportController::class, 'exportar'])
            ->name('avaliacoes.documento.pdf');

        Route::get('/avaliacoes/documento/csv', [AvaliacaoDocumentoExportController::class, 'exportarCsv'])
            ->name('avaliacoes.documento.csv');

        Route::post('/avaliacoes/respostas/autosave', AvaliacaoRespostaAutosaveController::class)
            ->name('avaliacoes.respostas.autosave');

        Route::get(
            '/pedidos/arquivos/{pedidoArquivo}/download',
            [PedidoArquivoController::class, 'download']
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

Route::prefix('admin/feedback-pedidos')
    ->middleware(['auth', EnsurePasswordIsChanged::class])
    ->group(function () {

        // Relatorio geral (cards + graficos + matriz + tabela)
        Route::get('/exportar-pdf/geral', [FeedbackPedidoExportController::class, 'exportarGeral'])
            ->name('feedback-pedidos.export-geral');

        // Relatorio de listagem (apenas tabela)
        Route::get('/exportar-pdf/listagem', [FeedbackPedidoExportController::class, 'exportarListagem'])
            ->name('feedback-pedidos.export-listagem');

        // Relatorio de graficos (cards + graficos + matriz)
        Route::get('/exportar-pdf/graficos', [FeedbackPedidoExportController::class, 'exportarGraficos'])
            ->name('feedback-pedidos.export-graficos');

        // Relatorio de avaliacao de empresas terceirizadas
        Route::get('/exportar-pdf/terceirizada', [FeedbackPedidoExportController::class, 'exportarTerceirizada'])
            ->name('feedback-pedidos.export-terceirizada');
    });

require __DIR__.'/mobile.php';

Route::get('/baixar-app', function () {
    return redirect()->route('mobile.install');
})->name('mobile.install.short');
