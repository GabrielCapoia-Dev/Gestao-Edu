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
            ->darkMode(false)

            ->colors([
                'primary' => [
                    50  => '#e8f1fb',
                    100 => '#d0e3f7',
                    200 => '#a2c7ef',
                    300 => '#73abe7',
                    400 => '#458fdf',
                    500 => '#1a6bc7',
                    600 => '#074f9b',
                    700 => '#053d78',
                    800 => '#042c56',
                    900 => '#021b34',
                    950 => '#010e1a',
                ],
                'gray' => [
                    50  => '#e5eaf1',
                    100 => '#c7def8',
                    200 => '#c0d4d4',
                    300 => '#c7cacc',
                    400 => '#a0a0a0',
                    500 => '#929292',
                    600 => '#074f9b',
                    700 => '#074f9b',
                    800 => '#151D2F',
                    900 => '#081124',
                    950 => '#081124',
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

            // Notificações na topbar (mantido do original)
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                function () {
                    $user = User::authUser();

                    if ($user->hasPermissionTo('Visualizar Notificações')) {
                        return view('livewire.topbar-notifications-hook');
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
                    ->profile(
                        fn($config) => $config
                            ->media(asset('images/background.png'))
                            ->mediaPosition(MediaPosition::Cover)
                    )
            ]);
    }
}