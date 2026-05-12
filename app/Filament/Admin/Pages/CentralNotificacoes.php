<?php

namespace App\Filament\Admin\Pages;

use App\Models\User;
use App\Services\NotificationCenterService;
use BackedEnum;
use Filament\Actions;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class CentralNotificacoes extends Page
{
    protected string $view = 'filament.pages.central-notificacoes';

    protected static ?string $title = 'Central de Notificações';

    protected static ?string $slug = 'central-notificacoes';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return app(NotificationCenterService::class)->canView($user);
    }

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Notificações',
            'title' => 'Central de Notificações',
            'description' => 'Gerencie as notificações, adicione novas e mantenha um registro atualizado das informações.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        $service = app(NotificationCenterService::class);

        /** @var User|null $user */
        $user = Auth::user();

        return [
            Actions\Action::make('marcar_todas_como_lidas')
                ->label('Marcar todas como lidas')
                ->icon('heroicon-o-check-badge')
                ->color('gray')
                ->url('#')
                ->extraAttributes([
                    'data-nc-action' => 'mark-all-read',
                    'style' => 'display:none;',
                ]),

            Actions\Action::make('nova_notificacao')
                ->label('Nova notificação')
                ->icon('heroicon-o-megaphone')
                ->color('primary')
                ->visible(fn() => $service->canCreate($user))
                ->url('#')
                ->extraAttributes([
                    'data-nc-action' => 'open-create',
                ]),
        ];
    }

    protected function getViewData(): array
    {
        $service = app(NotificationCenterService::class);

        /** @var User|null $user */
        $user = Auth::user();

        return [
            'canCreateNotifications' => $service->canCreate($user),
            'priorityOptions' => $service->prioridadeOptions(),
            'destinationTypeOptions' => $service->destinoTipoOptions(),
            'recipientOptions' => $service->formOptions(),
            'endpoints' => [
                'index' => route('notifications.center'),
                'send' => route('notifications.send'),
                'markAllRead' => route('notifications.markAllRead'),
                'markReadBase' => url('/admin/notifications'),
            ],
            'soundUrl' => asset('sons/som-notificacao.MP3'),
        ];
    }
}
