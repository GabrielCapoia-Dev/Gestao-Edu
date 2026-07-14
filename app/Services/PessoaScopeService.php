<?php

namespace App\Services;

use App\Models\Escola;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Gate;

class PessoaScopeService
{
    public function __construct(private readonly SetorHierarchyService $hierarchy) {}

    public function hasGlobalAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('Admin')
            || Gate::forUser($user)->allows('accessGlobalScope', Setor::class)
            || Gate::forUser($user)->allows('admin-only');
    }

    public function servidorDaPessoa(?User $user): ?Servidor
    {
        if (! $user) {
            return null;
        }

        $pessoas = Servidor::query()
            ->where('user_id', $user->getKey())
            ->where('status', Servidor::STATUS_ATIVO)
            ->orderBy('id')
            ->limit(2)
            ->get();

        return $pessoas->count() === 1 ? $pessoas->first() : null;
    }

    public function vinculosAtivos(?User $user)
    {
        $servidor = $this->servidorDaPessoa($user);

        if (! $servidor) {
            return collect();
        }

        return $servidor->vinculosAtivos()
            ->with([
                'funcaoAdministrativa',
                'setor:id,nome,contexto,exige_vinculo_escola',
                'escola:id,nome,setor_id',
            ])
            ->get();
    }

    public function ehEquipeGestora(?User $user): bool
    {
        return $this->temVinculoGestorEmQualquerPessoa($user);
    }

    public function usaEscopoPorVinculos(?User $user): bool
    {
        return $this->ehEquipeGestora($user) || $this->vinculosAtivos($user)->isNotEmpty();
    }

    public function primarySetorId(?User $user): ?int
    {
        $vinculos = $this->vinculosAtivos($user);

        if ($this->ehEquipeGestora($user)) {
            if ($this->escolaIdsDosVinculos($user) === []) {
                return null;
            }

            $setoresGestores = $vinculos
                ->filter(fn ($vinculo): bool => $vinculo->funcaoAdministrativa?->tipoEquipeGestora() !== null)
                ->pluck('setor_id')
                ->filter(fn ($id): bool => filled($id))
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();

            return $setoresGestores->count() === 1 ? $setoresGestores->first() : null;
        }

        if ($vinculos->isNotEmpty()) {
            $setorId = $vinculos->pluck('setor_id')->filter()->map(fn ($id): int => (int) $id)->first();

            if ($setorId) {
                return $setorId;
            }
        }

        return $this->legacyPrimarySetorId($user);
    }

    /** @return array<int, int> */
    public function setorIdsDosVinculos(?User $user): array
    {
        return $this->vinculosAtivos($user)
            ->pluck('setor_id')
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, int> */
    public function escolaIdsDosVinculos(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $vinculos = $this->vinculosAtivos($user);
        $escolasVinculo = $vinculos
            ->pluck('id_escola')
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $vinculosGestores = $vinculos
            ->filter(fn ($vinculo): bool => $vinculo->funcaoAdministrativa?->tipoEquipeGestora() !== null);
        $escolasGestoras = $vinculosGestores
            ->pluck('id_escola')
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $temVinculoGestor = $this->temVinculoGestorEmQualquerPessoa($user);

        // Equipe gestora opera em uma unica escola. Massa legada ambigua falha fechada.
        if ($temVinculoGestor) {
            return $vinculosGestores->isNotEmpty()
                && count($escolasGestoras) === 1
                && count($escolasVinculo) === 1
                && $escolasGestoras === $escolasVinculo
                ? $escolasGestoras
                : [];
        }

        if ($escolasVinculo !== []) {
            return $escolasVinculo;
        }

        return $user->idsEscolasVinculadas();
    }

    public function canAccessEscola(?User $user, ?int $escolaId): bool
    {
        if (! $user || blank($escolaId)) {
            return false;
        }

        if ($this->hasGlobalAccess($user)) {
            return true;
        }

        return in_array((int) $escolaId, $this->escolaIdsDosVinculos($user), true);
    }

    public function applyEscolaScope(
        Builder|QueryBuilder $query,
        ?User $user,
        string $column = 'id_escola',
    ): Builder|QueryBuilder {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasGlobalAccess($user)) {
            return $query;
        }

        $ids = $this->escolaIdsDosVinculos($user);

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $ids);
    }

    /** @return array<int, int> */
    public function visibleSetorIds(?User $user): array
    {
        if (! $user) {
            return [];
        }

        if ($this->hasGlobalAccess($user)) {
            return Setor::query()
                ->where('ativo', true)
                ->orderBy('path')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        if ($this->ehEquipeGestora($user)) {
            $escolaIds = $this->escolaIdsDosVinculos($user);

            if ($escolaIds === []) {
                return [];
            }

            return $this->vinculosAtivos($user)
                ->filter(fn ($vinculo): bool => $vinculo->funcaoAdministrativa?->tipoEquipeGestora() !== null)
                ->whereIn('id_escola', $escolaIds)
                ->pluck('setor_id')
                ->filter(fn ($id): bool => filled($id))
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $setorIds = $this->setorIdsDosVinculos($user);

        if ($setorIds !== []) {
            return collect($setorIds)
                ->flatMap(fn (int $setorId): array => $this->hierarchy->selfAndDescendantIds($setorId))
                ->unique()
                ->values()
                ->all();
        }

        $legacyPrimary = $this->legacyPrimarySetorId($user);

        return $this->hierarchy->selfAndDescendantIds($legacyPrimary);
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

    public function ehGestorGeral(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->hasGlobalAccess($user)) {
            return true;
        }

        if ($this->ehEquipeGestora($user) && $this->escolaIdsDosVinculos($user) === []) {
            return false;
        }

        $setorId = $this->primarySetorId($user);

        if (! $setorId) {
            return false;
        }

        return Setor::query()->whereKey($setorId)->where('is_default_root', true)->exists();
    }

    private function legacyPrimarySetorId(?User $user): ?int
    {
        if (! $user) {
            return null;
        }

        if (filled($user->setor_id)) {
            return (int) $user->setor_id;
        }

        if (filled($user->id_escola)) {
            $setorId = Escola::query()->whereKey($user->id_escola)->value('setor_id');

            if (filled($setorId)) {
                return (int) $setorId;
            }
        }

        $roleSetores = $user->roles()
            ->whereNotNull('roles.setor_id')
            ->pluck('roles.setor_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        return $roleSetores->count() === 1 ? $roleSetores->first() : null;
    }

    private function temVinculoGestorEmQualquerPessoa(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return Servidor::query()
            ->where('user_id', $user->getKey())
            ->whereHas(
                'vinculosAtivos.funcaoAdministrativa',
                fn (Builder $funcoes): Builder => $funcoes->equipeGestora(),
            )
            ->exists();
    }
}
