<?php

use App\Http\Controllers\RelatorioController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use App\Http\Controllers\LaudoArquivoController;

Route::get('/', function () {
    return view('home');
});

Route::get('/test', function () {
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
    });
