<?php

namespace App\Filament\Admin\Resources\EventosCalendario\Pages;

use App\Filament\Admin\Resources\EventosCalendario\EventoCalendarioResource;
use App\Filament\Admin\Support\PublicoAlvoForm;
use App\Models\EventoCalendario;
use App\Services\Dashboard\EventoCalendarioService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEventoCalendario extends CreateRecord
{
    protected static string $resource = EventoCalendarioResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        [$data, $publico] = PublicoAlvoForm::separar($data);
        $user = EventoCalendarioResource::usuarioEfetivo();
        abort_unless($user, 403);

        return app(EventoCalendarioService::class)->criar($data, $publico, $user);
    }
}
