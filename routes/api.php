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

        Route::post('/cache/clear', [MaintenanceController::class, 'clearCache'])
            ->name('cache.clear');

        Route::post('/cache/warm', [MaintenanceController::class, 'warmCache'])
            ->name('cache.warm');

        Route::post('/permissions/sync', [MaintenanceController::class, 'syncPermissions'])
            ->name('permissions.sync');

        Route::post('/migrate', [MaintenanceController::class, 'migrate'])
            ->name('migrate');

        Route::get('/info', [MaintenanceController::class, 'info'])
            ->name('info');

        Route::get('/health', [MaintenanceController::class, 'health'])
            ->name('health');

        Route::get('/commands', [MaintenanceController::class, 'commands'])
            ->name('commands');

        Route::post('/commands/{key}', [MaintenanceController::class, 'runCommand'])
            ->name('commands.run');

        Route::get('/queue/status', [MaintenanceController::class, 'queueStatus'])
            ->name('queue.status');

        Route::post('/queue/run', [MaintenanceController::class, 'runQueue'])
            ->name('queue.run');

        Route::post('/queue/retry-failed', [MaintenanceController::class, 'retryFailedQueueJobs'])
            ->name('queue.retry-failed');

        Route::post('/schedule/run', [MaintenanceController::class, 'runSchedule'])
            ->name('schedule.run');

        Route::post('/storage/link', [MaintenanceController::class, 'linkStorage'])
            ->name('storage.link');

        Route::post('/exports/prune', [MaintenanceController::class, 'pruneExports'])
            ->name('exports.prune');
    });
