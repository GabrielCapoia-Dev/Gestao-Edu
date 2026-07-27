<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\Avisos\AvisoResource;
use App\Filament\Admin\Resources\ReservasVeiculos\ReservaVeiculoResource;
use App\Filament\Admin\Resources\ReservasVeiculos\Schemas\ReservaVeiculoForm;
use App\Models\Aviso;
use App\Models\EventoCalendario;
use App\Models\ReservaVeiculo;
use App\Models\User;
use App\Services\Dashboard\ReservaVeiculoService;
use App\Services\ProfilePreviewService;
use App\Services\UserService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Dashboard extends Page
{
    // protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.pages.dashboard';

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Início',
            'title' => 'Bem-vindo ao Gestão Edu',
            'description' => 'Acompanhe avisos, eventos e atividades importantes para a sua rotina escolar.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        /** @var User|null $usuario */
        $usuario = auth()->user();
        abort_unless($usuario instanceof User, 403);

        $usuarioEfetivo = app(ProfilePreviewService::class)->effectiveUser();

        return [
            Action::make('gerenciar_avisos')
                ->label('Gerenciar avisos')
                ->icon('heroicon-o-megaphone')
                ->color('gray')
                ->visible(fn (): bool => $usuario !== null
                    && Gate::forUser($usuario)->allows('viewAny', Aviso::class))
                ->url(fn (): string => AvisoResource::getUrl()),

            Action::make('gerenciar_agenda')
                ->label('Gerenciar agenda')
                ->icon('heroicon-o-calendar-days')
                ->visible(fn (): bool => $usuarioEfetivo !== null
                    && Gate::forUser($usuarioEfetivo)->allows('viewAny', EventoCalendario::class))
                ->url(fn (): string => GerenciarEventos::getUrl()),

            Action::make('gerenciar_reservas')
                ->label('Gerenciar reservas')
                ->icon('heroicon-o-truck')
                ->color('gray')
                ->visible(fn (): bool => Gate::forUser($usuario)->allows('viewAny', ReservaVeiculo::class))
                ->url(fn (): string => ReservaVeiculoResource::getUrl()),

            Action::make('reservar_veiculo')
                ->label('Reservar veículo')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => Gate::forUser($usuario)->allows('create', ReservaVeiculo::class))
                ->authorize(fn (): bool => Gate::forUser($usuario)->allows('create', ReservaVeiculo::class))
                ->modalHeading('Nova reserva de veículo')
                ->modalDescription('Para vários dias, informe o intervalo. O horário será repetido diariamente.')
                ->modalWidth('3xl')
                ->closeModalByClickingAway(false)
                ->schema(ReservaVeiculoForm::criacao($usuario))
                ->action(function (array $data) use ($usuario): void {
                    $reservas = app(ReservaVeiculoService::class)->criarEmLote($usuario, $data);

                    Notification::make()
                        ->title($reservas->count() === 1
                            ? 'Reserva criada'
                            : "{$reservas->count()} reservas criadas")
                        ->success()
                        ->send();
                }),
        ];
    }

    protected static ?string $navigationLabel = 'Início';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Home;

    public static function canAccess(): bool
    {
        return Gate::allows('viewDashboard', User::class);
    }

    public static function userService(): UserService
    {
        return app(UserService::class);
    }
}
