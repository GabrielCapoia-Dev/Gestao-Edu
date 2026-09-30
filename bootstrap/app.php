<?php

use App\Http\Middleware\EnforceAbsoluteSessionLifetime;
use App\Http\Middleware\NormalizeSessionCookieDomain;
use App\Http\Middleware\PerformanceInstrumentation;
use App\Http\Middleware\RefreshExportSession;
use App\Http\Middleware\ValidaUser;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'valida.user' => ValidaUser::class,
        ]);

        $middleware->prependToGroup('web', NormalizeSessionCookieDomain::class);
        $middleware->appendToGroup('web', PerformanceInstrumentation::class);
        $middleware->appendToGroup('web', EnforceAbsoluteSessionLifetime::class);
        $middleware->appendToGroup('web', RefreshExportSession::class);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule
            ->command('app:notificar-pedidos-atrasados')
            ->hourly()
            ->withoutOverlapping(120);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule
            ->command('eventos-calendario:rejeitar-expirados')
            ->twiceDailyAt(8, 13)
            ->withoutOverlapping(5);
    })
    ->withSchedule(function ($schedule) {
        $schedule->command('app:notificar-pedidos-a-vencer')
            ->dailyAt('08:00')
            ->withoutOverlapping(120);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule
            ->command('app:notificar-balancos-estoque-vencidos')
            ->hourly()
            ->withoutOverlapping(60);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule
            ->command('app:notificar-alunos-pendentes-transferencia')
            ->dailyAt('08:00')
            ->withoutOverlapping(120);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule
            ->command('exports:prune')
            ->hourly()
            ->withoutOverlapping(120);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule
            ->command('exports:monitor-stalled')
            ->everyFiveMinutes()
            ->withoutOverlapping(30);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule
            ->command('exports:recover-queued --limit=1000')
            ->everyFiveMinutes()
            ->withoutOverlapping(5);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
