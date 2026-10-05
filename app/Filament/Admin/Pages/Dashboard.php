<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\Avisos\AvisoResource;
use App\Filament\Admin\Resources\ReservasVeiculos\ReservaVeiculoResource;
use App\Filament\Admin\Resources\ReservasVeiculos\Schemas\ReservaVeiculoForm;
use App\Models\Aviso;
use App\Models\Enums\ListaPermissoes;
use App\Models\EventoCalendario;
use App\Models\ReservaVeiculo;
use App\Models\User;
use App\Services\Dashboard\ReservaVeiculoService;
use App\Services\PedidoService;
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
                ->visible(fn (): bool => $usuarioEfetivo !== null
                    && $usuarioEfetivo->hasAnyPermissionTo([
                        ListaPermissoes::CriarAvisos->label(),
                        ListaPermissoes::EditarAvisos->label(),
                        ListaPermissoes::ExcluirAvisos->label(),
                        ListaPermissoes::PublicarAvisos->label(),
                        ListaPermissoes::GerenciarPublicoAlvoDeAvisos->label(),
                    ]))
                ->url(fn (): string => AvisoResource::getUrl()),

            Action::make('gerenciar_agenda')
                ->label('Gerenciar agenda')
                ->icon('heroicon-o-calendar-days')
                ->visible(fn (): bool => $usuarioEfetivo !== null
                    && $usuarioEfetivo->hasAnyPermissionTo([
                        ListaPermissoes::CriarEventos->label(),
                        ListaPermissoes::CriarEventosTransporte->label(),
                        ListaPermissoes::EditarEventos->label(),
                        ListaPermissoes::PublicarEventos->label(),
                        ListaPermissoes::PublicarEventosTransporte->label(),
                        ListaPermissoes::DesativarEventos->label(),
                        ListaPermissoes::DesativarEventosTransporte->label(),
                        ListaPermissoes::GerenciarPublicoAlvoDeEventos->label(),
                        ListaPermissoes::GerenciarTransporteDeEventos->label(),
                    ]))
                ->url(fn (): string => GerenciarEventos::getUrl()),

            Action::make('visualizar_calendario')
                ->label('Visualizar calendário')
                ->icon('heroicon-o-calendar')
                ->color('gray')
                ->visible(fn (): bool => $usuarioEfetivo?->hasPermissionTo(
                    ListaPermissoes::VisualizarAgendaDeTodaARede->label(),
                ) ?? false)
                ->authorize(fn (): bool => $usuarioEfetivo?->hasPermissionTo(
                    ListaPermissoes::VisualizarAgendaDeTodaARede->label(),
                ) ?? false)
                ->modalHeading('Calendário da rede')
                ->modalDescription('Consulte eventos por ano, mês ou semana. Pedidos de manutenção continuam limitados ao seu contexto de acesso.')
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->modalContent(fn (): View => view('livewire.home.calendario-completo-modal')),

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
                ->modalDescription('Escolha quando a reserva se repete e até quando. Todas as ocorrências serão verificadas antes de confirmar.')
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

    public function podeVisualizarAgenda(): bool
    {
        $usuario = app(ProfilePreviewService::class)->effectiveUser();

        return $usuario !== null
            && (Gate::forUser($usuario)->allows('viewAny', EventoCalendario::class)
                || app(PedidoService::class)->ehMembroDaManutencao($usuario));
    }

    public static function userService(): UserService
    {
        return app(UserService::class);
    }
}
