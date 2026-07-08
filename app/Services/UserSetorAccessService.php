<?php

namespace App\Services;

use App\Models\Escola;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UserSetorAccessService
{
    public const GLOBAL_SCOPE_PERMISSION = 'Acessar Escopo Global de Setores';

    /** @var array<int, bool> */
    private array $globalAccessByUser = [];

    /** @var array<int, int|null> */
    private array $primarySetorByUser = [];

    /** @var array<int, array<int, int>> */
    private array $visibleSetoresByUser = [];

    public function __construct(private readonly SetorHierarchyService $hierarchy)
    {
    }

    public function hasGlobalAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $userId = (int) $user->getKey();

        return $this->globalAccessByUser[$userId] ??= $user->hasRole('Admin')
            || Gate::forUser($user)->allows('accessGlobalScope', Setor::class)
            || Gate::forUser($user)->allows('admin-only');
    }

    public function primarySetorId(?User $user): ?int
    {
        if (! $user) {
            return null;
        }

        $userId = (int) $user->getKey();

        if (array_key_exists($userId, $this->primarySetorByUser)) {
            return $this->primarySetorByUser[$userId];
        }

        if (filled($user->setor_id)) {
            return $this->primarySetorByUser[$userId] = (int) $user->setor_id;
        }

        if (filled($user->id_escola)) {
            $setorId = Escola::query()
                ->whereKey($user->id_escola)
                ->value('setor_id');

            if (filled($setorId)) {
                return $this->primarySetorByUser[$userId] = (int) $setorId;
            }
        }

        $roleSetores = $user->roles()
            ->whereNotNull('roles.setor_id')
            ->pluck('roles.setor_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        return $this->primarySetorByUser[$userId] = $roleSetores->count() === 1 ? $roleSetores->first() : null;
    }

    public function visibleSetorIds(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $userId = (int) $user->getKey();

        if (array_key_exists($userId, $this->visibleSetoresByUser)) {
            return $this->visibleSetoresByUser[$userId];
        }

        if ($this->hasGlobalAccess($user)) {
            return $this->visibleSetoresByUser[$userId] = Setor::query()
                ->where('ativo', true)
                ->orderBy('path')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return $this->visibleSetoresByUser[$userId] = $this->hierarchy->selfAndDescendantIds($this->primarySetorId($user));
    }

    public function canAccessSetor(?User $user, ?int $setorId): bool
    {
        if (! $user || blank($setorId)) {
            return false;
        }

        if ($this->hasGlobalAccess($user)) {
            return true;
        }

        return in_array((int) $setorId, $this->visibleSetorIds($user), true);
    }

    public function applySetorScope(Builder $query, ?User $user, string $column = 'setor_id'): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasGlobalAccess($user)) {
            return $query;
        }

        $ids = $this->visibleSetorIds($user);

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $ids);
    }

    public function visibleSetorQuery(?User $user): Builder
    {
        return $this->applySetorScope(Setor::query()->where('ativo', true), $user, 'id');
    }

    public function optionsForSelect(?User $user, ?int $rootSetorId = null): array
    {
        $ids = $this->visibleSetorIds($user);

        if ($rootSetorId) {
            $allowedUnderRoot = $this->hierarchy->selfAndDescendantIds($rootSetorId);
            $ids = array_values(array_intersect($ids, $allowedUnderRoot));
        }

        if ($ids === []) {
            return [];
        }

        return $this->hierarchy->labelsForOptions($ids);
    }

    public function assertCanUseSetor(?User $user, ?int $setorId): void
    {
        if ($this->canAccessSetor($user, $setorId)) {
            return;
        }

        throw ValidationException::withMessages([
            'setor_id' => 'Você não tem permissão para usar este setor.',
        ]);
    }
}
