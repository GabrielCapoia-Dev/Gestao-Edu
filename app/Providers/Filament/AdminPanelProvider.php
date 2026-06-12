<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Auth\EditProfile as CustomEditProfile;
use App\Filament\Admin\Resources\Pedidos\Pages\ListPedidos;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Middleware\ApplyProfilePreviewUser;
use App\Http\Middleware\BlockProfilePreviewWrites;
use App\Http\Middleware\BloquearProfessorPendenciaTransferencia;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Models\User;
use App\Services\ProfilePreviewService;
use App\Services\UserPresenceService;
use Caresome\FilamentAuthDesigner\AuthDesignerPlugin;
use Caresome\FilamentAuthDesigner\Data\AuthPageConfig;
use Caresome\FilamentAuthDesigner\Enums\MediaPosition;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->default()
            ->path('admin')
            ->login([AdminLoginController::class, 'show'])
            ->profile()
            ->globalSearch(false)
            ->userMenuItems([
                Action::make('profilePreview')
                    ->label(fn () => app(ProfilePreviewService::class)->isActive() ? 'Sair do modo visualização' : 'Trocar de Usuário')
                    ->icon(fn () => app(ProfilePreviewService::class)->isActive() ? Heroicon::ArrowUturnLeft : Heroicon::ArrowsRightLeft)
                    ->color(fn () => app(ProfilePreviewService::class)->isActive() ? 'danger' : null)
                    ->visible(fn () => app(ProfilePreviewService::class)->canControl())
                    ->sort(1)
                    ->modalHeading(fn () => app(ProfilePreviewService::class)->isActive() ? 'Sair do modo visualização' : 'Trocar de Usuário')
                    ->modalSubmitActionLabel(fn () => app(ProfilePreviewService::class)->isActive() ? 'Voltar à normalidade' : 'Ativar visualização')
                    ->form(fn () => [
                        Select::make('target_user_id')
                            ->label('Usuário para visualizar')
                            ->options(fn () => User::query()
                                ->where('email_approved', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->mapWithKeys(fn ($name, $id) => [$id => $name.' (#'.$id.')']))
                            ->searchable()
                            ->required()
                            ->placeholder('Selecione um usuário...')
                            ->visible(fn () => ! app(ProfilePreviewService::class)->isActive()),
                    ])
                    ->action(function (array $data) {
                        $preview = app(ProfilePreviewService::class);
                        $realUser = $preview->controlUser();

                        if ($preview->isActive()) {
                            $preview->stop();

                            Notification::make()
                                ->title('Modo visualização finalizado')
                                ->body('Seu acesso normal foi restaurado.')
                                ->success()
                                ->send();

                            return redirect()->to($this->profilePreviewRefreshUrl());
                        }

                        if (! $realUser || ! $preview->canControl($realUser)) {
                            Notification::make()
                                ->title('Sem permissão')
                                ->danger()
                                ->send();

                            return;
                        }

                        $preview->start((int) $data['target_user_id'], $realUser);

                        Notification::make()
                            ->title('Modo visualização ativado')
                            ->body('Você está navegando como outro usuário. Ações de escrita serão bloqueadas.')
                            ->success()
                            ->send();

                        return redirect()->to($this->profilePreviewRefreshUrl());
                    }),
            ])
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
                ApplyProfilePreviewUser::class,
                BlockProfilePreviewWrites::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordIsChanged::class,
                BloquearProfessorPendenciaTransferencia::class,
            ], isPersistent: true)
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                function () {
                    $user = User::authUser();
                    $showNotifications = false;

                    if ($user) {
                        try {
                            $showNotifications = $user->hasPermissionTo('Visualizar Notificações');
                        } catch (PermissionDoesNotExist) {
                            $showNotifications = false;
                        }
                    }

                    return view('filament.partials.topbar-user-menu-before', [
                        'showOnlineUsers' => $user?->hasPermissionTo(UserPresenceService::PERMISSION) ?? false,
                        'showNotifications' => $showNotifications,
                    ]);
                }
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): View => view('filament.partials.profile-preview-body-state')
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): View => view('components.user-presence-heartbeat')
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.pages.partials.inventory-page-styles')
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.pages.partials.panel-layering-styles')
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.pages.partials.pedidos-responsive-table-styles'),
                ListPedidos::class
            )
            ->plugins([
                AuthDesignerPlugin::make()
                    ->profile(
                        fn (AuthPageConfig $config) => $config
                            ->media(asset('images/background.jpg'))
                            ->mediaPosition(MediaPosition::Cover)
                            ->blur(1)
                            ->usingPage(CustomEditProfile::class)
                    ),
            ]);
    }

    private function profilePreviewRefreshUrl(): string
    {
        $fallback = Filament::getPanel('admin')->getUrl();
        $referer = request()->headers->get('referer');

        if (! is_string($referer) || blank($referer)) {
            return $fallback;
        }

        if (str_starts_with($referer, '/') && ! str_starts_with($referer, '//')) {
            return url($referer);
        }

        if (parse_url($referer, PHP_URL_HOST) === request()->getHost()) {
            return $referer;
        }

        return $fallback;
    }
}
