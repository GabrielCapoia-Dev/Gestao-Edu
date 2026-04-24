<?php

namespace App\Services\Inventario;

use App\Models\Inventario;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class InventarioContextService
{
    public function ehGestorGeral(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // Impacto: esta regra define o escopo global do inventario. Alterar "blank(id_escola)" afeta listagens, romaneios e permissoes de gestores sem escola vinculada.
        return $user->hasRole('Admin') || blank($user->id_escola);
    }

    public function inventarioDoUsuario(?User $user): ?Inventario
    {
        if (! $user || blank($user->id_escola)) {
            return null;
        }

        return Inventario::query()
            ->with('escola')
            ->where('escola_id', $user->id_escola)
            ->first();
    }

    public function resolverInventario(?User $user, ?int $inventarioId = null): ?Inventario
    {
        if (! $user) {
            return null;
        }

        // Impacto: gestor geral precisa escolher inventario; usuario de escola nunca deve receber inventarioId arbitrario da request.
        if ($this->ehGestorGeral($user)) {
            if ($inventarioId) {
                return Inventario::query()->with('escola')->find($inventarioId);
            }

            return null;
        }

        return $this->inventarioDoUsuario($user);
    }

    public function queryInventariosVisiveis(?User $user): Builder
    {
        $query = Inventario::query()->with('escola');

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->ehGestorGeral($user)) {
            return $query;
        }

        if (filled($user->id_escola)) {
            return $query->where('escola_id', $user->id_escola);
        }

        return $query->whereRaw('1 = 0');
    }
}
