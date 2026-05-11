<?php

namespace App\Providers\Filament;

use App\Http\Middleware\BloquearProfessorPendenciaTransferencia;
use App\Filament\Admin\Pages\Auth\EditProfile as CustomEditProfile;
use App\Livewire\LoginPage;
use App\Models\User;
use App\Services\UserPresenceService;
use Caresome\FilamentAuthDesigner\AuthDesignerPlugin;
use Caresome\FilamentAuthDesigner\Data\AuthPageConfig;
use Caresome\FilamentAuthDesigner\Enums\MediaPosition;
use Caresome\FilamentAuthDesigner\View\AuthDesignerRenderHook;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Spatie\Permission\Models\Permission;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->default()
            ->path('admin')
            ->login()
            ->profile()
            ->darkMode(false)
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
                BloquearProfessorPendenciaTransferencia::class,
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                function () {
                    $user = User::authUser();
                    $notificationPermission = collect([
                        'Visualizar Notificações',
                    ])->first(fn (string $name): bool => Permission::query()->where('name', $name)->exists());

                    return view('filament.partials.topbar-user-menu-before', [
                        'showOnlineUsers' => $user?->hasPermissionTo(UserPresenceService::PERMISSION) ?? false,
                        'showNotifications' => $user
                            && filled($notificationPermission)
                            && $user->hasPermissionTo($notificationPermission),
                    ]);
                }
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): \Illuminate\Contracts\View\View => view('components.user-presence-heartbeat')
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): \Illuminate\Contracts\View\View => view('filament.pages.partials.inventory-page-styles')
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): \Illuminate\Contracts\View\View => view('filament.pages.partials.panel-layering-styles')
            )
            ->plugins([
                AuthDesignerPlugin::make()
                    ->login(
                        fn (AuthPageConfig $config) => $config
                            ->media(asset('images/background.png'))
                            ->mediaPosition(MediaPosition::Left)
                            ->renderHook(AuthDesignerRenderHook::MediaOverlay, fn () => view('background-page'))
                            ->usingPage(LoginPage::class)
                            ->mediaSize('70%')
                            ->themeToggle()
                    )
                    ->profile(
                        fn (AuthPageConfig $config) => $config
                            ->media(asset('images/background.jpg'))
                            ->mediaPosition(MediaPosition::Cover)
                            ->blur(1)
                            ->usingPage(CustomEditProfile::class)
                    ),
            ]);
    }
}
