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
                'versao_envio' => 1,
                'criado_por_id' => $ator->getKey(),
                'atualizado_por_id' => $ator->getKey(),
                'excluido_por_id' => null,
            ])->save();

            return $copia->fresh(['publicoAlvo']);
        });
    }

    public function reenviar(Aviso $aviso, User $ator): Aviso
    {
        Gate::forUser($ator)->authorize('publish', $aviso);

        return DB::transaction(function () use ($aviso, $ator): Aviso {
            $avisoAtual = Aviso::query()->lockForUpdate()->findOrFail($aviso->getKey());
            $avisoAtual->forceFill([
                'versao_envio' => max(1, (int) $avisoAtual->versao_envio) + 1,
                'atualizado_por_id' => $ator->getKey(),
            ])->save();

            return $avisoAtual->refresh();
        });
    }
}
