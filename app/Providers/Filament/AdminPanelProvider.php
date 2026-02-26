<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Hasnayeen\Themes\ThemesPlugin;
use Rmsramos\Activitylog\ActivitylogPlugin;
use Filament\Http\Middleware\Authenticate;
use App\Livewire\LoginPage;
use App\Services\UserService;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LaudoArquivoController;
use Filament\Actions\Action as GlobalAction;
use Filament\Navigation\MenuItem;
use App\Models\User;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

class AdminPanelProvider extends PanelProvider
{

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->routes(function () {})
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(LoginPage::class)
            ->spa()
            ->colors([
                'primary' => Color::Green,
                'gray' => [
                    50 => '#e5eaf1ff',
                    100 => '#c7def8c7',
                    200 => '#c0d4d4ff',
                    300 => '#c7caccff',
                    400 => '#a0a0a0ff',
                    500 => '#929292ff',
                    600 => '#074f9bff',
                    700 => '#074f9b29',
                    800 => '#151D2Fff',
                    900 => '#081124ff',
                    950 => '#081124ff',
                ],
            ])
            ->brandLogo(fn() => view('components.logo-admin-do-sistema'))
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
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
                \Hasnayeen\Themes\Http\Middleware\SetTheme::class
            ])
            ->tenantMiddleware([
                \Hasnayeen\Themes\Http\Middleware\SetTheme::class
            ])
            ->authMiddleware([
                Authenticate::class,
            ])

            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn() => view('components.topbar-notifications')
            )
            ->userMenuItems([
                // O logout já vem por padrão, mas você pode customizar
                'logout' => MenuItem::make()
                    ->label('Sair'),
            ])
            ->plugins([

                ThemesPlugin::make()
                    ->canViewThemesPage(fn() => false),

                ActivitylogPlugin::make()
                    ->label('Registro de Atividade')
                    ->pluralLabel('Registro de Atividades')
                    ->navigationGroup('Administrativo')
                    ->navigationSort(1)
                    ->authorize(fn() => app(UserService::class)->ehAdmin(User::authUser())),
            ]);
    }
}
