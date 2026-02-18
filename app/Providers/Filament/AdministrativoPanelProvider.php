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

class AdministrativoPanelProvider extends PanelProvider
{
    public static function getAuthUser()
    {

        return Auth::user();
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('administrativo')
            ->path('administrativo')
            ->spa()
            ->login(LoginPage::class)
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
            ->brandLogo(fn() => view('components.logo-administrativo'))
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Administrativo/Resources'), for: 'App\\Filament\\Administrativo\\Resources')
            ->discoverPages(in: app_path('Filament/Administrativo/Pages'), for: 'App\\Filament\\Administrativo\\Pages')
            ->resources([
                \App\Filament\Resources\RoleResource::class,
                \App\Filament\Resources\PermissionResource::class,
            ])
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Administrativo/Widgets'), for: 'App\\Filament\\Administrativo\\Widgets')
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
            ->userMenuItems([
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
                    ->authorize(fn() => app(UserService::class)->ehAdmin($this->getAuthUser())),
            ]);
    }
}
