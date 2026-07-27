<?php

namespace App\Filament\Admin\Resources\ReservasVeiculos\Pages;

use App\Filament\Admin\Resources\ReservasVeiculos\ReservaVeiculoResource;
use App\Filament\Admin\Resources\ReservasVeiculos\Schemas\ReservaVeiculoForm;
use App\Livewire\Transporte\VeiculosTransporteTable;
use App\Models\ReservaVeiculo;
use App\Models\User;
use App\Models\VeiculoTransporte;
use App\Services\Dashboard\ReservaVeiculoService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ManageReservasVeiculos extends ManageRecords
{
    protected static string $resource = ReservaVeiculoResource::class;

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Frota',
            'title' => 'Reserva de veículos',
            'description' => 'Organize deslocamentos, consulte disponibilidade e acompanhe as reservas da rede.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        $usuario = $this->usuario();

        return [
            Action::make('gerenciar_frota')
                ->label('Gerenciar frota')
                ->icon('heroicon-o-truck')
                ->color('gray')
                ->visible(fn (): bool => Gate::forUser($usuario)->allows('viewAny', VeiculoTransporte::class))
                ->authorize(fn (): bool => Gate::forUser($usuario)->allows('viewAny', VeiculoTransporte::class))
                ->modalHeading('Veículos da frota')
                ->modalDescription('Cadastre e mantenha os veículos disponíveis para reserva.')
                ->slideOver()
                ->modalWidth('5xl')
                ->stickyModalHeader()
                ->formWrapper(false)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->schema([
                    LivewireComponent::make(VeiculosTransporteTable::class)
                        ->key('veiculos-reserva-frota'),
                ]),

            Action::make('nova_reserva')
                ->label('Nova reserva')
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

    private function usuario(): User
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }
}
