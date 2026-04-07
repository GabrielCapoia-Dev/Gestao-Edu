<?php

namespace App\Services\Inventario;

use App\Models\Escola;
use App\Models\Inventario;
use App\Models\User;
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

        if (Inventario::query()->where('escola_id', $escola->getKey())->exists()) {
            throw new DomainException('Esta escola ja possui inventario vinculado.');
        }

        return Inventario::query()->create([
            'escola_id' => $escola->getKey(),
            'nome' => 'Inventario - ' . $escola->nome,
            'ativo' => true,
            'criado_por_id' => $user->getKey(),
        ]);
    }

    public function escolasSemInventario(): array
    {
        return Escola::query()
            ->where('ativo', true)
            ->whereDoesntHave('inventario')
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }
}
