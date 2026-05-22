<?php

namespace App\Filament\Admin\Pages;

use App\Models\User;
use App\Services\NotificationCenterService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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
                ->visible(fn (): bool => $service->canCreate($user) && $service->destinoTipoOptions($user) !== [])
                ->modalHeading('Nova notificação')
                ->modalDescription('Disparo manual')
                ->modalIcon('heroicon-o-megaphone')
                ->modalSubmitActionLabel('Enviar notificação')
                ->modalWidth('3xl')
                ->schema(fn (): array => $this->notificationFormSchema($service, $user))
                ->action(function (array $data) use ($service, $user): void {
                    if (! $user) {
                        abort(403);
                    }

                    $result = $service->send($user, $data);

                    if (($result['count'] ?? 0) < 1) {
                        throw ValidationException::withMessages([
                            'destinatarios' => 'Nenhum destinatário encontrado para o público selecionado.',
                        ]);
                    }

                    Notification::make()
                        ->title('Notificação enfileirada')
                        ->body(($result['count'] ?? 0).' destinatário(s) em '.$result['destino_label'].'.')
                        ->success()
                        ->send();

                    $this->dispatch('notification-center-refresh');
                }),
        ];
    }

    private function notificationFormSchema(NotificationCenterService $service, ?User $user): array
    {
        $destinationOptions = $service->destinoTipoOptions($user);
        $recipientOptions = $service->formSelectOptions($user);

        return [
            TextInput::make('titulo')
                ->label('Título')
                ->required()
                ->maxLength(120)
                ->columnSpanFull(),

            Textarea::make('mensagem')
                ->label('Mensagem')
                ->required()
                ->rows(4)
                ->maxLength(1500)
                ->columnSpanFull(),

            Grid::make()
                ->columns(2)
                ->schema([
                    Select::make('prioridade')
                        ->label('Prioridade')
                        ->options($service->prioridadeOptions())
                        ->default('normal')
                        ->native(false)
                        ->required(),

                    Select::make('destino_tipo')
                        ->label('Enviar para')
                        ->options($destinationOptions)
                        ->default(array_key_first($destinationOptions) ?: null)
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function (callable $set): void {
                            foreach (['usuarios_ids', 'roles_ids', 'escolas_ids', 'turmas_ids', 'permissoes', 'setores_ids'] as $field) {
                                $set($field, []);
                            }
                        })
                        ->required(),
                ])
                ->columnSpanFull(),

            TextInput::make('url')
                ->label('Link de ação')
                ->placeholder('https://...')
                ->maxLength(2048)
                ->columnSpanFull(),

            TextInput::make('label')
                ->label('Texto do botão')
                ->placeholder('Ver detalhes')
                ->maxLength(80)
                ->columnSpanFull(),

            ...$this->recipientSelects($recipientOptions),
        ];
    }

    private function recipientSelects(array $recipientOptions): array
    {
        return [
            Select::make('usuarios_ids')
                ->label('Usuários')
                ->options($recipientOptions['usuarios'] ?? [])
                ->multiple()
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => $get('destino_tipo') === 'usuarios')
                ->required(fn (Get $get): bool => $get('destino_tipo') === 'usuarios')
                ->dehydrated(fn (Get $get): bool => $get('destino_tipo') === 'usuarios')
                ->columnSpanFull(),

            Select::make('roles_ids')
                ->label('Níveis de acesso')
                ->options($recipientOptions['roles'] ?? [])
                ->multiple()
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => $get('destino_tipo') === 'roles')
                ->required(fn (Get $get): bool => $get('destino_tipo') === 'roles')
                ->dehydrated(fn (Get $get): bool => $get('destino_tipo') === 'roles')
                ->columnSpanFull(),

            Select::make('escolas_ids')
                ->label('Escolas')
                ->options($recipientOptions['escolas'] ?? [])
                ->multiple()
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => $get('destino_tipo') === 'escolas')
                ->required(fn (Get $get): bool => $get('destino_tipo') === 'escolas')
                ->dehydrated(fn (Get $get): bool => $get('destino_tipo') === 'escolas')
                ->columnSpanFull(),

            Select::make('turmas_ids')
                ->label('Turmas')
                ->options($recipientOptions['turmas'] ?? [])
                ->multiple()
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => $get('destino_tipo') === 'professores_turmas')
                ->required(fn (Get $get): bool => $get('destino_tipo') === 'professores_turmas')
                ->dehydrated(fn (Get $get): bool => $get('destino_tipo') === 'professores_turmas')
                ->columnSpanFull(),

            Select::make('permissoes')
                ->label('Permissões')
                ->options($recipientOptions['permissoes'] ?? [])
                ->multiple()
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => $get('destino_tipo') === 'permissoes')
                ->required(fn (Get $get): bool => $get('destino_tipo') === 'permissoes')
                ->dehydrated(fn (Get $get): bool => $get('destino_tipo') === 'permissoes')
                ->columnSpanFull(),

            Select::make('setores_ids')
                ->label('Setores')
                ->options($recipientOptions['setores'] ?? [])
                ->multiple()
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => $get('destino_tipo') === 'setores')
                ->required(fn (Get $get): bool => $get('destino_tipo') === 'setores')
                ->dehydrated(fn (Get $get): bool => $get('destino_tipo') === 'setores')
                ->columnSpanFull(),
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
            'endpoints' => [
                'index' => route('notifications.center'),
                'unreadCount' => route('notifications.unreadCount'),
                'send' => route('notifications.send'),
                'markAllRead' => route('notifications.markAllRead'),
                'markReadBase' => url('/admin/notifications'),
            ],
            'pollIntervalMs' => (int) config('notifications.center_poll_interval_ms', 30000),
            'soundUrl' => asset('sons/som-notificacao.MP3'),
        ];
    }
}
