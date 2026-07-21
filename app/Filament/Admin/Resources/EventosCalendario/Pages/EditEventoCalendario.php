<?php

namespace App\Filament\Admin\Resources\EventosCalendario\Pages;

use App\Filament\Admin\Resources\EventosCalendario\EventoCalendarioResource;
use App\Filament\Admin\Resources\EventosCalendario\Schemas\EventoCalendarioForm;
use App\Models\EventoCalendario;
use App\Services\Dashboard\EventoCalendarioService;
use App\Services\Dashboard\EventoCalendarioEscolaService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEventoCalendario extends EditRecord
{
    protected static string $resource = EventoCalendarioResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $inicio = $this->record->data_inicio->format('H:i');
        $fim = $this->record->data_fim->format('H:i');
        $data['data_evento'] = $this->record->data_inicio->toDateString();
        $data['hora_inicio'] = $inicio;
        $data['hora_fim'] = $fim;
        $data['periodo'] = EventoCalendarioForm::periodoCorrespondente($inicio, $fim);
        $data['inserir_link'] = filled($this->record->link_acao);
        $data['enviar_todos_usuarios'] = (bool) $this->record->publicoAlvo?->todos_usuarios;
        $data['escolas_agendadas'] = app(EventoCalendarioEscolaService::class)
            ->paraFormulario($this->record);

        return $data;
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

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
