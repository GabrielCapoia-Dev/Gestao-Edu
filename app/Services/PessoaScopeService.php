<?php

namespace App\Services;

use App\Models\Escola;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class PessoaScopeService
{
    /** @var array<int, bool> */
    private array $globalAccess = [];

    /** @var array<int, Servidor|null> */
    private array $servidores = [];

    /** @var array<int, Collection> */
    private array $vinculos = [];

    /** @var array<int, bool> */
    private array $equipesGestoras = [];

    /** @var array<int, array<int, int>> */
    private array $escolas = [];

    /** @var array<int, array<int, int>> */
    private array $setores = [];

    /** @var array<int, array<int, int>> */
    private array $setoresVisiveis = [];

    public function __construct(private readonly SetorHierarchyService $hierarchy) {}

    public function hasGlobalAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $userId = (int) $user->getKey();

        return $this->globalAccess[$userId] ??= $user->hasRole('Admin')
            || Gate::forUser($user)->allows('accessGlobalScope', Setor::class)
            || Gate::forUser($user)->allows('admin-only');
    }

    public function servidorDaPessoa(?User $user): ?Servidor
    {
        if (! $user) {
            return null;
        }

        $userId = (int) $user->getKey();

        if (array_key_exists($userId, $this->servidores)) {
            return $this->servidores[$userId];
        }

        $pessoas = Servidor::query()
            ->where('user_id', $user->getKey())
            ->where('status', Servidor::STATUS_ATIVO)
            ->orderBy('id')
            ->limit(2)
            ->get();

        return $this->servidores[$userId] = $pessoas->count() === 1 ? $pessoas->first() : null;
    }

    public function vinculosAtivos(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        $userId = (int) $user->getKey();

        if (isset($this->vinculos[$userId])) {
            return $this->vinculos[$userId];
        }

        $servidor = $this->servidorDaPessoa($user);

        if (! $servidor) {
            return collect();
        }

        return $this->vinculos[$userId] = $servidor->vinculosAtivos()
            ->with([
                'funcaoAdministrativa',
                'setor:id,nome,contexto,exige_vinculo_escola',
                'escola:id,nome,setor_id',
            ])
            ->get();
    }

    public function ehEquipeGestora(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $userId = (int) $user->getKey();

        return $this->equipesGestoras[$userId] ??= $this->temVinculoGestorEmQualquerPessoa($user);
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
        if (! $user) {
            return [];
        }

        $userId = (int) $user->getKey();

        return $this->setores[$userId] ??= $this->vinculosAtivos($user)
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

        $userId = (int) $user->getKey();

        if (isset($this->escolas[$userId])) {
            return $this->escolas[$userId];
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
            return $this->escolas[$userId] = $vinculosGestores->isNotEmpty()
                && count($escolasGestoras) === 1
                && count($escolasVinculo) === 1
                && $escolasGestoras === $escolasVinculo
                ? $escolasGestoras
                : [];
        }

        if ($escolasVinculo !== []) {
            return $this->escolas[$userId] = $escolasVinculo;
        }

        return $this->escolas[$userId] = $user->idsEscolasVinculadas();
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

    public function applyPessoaScope(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasGlobalAccess($user)) {
            return $query;
        }

        $escolaIds = $this->escolaIdsDosVinculos($user);

        if ($escolaIds !== []) {
            return $query->where(function (Builder $pessoas) use ($escolaIds): void {
                $pessoas
                    ->whereHas('professores', fn (Builder $professores): Builder => $professores
                        ->where('ativo', true)
                        ->whereIn('id_escola', $escolaIds))
                    ->orWhereHas('vinculosAtivos', fn (Builder $vinculos): Builder => $vinculos
                        ->whereIn('id_escola', $escolaIds));
            });
        }

        if ($this->usaEscopoPorVinculos($user)) {
            return $query->whereRaw('1 = 0');
        }

        $setorIds = $this->visibleSetorIds($user);

        if ($setorIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('vinculosAtivos', fn (Builder $vinculos): Builder => $vinculos
            ->whereIn('setor_id', $setorIds));
    }

    public function canAccessPessoa(?User $user, Servidor $pessoa): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->hasGlobalAccess($user)) {
            return true;
        }

        if ($pessoa->trashed()) {
            return $this->applyPessoaArchivedScope(
                Servidor::withTrashed()->whereKey($pessoa->getKey()),
                $user,
            )->exists();
        }

        $query = Servidor::query()->whereKey($pessoa->getKey());

        return $this->applyPessoaScope($query, $user)->exists();
    }

    private function applyPessoaArchivedScope(Builder $query, User $user): Builder
    {
        $escolaIds = $this->escolaIdsDosVinculos($user);

        if ($escolaIds !== []) {
            return $query->where(function (Builder $pessoas) use ($escolaIds): void {
                $pessoas
                    ->whereIn('id_escola', $escolaIds)
                    ->orWhereHas('professores', fn (Builder $professores): Builder => $professores
                        ->whereIn('id_escola', $escolaIds))
                    ->orWhereHas('vinculos', fn (Builder $vinculos): Builder => $vinculos
                        ->whereIn('id_escola', $escolaIds));
            });
        }

        if ($this->usaEscopoPorVinculos($user)) {
            return $query->whereRaw('1 = 0');
        }

        $setorIds = $this->visibleSetorIds($user);

        if ($setorIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $pessoas) use ($setorIds): void {
            $pessoas
                ->whereIn('setor_id', $setorIds)
                ->orWhereHas('vinculos', fn (Builder $vinculos): Builder => $vinculos
                    ->whereIn('setor_id', $setorIds));
        });
    }

    /** @return array<int, int> */
    public function visibleSetorIds(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $userId = (int) $user->getKey();

        if (isset($this->setoresVisiveis[$userId])) {
            return $this->setoresVisiveis[$userId];
        }

        if ($this->hasGlobalAccess($user)) {
            return $this->setoresVisiveis[$userId] = Setor::query()
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
                return $this->setoresVisiveis[$userId] = [];
            }

            return $this->setoresVisiveis[$userId] = $this->vinculosAtivos($user)
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
            return $this->setoresVisiveis[$userId] = collect($setorIds)
                ->flatMap(fn (int $setorId): array => $this->hierarchy->selfAndDescendantIds($setorId))
                ->unique()
                ->values()
                ->all();
        }

        $legacyPrimary = $this->legacyPrimarySetorId($user);

        return $this->setoresVisiveis[$userId] = $this->hierarchy->selfAndDescendantIds($legacyPrimary);
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
