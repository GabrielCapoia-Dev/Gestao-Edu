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
use Filament\Http\Middleware\Authenticate;
use App\Livewire\LoginPage;
use App\Services\UserService;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LaudoArquivoController;
use Filament\Actions\Action as GlobalAction;
use App\Models\User;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Caresome\FilamentAuthDesigner\AuthDesignerPlugin;
use Caresome\FilamentAuthDesigner\Data\AuthPageConfig;
use Caresome\FilamentAuthDesigner\Enums\MediaPosition;
use Caresome\FilamentAuthDesigner\View\AuthDesignerRenderHook;


class AdminPanelProvider extends PanelProvider
{

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->spa()
            ->colors([
                'primary' => Color::Sky,
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
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->discoverClusters(in: app_path('Filament/Admin/Clusters'), for: 'App\\Filament\\Admin\\Clusters')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
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
            ])

            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                function () {
                    $user = User::authUser();

                    if ($user->hasPermissionTo('Visualizar Notificações')) {

                        return Blade::render('@livewire("topbar-notifications")');
                    }
                    return '';
                }
            )
            ->plugins([
                AuthDesignerPlugin::make()
                    ->login(
                        fn(AuthPageConfig $config) => $config
                            ->media(asset('images/background.png'))
                            ->mediaPosition(MediaPosition::Left)
                            ->renderHook(AuthDesignerRenderHook::MediaOverlay, fn() => view('background-page'))
                            ->usingPage(LoginPage::class)
                            ->mediaSize('70%')
                            ->themeToggle()

                    )
            ]);
    }
}
