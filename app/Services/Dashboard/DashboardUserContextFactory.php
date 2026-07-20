<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Services\PessoaScopeService;
use App\Services\UserSetorAccessService;
use App\Support\Dashboard\DashboardUserContext;
use Illuminate\Support\Collection;

class DashboardUserContextFactory
{
    /** @var array<int, DashboardUserContext> */
    private array $contextos = [];

    public function __construct(
        private readonly PessoaScopeService $pessoaScope,
        private readonly UserSetorAccessService $setorAccess,
    ) {}

    public function make(User $user): DashboardUserContext
    {
        $userId = (int) $user->getKey();

        if ($userId > 0 && isset($this->contextos[$userId])) {
            return $this->contextos[$userId];
        }

        $user->loadMissing([
            'roles:id,name,setor_id',
            'roles.permissions:id,name',
            'permissions:id,name',
        ]);

        $vinculos = $this->pessoaScope->vinculosAtivos($user);
        $escolaIds = $this->ids($this->pessoaScope->escolaIdsDosVinculos($user));

        $setorIds = $this->ids($this->pessoaScope->setorIdsDosVinculos($user));

        // Segmentacao por setor representa lotacao exata. A hierarquia e o setor da
        // escola pertencem ao escopo de acesso, nao a afiliacao do destinatario.
        if ($vinculos->isEmpty()) {
            if (filled($user->setor_id)) {
                $setorIds->push((int) $user->setor_id);
            }

            if ($setorIds->isEmpty()) {
                $setorIds = $setorIds->merge($user->roles->pluck('setor_id'));
            }
        }

        $contexto = new DashboardUserContext(
            userId: $userId,
            escopoGlobal: $this->pessoaScope->hasGlobalAccess($user),
            roleIds: $this->ids($user->roles->pluck('id'))->all(),
            permissionIds: $this->ids($user->getAllPermissions()->pluck('id'))->all(),
            funcaoAdministrativaIds: $this->ids(
                $vinculos
                    ->filter(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ativo)
                    ->pluck('funcao_administrativa_id'),
            )->all(),
            escolaIds: $escolaIds->all(),
            setorIds: $this->ids($setorIds)->all(),
            setorVisivelIds: $this->ids($this->setorAccess->visibleSetorIds($user))->all(),
        );

        if ($userId > 0) {
            $this->contextos[$userId] = $contexto;
        }

        return $contexto;
    }

    public function forget(User|int|null $user = null): void
    {
        if ($user === null) {
            $this->contextos = [];

            return;
        }

        $userId = $user instanceof User ? (int) $user->getKey() : $user;

        unset($this->contextos[$userId]);
    }

    private function ids(iterable $ids): Collection
    {
        return collect($ids)
            ->filter(fn ($id): bool => filled($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }
}
