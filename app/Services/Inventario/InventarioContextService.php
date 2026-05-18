<?php

namespace App\Services\Inventario;

use App\Models\Inventario;
use App\Models\User;
use App\Services\UserSetorAccessService;
use Illuminate\Database\Eloquent\Builder;

class InventarioContextService
{
    public function ehGestorGeral(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return app(UserSetorAccessService::class)->hasGlobalAccess($user)
            || blank($user->id_escola);
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

        if (app(UserSetorAccessService::class)->hasGlobalAccess($user)) {
            if ($inventarioId) {
                return Inventario::query()->with('escola')->find($inventarioId);
            }

            return null;
        }

        if ($inventarioId) {
            return $this->queryInventariosVisiveis($user)->find($inventarioId);
        }

        return $this->inventarioDoUsuario($user);
    }

    public function queryInventariosVisiveis(?User $user): Builder
    {
        $query = Inventario::query()->with('escola');

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $access = app(UserSetorAccessService::class);

        if ($access->hasGlobalAccess($user)) {
            return $query;
        }

        $setorIds = $access->visibleSetorIds($user);
        $legacyEscolaId = filled($user->id_escola) ? (int) $user->id_escola : null;

        if ($setorIds !== []) {
            return $query->where(function (Builder $builder) use ($setorIds, $legacyEscolaId): void {
                $builder->whereIn('setor_id', $setorIds)
                    ->orWhere(function (Builder $legacy) use ($setorIds): void {
                        $legacy->whereNull('setor_id')
                            ->whereHas('escola', fn (Builder $escola): Builder => $escola->whereIn('setor_id', $setorIds));
                    });

                if ($legacyEscolaId !== null) {
                    $builder->orWhere('escola_id', $legacyEscolaId);
                }
            });
        }

        if ($legacyEscolaId !== null) {
            return $query->where('escola_id', $legacyEscolaId);
        }

        return $query->whereRaw('1 = 0');
    }
}
