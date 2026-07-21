<?php

namespace App\Services\Dashboard;

use App\Models\Aviso;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
        $this->reenviarEmMassa([$aviso], $ator);

        return $aviso->refresh();
    }

    /** @param iterable<Aviso> $avisos */
    public function definirPublicacaoEmMassa(iterable $avisos, User $ator, bool $publicar): int
    {
        $avisos = $this->normalizarAvisos($avisos);

        foreach ($avisos as $aviso) {
            Gate::forUser($ator)->authorize('publish', $aviso);

            if ($publicar && $aviso->fim_exibicao?->isPast()) {
                throw new \DomainException('Avisos expirados precisam ter o período atualizado antes da publicação.');
            }
        }

        return DB::transaction(function () use ($avisos, $ator, $publicar): int {
            $ids = $avisos->modelKeys();
            $avisosBloqueados = Aviso::query()
                ->whereKey($ids)
                ->lockForUpdate()
                ->get();

            if ($avisosBloqueados->count() !== count($ids)) {
                throw new \DomainException('Um dos avisos selecionados não está mais disponível.');
            }

            $alterados = 0;

            foreach ($avisosBloqueados as $aviso) {
                if ($publicar && $aviso->fim_exibicao?->isPast()) {
                    throw new \DomainException('Avisos expirados precisam ter o período atualizado antes da publicação.');
                }

                if ((bool) $aviso->ativo === $publicar) {
                    continue;
                }

                $aviso->forceFill([
                    'ativo' => $publicar,
                    'atualizado_por_id' => $ator->getKey(),
                ])->save();
                $alterados++;
            }

            return $alterados;
        });
    }

    /** @param iterable<Aviso> $avisos */
    public function reenviarEmMassa(iterable $avisos, User $ator): int
    {
        $avisos = $this->normalizarAvisos($avisos);

        foreach ($avisos as $aviso) {
            Gate::forUser($ator)->authorize('publish', $aviso);

            if ($aviso->statusExibicao() !== 'ativo') {
                throw new \DomainException('Somente avisos ativos e dentro do período de exibição podem ser enviados novamente.');
            }
        }

        return DB::transaction(function () use ($avisos, $ator): int {
            $ids = $avisos->modelKeys();
            $avisosBloqueados = Aviso::query()
                ->whereKey($ids)
                ->lockForUpdate()
                ->get();

            if ($avisosBloqueados->count() !== count($ids)) {
                throw new \DomainException('Um dos avisos selecionados não está mais disponível.');
            }

            foreach ($avisosBloqueados as $aviso) {
                if ($aviso->statusExibicao() !== 'ativo') {
                    throw new \DomainException('Somente avisos ativos e dentro do período de exibição podem ser enviados novamente.');
                }

                $aviso->forceFill([
                    'versao_envio' => max(1, (int) $aviso->versao_envio) + 1,
                    'atualizado_por_id' => $ator->getKey(),
                ])->save();
            }

            return $avisosBloqueados->count();
        });
    }

    /** @param iterable<Aviso> $avisos */
    private function normalizarAvisos(iterable $avisos): EloquentCollection
    {
        $recebidos = collect($avisos);

        if ($recebidos->isEmpty() || $recebidos->contains(fn ($aviso): bool => ! ($aviso instanceof Aviso))) {
            throw new \DomainException('Selecione ao menos um aviso válido.');
        }

        return new EloquentCollection($recebidos
            ->unique(fn (Aviso $aviso): int => (int) $aviso->getKey())
            ->values()
            ->all());
    }
}
