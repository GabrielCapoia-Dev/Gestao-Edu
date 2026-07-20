<?php

namespace App\Filament\Admin\Resources\Avisos\Pages;

use App\Filament\Admin\Resources\Avisos\AvisoResource;
use App\Filament\Admin\Support\PublicoAlvoForm;
use App\Models\Aviso;
use App\Models\User;
use App\Services\Dashboard\PublicoAlvoService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EditAviso extends EditRecord
{
    protected static string $resource = AvisoResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Aviso $aviso */
        $aviso = $this->record;
        $data[PublicoAlvoForm::STATE_PATH] = PublicoAlvoForm::paraFormulario($aviso->publicoAlvo);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user, 403);
        Gate::forUser($user)->authorize('update', $record);

        [$avisoData, $publicoAlvoData] = PublicoAlvoForm::separar($data);
        $podeGerenciarPublico = Gate::forUser($user)->allows('manageAudience', $record);

        if (! Gate::forUser($user)->allows('publish', $record)) {
            unset($avisoData['ativo']);
        }

        return DB::transaction(function () use (
            $record,
            $avisoData,
            $publicoAlvoData,
            $podeGerenciarPublico,
            $user,
        ): Model {
            if ($podeGerenciarPublico) {
                app(PublicoAlvoService::class)->atualizar(
                    $record->publicoAlvo,
                    $user,
                    $publicoAlvoData,
                );
            }

            $record->fill([
                ...$avisoData,
                'atualizado_por_id' => $user->getKey(),
            ])->save();

            return $record;
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
