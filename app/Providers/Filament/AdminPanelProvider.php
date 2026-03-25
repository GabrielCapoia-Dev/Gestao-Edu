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
                    50  => '#eef6fc',
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
                    50  => '#f4f5f9',
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

            // ----------------------------------------------------------------
            // HOOK 1 — SIDEBAR_NAV_START
            // Injeta o separador "Principal" antes dos primeiros itens de nav,
            // replicando: <div class="section"><span>Principal</span></div>
            //
            // NOTA: os demais grupos (Conta, Sistema) são criados via
            // ->navigationGroups() em cada Resource. Este hook só adiciona
            // o separador do primeiro bloco quando não há grupo definido,
            // ou serve como cabeçalho visual extra acima de todos os grupos.
            // ----------------------------------------------------------------
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn(): string => Blade::render(<<<'HTML'
                    <div style="
                        font-size: 10px;
                        font-weight: 800;
                        letter-spacing: 0.1em;
                        color: rgba(255,255,255,0.25);
                        text-transform: uppercase;
                        padding: 16px 12px 8px;
                        font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
                    ">Principal</div>
                HTML)
            )

            // ----------------------------------------------------------------
            // HOOK 3 — SIDEBAR_FOOTER
            // Injeta o rodapé com o link "Sair",
            // replicando: .app-sidebar-foot a { ícone + "Sair" }
            //
            // O Filament já renderiza o menu do usuário no footer por padrão.
            // Este hook adiciona o link de logout estilizado ABAIXO dele,
            // caso queira um atalho rápido visível como no protótipo.
            // Se preferir substituir completamente o footer padrão,
            // use ->userMenuItems([]) no panel e mantenha apenas este hook.
            // ----------------------------------------------------------------
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn(): string => Blade::render(<<<'HTML'
                    <div style="padding:8px;border-top:1px solid rgba(255,255,255,.06);">
                        <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
                            @csrf
                            <button type="submit" style="
                                display:flex;align-items:center;gap:10px;
                                font-size:12px;color:rgba(255,255,255,.4);
                                text-decoration:none;padding:8px 12px;border-radius:8px;
                                transition:color .2s,background .2s;
                                background:transparent;border:none;cursor:pointer;
                                width:100%;font-family:'Plus Jakarta Sans',system-ui,sans-serif;
                            "
                            onmouseover="this.style.color='#9cc7e7';this.style.background='rgba(255,255,255,.04)'"
                            onmouseout="this.style.color='rgba(255,255,255,.4)';this.style.background='transparent'">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" style="flex-shrink:0">
                                    <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                <span>Sair</span>
                            </button>
                        </form>
                    </div>
                HTML)
            )

            // ----------------------------------------------------------------
            // HOOK 4 — USER_MENU_BEFORE
            // Notificações no canto da topbar (mantido do original)
            // ----------------------------------------------------------------
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
