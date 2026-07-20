<?php

namespace App\Filament\Admin\Resources\EventosCalendario\Pages;

use App\Filament\Admin\Resources\EventosCalendario\EventoCalendarioResource;
use App\Filament\Admin\Support\PublicoAlvoForm;
use App\Models\EventoCalendario;
use App\Services\Dashboard\EventoCalendarioService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class EditEventoCalendario extends EditRecord
{
    protected static string $resource = EventoCalendarioResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data[PublicoAlvoForm::STATE_PATH] = PublicoAlvoForm::paraFormulario($this->record->publicoAlvo);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = EventoCalendarioResource::usuarioEfetivo();
        abort_unless($user && $record instanceof EventoCalendario, 403);

        $podeGerenciarPublico = Gate::forUser($user)->allows('manageAudience', $record);
        [$data, $publico] = PublicoAlvoForm::separar($data);

        return app(EventoCalendarioService::class)->atualizar(
            $record,
            $data,
            $podeGerenciarPublico ? $publico : null,
            $user,
        );
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
