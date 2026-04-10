<?php

use App\Http\Controllers\Mobile\AccessController;
use App\Http\Controllers\Mobile\AuthController;
use App\Http\Controllers\Mobile\HomeController;
use App\Http\Controllers\Mobile\ReportsController;
use App\Http\Middleware\AuthenticateMobile;
use Illuminate\Support\Facades\Route;

Route::prefix('app')
    ->name('mobile.')
    ->group(function (): void {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.store');
        Route::post('/logout', [AuthController::class, 'logout'])
            ->middleware(AuthenticateMobile::class)
            ->name('logout');

        Route::middleware(AuthenticateMobile::class)->group(function (): void {
            Route::get('/', [HomeController::class, 'index'])->name('home');
            Route::get('/inicio', fn () => redirect()->route('mobile.home'))->name('legacy.home');

            Route::get('/acesso', [AccessController::class, 'index'])->name('access.index');
            Route::get('/usuarios', [AccessController::class, 'users'])->name('users.index');
            Route::get('/dominio-emails', [AccessController::class, 'domains'])->name('domains.index');
            Route::get('/niveis-de-acesso', [AccessController::class, 'roles'])->name('roles.index');

            Route::get('/relatorios', [ReportsController::class, 'index'])->name('reports.index');
            Route::get('/relatorios-dashboard', [ReportsController::class, 'dashboard'])->name('reports.dashboard');
            Route::get('/relatorio-professor-componente-turma', [ReportsController::class, 'professorByClass'])->name('reports.professor-by-class');
            Route::get('/relatorio-componentes-com-professores-faltando', [ReportsController::class, 'missingTeachers'])->name('reports.missing-teachers');
        });
    });
