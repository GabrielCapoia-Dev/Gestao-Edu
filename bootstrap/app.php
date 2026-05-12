<?php

use App\Http\Middleware\ValidaUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'valida.user' => ValidaUser::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule
            ->command('app:notificar-pedidos-atrasados')->everyMinute();
    })
    ->withSchedule(function ($schedule) {
        $schedule->command('app:notificar-pedidos-a-vencer')
            ->dailyAt('08:00');
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule
            ->command('app:notificar-balancos-estoque-vencidos')
            ->hourly();
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule
            ->command('app:notificar-alunos-pendentes-transferencia')
            ->dailyAt('08:00');
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule
            ->command('exports:prune')
            ->dailyAt('02:30');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
