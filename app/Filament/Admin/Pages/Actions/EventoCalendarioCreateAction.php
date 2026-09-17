<?php

namespace App\Filament\Admin\Pages\Actions;

use App\Filament\Admin\Pages\Schemas\EventoCalendarioForm;
use App\Models\EventoCalendario;
use App\Models\User;
use App\Services\Dashboard\EventoCalendarioService;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Gate;

final class EventoCalendarioCreateAction
{
    public static function make(string $name, ?User $user): CreateAction
    {
        return CreateAction::make($name)
            ->label('Novo evento')
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->model(EventoCalendario::class)
            ->modelLabel('evento')
            ->modalHeading('Novo evento')
            ->modalDescription('Informe os dados essenciais e defina as escolas participantes.')
            ->modalWidth('5xl')
            ->modalSubmitActionLabel('Criar evento')
            ->modalCancelActionLabel('Cancelar')
            ->modalFooterActionsAlignment('end')
            ->closeModalByClickingAway(false)
            ->createAnother(false)
            ->schema(fn (): array => EventoCalendarioForm::components($user))
            ->visible(fn (): bool => $user && Gate::forUser($user)->allows('create', EventoCalendario::class))
            ->authorize(fn (): bool => $user && Gate::forUser($user)->allows('create', EventoCalendario::class))
            ->using(function (array $data) use ($user): EventoCalendario {
                abort_unless($user, 403);

                return app(EventoCalendarioService::class)->criar($data, [], $user);
            })
            ->successNotificationTitle('Evento criado');
    }
}
