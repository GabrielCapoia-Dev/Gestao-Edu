<?php

namespace App\Services;

use App\Models\Setor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class SetorHierarchyService
{
    public function defaultRoot(): ?Setor
    {
        return Setor::query()
            ->where('ativo', true)
            ->where('is_default_root', true)
            ->orderBy('id')
            ->first()
            ?? Setor::query()
                ->where('ativo', true)
                ->where('recebe_pedidos_iniciais', true)
                ->orderBy('id')
                ->first()
            ?? Setor::query()
                ->where('ativo', true)
                ->whereNull('parent_id')
                ->orderBy('id')
                ->first()
            ?? Setor::query()
                ->where('ativo', true)
                ->orderBy('id')
                ->first();
    }

    public function refreshNode(Setor $setor, bool $refreshDescendants = true): void
    {
        if (! $setor->exists) {
            return;
        }

        $parent = $setor->parent_id
            ? Setor::query()->find($setor->parent_id)
            : null;

        if ($parent && blank($parent->path)) {
            $this->refreshNode($parent, false);
            $parent->refresh();
        }

        $path = $parent?->path
            ? $parent->path.$setor->id.'/'
            : "/{$setor->id}/";

        $depth = $parent ? ((int) $parent->depth + 1) : 0;

        if ($setor->path !== $path || (int) $setor->depth !== $depth) {
            $setor->forceFill([
                'path' => $path,
                'depth' => $depth,
            ])->saveQuietly();
        }

        if ($setor->is_default_root) {
            Setor::query()
                ->whereKeyNot($setor->id)
                ->where('is_default_root', true)
                ->update(['is_default_root' => false]);
        }

        if (! $refreshDescendants) {
            return;
        }

        Setor::query()
            ->where('parent_id', $setor->id)
            ->orderBy('sort_order')
            ->orderBy('nome')
            ->get()
            ->each(function (Setor $child): void {
                $this->refreshNode($child);
            });
    }

    public function rebuildAll(): void
    {
        Setor::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (Setor $setor): void {
                $this->refreshNode($setor);
            });

        Setor::query()
            ->where(function (Builder $query): void {
                $query->whereNull('path')
                    ->orWhere('path', '');
            })
            ->orderBy('id')
            ->get()
            ->each(function (Setor $setor): void {
                $this->refreshNode($setor);
            });
    }

    public function descendantsQuery(Setor|int|null $setor, bool $includeSelf = false): Builder
    {
        $model = $this->resolveSetor($setor);

        if (! $model || blank($model->path)) {
            return Setor::query()->whereRaw('1 = 0');
        }

        return Setor::query()
            ->where('path', 'like', $model->path.'%')
            ->when(! $includeSelf, fn (Builder $query): Builder => $query->whereKeyNot($model->id))
            ->orderBy('path')
            ->orderBy('sort_order')
            ->orderBy('nome');
    }

    public function selfAndDescendantIds(Setor|int|null $setor): array
    {
        $model = $this->resolveSetor($setor);

        if (! $model) {
            return [];
        }

        if (blank($model->path)) {
            $this->refreshNode($model);
            $model->refresh();
        }

        return Setor::query()
            ->where('path', 'like', $model->path.'%')
            ->orderBy('path')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function ancestorIds(Setor|int|null $setor, bool $includeSelf = true): array
    {
        $model = $this->resolveSetor($setor);

        if (! $model || blank($model->path)) {
            return [];
        }

        return collect(explode('/', trim($model->path, '/')))
            ->filter()
            ->map(fn (string $id): int => (int) $id)
            ->when(! $includeSelf, fn ($ids) => $ids->reject(fn (int $id): bool => $id === (int) $model->id))
            ->values()
            ->all();
    }

    public function isAncestorOf(Setor|int|null $ancestor, Setor|int|null $setor, bool $includeSelf = true): bool
    {
        $ancestorModel = $this->resolveSetor($ancestor);
        $setorModel = $this->resolveSetor($setor);

        if (! $ancestorModel || ! $setorModel || blank($ancestorModel->path) || blank($setorModel->path)) {
            return false;
        }

        if (! $includeSelf && (int) $ancestorModel->id === (int) $setorModel->id) {
            return false;
        }

        return str_starts_with((string) $setorModel->path, (string) $ancestorModel->path);
    }

    public function fullPathLabel(Setor|int|null $setor, string $separator = ' > '): ?string
    {
        $model = $this->resolveSetor($setor);

        if (! $model) {
            return null;
        }

        $ids = $this->ancestorIds($model);

        if ($ids === []) {
            return $model->nome;
        }

        return Setor::query()
            ->whereIn('id', $ids)
            ->get(['id', 'nome'])
            ->sortBy(fn (Setor $item): int => array_search((int) $item->id, $ids, true))
            ->pluck('nome')
            ->join($separator);
    }

    public function labelsForOptions(?array $ids = null): array
    {
        return Setor::query()
            ->where('ativo', true)
            ->when($ids !== null, fn (Builder $query): Builder => $query->whereIn('id', $ids))
            ->orderBy('path')
            ->orderBy('sort_order')
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(fn (Setor $setor): array => [$setor->id => $this->fullPathLabel($setor)])
            ->toArray();
    }

    public function assertValidParent(Setor $setor, ?int $parentId): void
    {
        if (blank($parentId)) {
            return;
        }

        $parent = Setor::query()->find($parentId);

        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_id' => 'Setor pai inexistente.',
            ]);
        }

        if ($setor->exists && (int) $parent->id === (int) $setor->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Um setor não pode ser pai dele mesmo.',
            ]);
        }

        if ($setor->exists && blank($setor->path)) {
            $this->refreshNode($setor, false);
            $setor->refresh();
        }

        if ($setor->exists && blank($parent->path)) {
            $this->refreshNode($parent, false);
            $parent->refresh();
        }

        if ($setor->exists && $setor->path && str_starts_with((string) $parent->path, (string) $setor->path)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Um setor não pode ser movido para dentro de sua própria arvore.',
            ]);
        }
    }

    private function resolveSetor(Setor|int|null $setor): ?Setor
    {
        if ($setor instanceof Setor) {
            return $setor->exists ? $setor : null;
        }

        return $setor ? Setor::query()->find($setor) : null;
    }
}
