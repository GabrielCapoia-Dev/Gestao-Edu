<?php

use App\Http\Controllers\MaintenanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('maintenance')
    ->name('maintenance.')
    ->middleware('throttle:10,1')
    ->group(function (): void {
        Route::post('/login', [MaintenanceController::class, 'login'])
            ->name('login');

        Route::post('/cache/rebuild', [MaintenanceController::class, 'rebuildCache'])
            ->name('cache.rebuild');

        Route::post('/permissions/sync', [MaintenanceController::class, 'syncPermissions'])
            ->name('permissions.sync');

        Route::post('/migrate', [MaintenanceController::class, 'migrate'])
            ->name('migrate');
    });
