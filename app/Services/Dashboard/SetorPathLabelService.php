<?php

namespace App\Services\Dashboard;

use App\Models\Setor;

class SetorPathLabelService
{
    /**
     * Monta os nomes hierárquicos em duas consultas no máximo, sem executar
     * uma busca de ancestrais para cada setor exibido.
     *
     * @param iterable<Setor|null> $setores
     * @return array<int, string>
     */
    public function labels(iterable $setores): array
    {
        $models = collect($setores)
            ->filter(fn ($setor): bool => $setor instanceof Setor && $setor->exists)
            ->unique(fn (Setor $setor): int => (int) $setor->getKey())
            ->values();

        if ($models->isEmpty()) {
            return [];
        }

        $paths = $models->mapWithKeys(fn (Setor $setor): array => [
            (int) $setor->getKey() => $this->pathIds($setor),
        ]);
        $names = Setor::query()
            ->whereKey($paths->flatten()->unique()->all())
            ->pluck('nome', 'id');

        return $models->mapWithKeys(function (Setor $setor) use ($paths, $names): array {
            $label = collect($paths->get((int) $setor->getKey(), []))
                ->map(fn (int $id): ?string => $names->get($id))
                ->filter()
                ->join(' > ');

            return [(int) $setor->getKey() => $label !== '' ? $label : (string) $setor->nome];
        })->all();
    }

    /** @return list<int> */
    private function pathIds(Setor $setor): array
    {
        $ids = collect(explode('/', trim((string) $setor->path, '/')))
            ->filter(fn (string $id): bool => ctype_digit($id) && (int) $id > 0)
            ->map(fn (string $id): int => (int) $id)
            ->values();

        if (! $ids->contains((int) $setor->getKey())) {
            $ids->push((int) $setor->getKey());
        }

        return $ids->unique()->values()->all();
    }
}
