<?php

use App\Http\Controllers\RelatorioController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use App\Http\Controllers\LaudoArquivoController;
use App\Models\Pedido;
use App\Services\Relatorios\PedidoRelatorioService;
use App\Models\User;
use App\Notifications\SistemaNotification;

Route::get('/', function () {
    return view('home');
});

Route::get('/test', function () {
    $user = User::find(1);
    $user->notify(
        new SistemaNotification(
            titulo: 'Novo Pedido',
            mensagem: 'Um novo pedido foi criado.',
            url: route('filament.admin.resources.pedidos.index')
        )
    );

    return view('test');
});

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
