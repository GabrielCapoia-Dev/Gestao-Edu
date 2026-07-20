<?php

namespace App\Services\Dashboard;

use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\User;
use App\Services\UserSetorAccessService;
use App\Support\Dashboard\DashboardUserContext;
use Illuminate\Database\Eloquent\Builder;

class PublicoAlvoOptionsService
{
    public function __construct(
        private readonly DashboardUserContextFactory $contextFactory,
        private readonly UserSetorAccessService $setorAccess,
    ) {}

    /** @return array<int, string> */
    public function buscarUsuarios(User $ator, string $busca = '', int $limite = 50): array
    {
        $contexto = $this->contextFactory->make($ator);
        $limite = max(1, min($limite, 50));
        $busca = trim($busca);

        $query = User::query()
            ->when($busca !== '', function (Builder $usuarios) use ($busca): void {
                $usuarios->where(function (Builder $campos) use ($busca): void {
                    $campos
                        ->where('users.name', 'like', "%{$busca}%")
                        ->orWhere('users.email', 'like', "%{$busca}%");
                });
            });

        $this->aplicarEscopoAproximado($query, $contexto);

        $candidatos = $query
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->limit($limite)
            ->get(['users.id', 'users.name', 'users.email']);

        return $candidatos
            ->mapWithKeys(fn (User $usuario): array => [
                (int) $usuario->id => $this->rotuloUsuario($usuario),
            ])
            ->all();
    }

    /**
     * @param array<int, int|string> $ids
     * @return array<int, string>
     */
    public function rotulosUsuarios(User $ator, array $ids): array
    {
        $ids = collect($ids)
            ->filter(fn ($id): bool => filled($id) && is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->take(100)
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $contexto = $this->contextFactory->make($ator);
        $query = User::query()->whereKey($ids->all());
        $this->aplicarEscopoAproximado($query, $contexto);

        return $query
            ->get(['users.id', 'users.name', 'users.email'])
            ->sortBy(fn (User $usuario): int => $ids->search((int) $usuario->id))
            ->mapWithKeys(fn (User $usuario): array => [
                (int) $usuario->id => $this->rotuloUsuario($usuario),
            ])
            ->all();
    }

    /** @return array<int, string> */
    public function escolas(User $ator): array
    {
        $contexto = $this->contextFactory->make($ator);

        return Escola::query()
            ->where('ativo', true)
            ->when(
                ! $contexto->escopoGlobal,
                fn (Builder $query): Builder => $query->whereKey($contexto->escolaIds),
            )
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    /** @return array<int, string> */
    public function setores(User $ator): array
    {
        return collect($this->setorAccess->optionsForSelect($ator))
            ->mapWithKeys(fn (string $label, int|string $id): array => [(int) $id => $label])
            ->all();
    }

    /** @return array<int, string> */
    public function roles(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int, string> */
    public function permissoes(): array
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int, string> */
    public function funcoesAdministrativas(): array
    {
        return FuncaoAdministrativa::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    private function aplicarEscopoAproximado(
        Builder $query,
        DashboardUserContext $ator,
    ): Builder {
        if ($ator->escopoGlobal) {
            return $query;
        }

        if ($ator->escolaIds !== []) {
            return $query->where(function (Builder $usuarios) use ($ator): void {
                $usuarios
                    ->whereIn('users.id_escola', $ator->escolaIds)
                    ->orWhereHas(
                        'escolas',
                        fn (Builder $escolas): Builder => $escolas->whereKey($ator->escolaIds),
                    )
                    ->orWhereHas(
                        'servidores',
                        fn (Builder $pessoas): Builder => $pessoas
                            ->where('servidores.status', Servidor::STATUS_ATIVO)
                            ->whereHas(
                                'vinculosAtivos',
                                fn (Builder $vinculos): Builder => $vinculos
                                    ->whereIn(
                                        'servidor_funcao_administrativa.id_escola',
                                        $ator->escolaIds,
                                    ),
                            ),
                    );
            });
        }

        if ($ator->setorVisivelIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $usuarios) use ($ator): void {
            $usuarios
                ->whereIn('users.setor_id', $ator->setorVisivelIds)
                ->orWhereHas(
                    'roles',
                    fn (Builder $roles): Builder => $roles->whereIn(
                        'roles.setor_id',
                        $ator->setorVisivelIds,
                    ),
                )
                ->orWhereHas(
                    'servidores',
                    fn (Builder $pessoas): Builder => $pessoas
                        ->where('servidores.status', Servidor::STATUS_ATIVO)
                        ->whereHas(
                            'vinculosAtivos',
                            fn (Builder $vinculos): Builder => $vinculos->whereIn(
                                'servidor_funcao_administrativa.setor_id',
                                $ator->setorVisivelIds,
                            ),
                        ),
                );
        });
    }

    private function rotuloUsuario(User $usuario): string
    {
        return filled($usuario->email)
            ? "{$usuario->name} — {$usuario->email}"
            : (string) $usuario->name;
    }
}
