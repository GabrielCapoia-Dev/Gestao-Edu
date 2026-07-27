<?php

namespace App\Livewire\Home;

use App\Filament\Admin\Resources\ReservasVeiculos\ReservaVeiculoResource;
use App\Filament\Admin\Resources\ReservasVeiculos\Schemas\ReservaVeiculoForm;
use App\Models\ReservaVeiculo;
use App\Models\User;
use App\Services\Dashboard\ReservaVeiculoService;
use App\Services\ProfilePreviewService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ReservasVeiculosResumo extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function novaReservaAction(): Action
    {
        $usuario = $this->usuario();

        return Action::make('novaReserva')
            ->label('Reservar veículo')
            ->icon('heroicon-o-plus')
            ->visible(fn (): bool => Gate::forUser($usuario)->allows('create', ReservaVeiculo::class))
            ->authorize(fn (): bool => Gate::forUser($usuario)->allows('create', ReservaVeiculo::class))
            ->modalHeading('Nova reserva de veículo')
            ->modalDescription('Adicione quantas datas forem necessárias para repetir o deslocamento.')
            ->modalWidth('3xl')
            ->closeModalByClickingAway(false)
            ->schema(ReservaVeiculoForm::criacao($usuario))
            ->action(function (array $data) use ($usuario): void {
                $reservas = app(ReservaVeiculoService::class)->criarEmLote($usuario, [
                    ...$data,
                    'datas' => collect($data['datas'] ?? [])
                        ->pluck('data')
                        ->filter()
                        ->values()
                        ->all(),
                ]);

                Notification::make()
                    ->title($reservas->count() === 1
                        ? 'Reserva criada'
                        : "{$reservas->count()} reservas criadas")
                    ->success()
                    ->send();
            });
    }

    public function render(): View
    {
        $usuario = app(ProfilePreviewService::class)->effectiveUser();
        $podeAcessar = $usuario instanceof User
            && Gate::forUser($usuario)->allows('viewAny', ReservaVeiculo::class);

        return view('livewire.home.reservas-veiculos-resumo', [
            'podeAcessar' => $podeAcessar,
            'reservas' => $podeAcessar
                ? app(ReservaVeiculoService::class)->proximas($usuario, 4)
                : collect(),
            'gerenciarUrl' => $podeAcessar ? ReservaVeiculoResource::getUrl() : null,
        ]);
    }

    private function usuario(): User
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }
}
