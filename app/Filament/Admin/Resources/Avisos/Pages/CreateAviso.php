<?php

namespace App\Filament\Admin\Resources\Avisos\Pages;

use App\Filament\Admin\Resources\Avisos\AvisoResource;
use App\Filament\Admin\Support\PublicoAlvoForm;
use App\Models\Aviso;
use App\Models\User;
use App\Services\Dashboard\PublicoAlvoService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateAviso extends CreateRecord
{
    protected static string $resource = AvisoResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user, 403);
        Gate::forUser($user)->authorize('create', Aviso::class);

        [$avisoData, $publicoAlvoData] = PublicoAlvoForm::separar($data);

        if (! Gate::forUser($user)->allows('manageAudience', Aviso::class)) {
            $publicoAlvoData = PublicoAlvoForm::normalizar([]);
        }

        if (! Gate::forUser($user)->allows('publish', Aviso::class)) {
            $avisoData['ativo'] = false;
        }

        return DB::transaction(function () use ($avisoData, $publicoAlvoData, $user): Aviso {
            $publicoAlvo = app(PublicoAlvoService::class)->criar($user, $publicoAlvoData);

            return Aviso::query()->create([
                ...$avisoData,
                'publico_alvo_id' => $publicoAlvo->getKey(),
                'criado_por_id' => $user->getKey(),
                'atualizado_por_id' => $user->getKey(),
            ]);
        });
    }
}
