<?php

namespace App\Filament\Admin\Resources\EventosCalendario\Pages;

use App\Filament\Admin\Resources\EventosCalendario\EventoCalendarioResource;
use App\Filament\Admin\Resources\EventosCalendario\Schemas\EventoCalendarioForm;
use App\Models\EventoCalendario;
use App\Services\Dashboard\EventoCalendarioService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEventoCalendario extends EditRecord
{
    protected static string $resource = EventoCalendarioResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return EventoCalendarioForm::dadosParaEdicao($this->record, $data);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = EventoCalendarioResource::usuarioEfetivo();
        abort_unless($user && $record instanceof EventoCalendario, 403);

        return app(EventoCalendarioService::class)->atualizar(
            $record,
            $data,
            null,
            $user,
        );
    }
}
