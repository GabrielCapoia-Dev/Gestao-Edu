<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Relatorios\RelatorioComponenteProfessorFaltando;
use App\Filament\Admin\Pages\Relatorios\RelatorioProfessorComponenteTurma;
use App\Filament\Admin\Pages\Relatorios\RelatoriosDashboard;
use App\Filament\Admin\Resources\DominioEmails\DominioEmailResource;
use App\Filament\Admin\Resources\Roles\RoleResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\Mobile\Pages\MobileHome;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class MobilePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('mobile')
            ->path('app')
            ->login()
            ->profile()
            ->darkMode(false)
            ->breadcrumbs(false)
            ->homeUrl(fn (): string => MobileHome::getUrl(panel: 'mobile'))
            ->colors([
                'primary' => [
                    50 => '#eef6fc',
                    100 => '#d8ecf7',
                    200 => '#a2c7ef',
                    300 => '#73abe7',
                    400 => '#458fdf',
                    500 => '#1a6bc7',
                    600 => '#17368d',
                    700 => '#0f2261',
                    800 => '#0a1a4a',
                    900 => '#071232',
                    950 => '#040b1e',
                ],
                'gray' => [
                    50 => '#f4f5f9',
                    100 => '#e8ebf2',
                    200 => '#d4d8e6',
                    300 => '#b4bace',
                    400 => '#9098b0',
                    500 => '#6b7694',
                    600 => '#47516e',
                    700 => '#2d3756',
                    800 => '#1a2340',
                    900 => '#0f1729',
                    950 => '#081124',
                ],
            ])
            ->brandLogo(fn () => view('components.logo-admin-do-sistema'))
            ->resources([
                UserResource::class,
                DominioEmailResource::class,
                RoleResource::class,
            ])
            ->pages([
                MobileHome::class,
                RelatoriosDashboard::class,
                RelatorioProfessorComponenteTurma::class,
                RelatorioComponenteProfessorFaltando::class,
            ])
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
