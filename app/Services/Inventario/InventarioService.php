<?php

namespace App\Services\Inventario;

use App\Models\Escola;
use App\Models\Inventario;
use App\Models\User;
use App\Services\UserSetorAccessService;
use DomainException;

class InventarioService
{
    public function __construct(
        protected InventarioContextService $contextService,
    ) {}

    public function criar(int $escolaId, ?string $descricao, User $user): Inventario
    {
        if (! $this->contextService->ehGestorGeral($user)) {
            throw new DomainException('Somente o gestor geral pode criar inventarios.');
        }

        $escola = Escola::query()
            ->where('ativo', true)
            ->find($escolaId);

        if (! $escola) {
            throw new DomainException('A escola informada nao foi encontrada.');
        }

        $access = app(UserSetorAccessService::class);

        if (! $access->hasGlobalAccess($user) && ! $access->canAccessSetor($user, $escola->setor_id)) {
            throw new DomainException('O usuario nao tem permissao para criar inventario nesta escola.');
        }

        if (Inventario::query()->where('escola_id', $escola->getKey())->exists()) {
            throw new DomainException('Esta escola ja possui inventario vinculado.');
        }

        return Inventario::query()->create([
            'escola_id' => $escola->getKey(),
            'setor_id' => $escola->setor_id,
            'nome' => 'Inventario - ' . $escola->nome,
            'ativo' => true,
            'criado_por_id' => $user->getKey(),
        ]);
    }

    public function escolasSemInventario(?User $user = null): array
    {
        $user ??= auth()->user();
        $access = app(UserSetorAccessService::class);

        $query = Escola::query()
            ->where('ativo', true)
            ->whereDoesntHave('inventario');

        if (! $access->hasGlobalAccess($user)) {
            $setorIds = $access->visibleSetorIds($user);

            if ($setorIds === []) {
                return [];
            }

            $query->whereIn('setor_id', $setorIds);
        }

        return $query->orderBy('nome')->pluck('nome', 'id')->toArray();
    }
}
