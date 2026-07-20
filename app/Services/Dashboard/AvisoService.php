<?php

namespace App\Services\Dashboard;

use App\Models\Aviso;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AvisoService
{
    public function __construct(
        private readonly PublicoAlvoService $publicoAlvoService,
    ) {}

    public function duplicar(Aviso $aviso, User $ator): Aviso
    {
        Gate::forUser($ator)->authorize('duplicate', $aviso);

        return DB::transaction(function () use ($aviso, $ator): Aviso {
            $aviso->loadMissing('publicoAlvo');
            $publicoAlvo = $this->publicoAlvoService->duplicar($aviso->publicoAlvo, $ator);
            $copia = $aviso->replicate();

            $copia->forceFill([
                'publico_alvo_id' => $publicoAlvo->getKey(),
                'titulo' => (string) str('Cópia de '.$aviso->titulo)->limit(160, ''),
                'ativo' => false,
                'criado_por_id' => $ator->getKey(),
                'atualizado_por_id' => $ator->getKey(),
                'excluido_por_id' => null,
            ])->save();

            return $copia->fresh(['publicoAlvo']);
        });
    }
}
