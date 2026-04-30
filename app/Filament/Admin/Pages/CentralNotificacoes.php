<?php

namespace App\Filament\Admin\Pages;

use App\Models\User;
use App\Services\NotificationCenterService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
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
