<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\User;
use App\Support\UserActorSnapshot;
use Illuminate\Support\Collection;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class PedidoNotificationRecipientService
{
    public const PERMISSAO_LISTAR_PEDIDOS = 'Listar Pedidos';

    public function podeReceber(User $user, Pedido $pedido): bool
    {
        if (
            ! UserActorSnapshot::canReceiveNotification($user)
            || ! $user->hasPermissionTo(self::PERMISSAO_LISTAR_PEDIDOS)
            || blank($pedido->escola_id)
        ) {
            return false;
        }

        return in_array(
            (int) $pedido->escola_id,
            $user->idsEscolasVinculadas(),
            true,
        );
    }

    /**
     * Retorna somente usuários que podem listar pedidos e possuem vínculo
     * com a escola do pedido. Usuários sem vínculo escolar falham fechados.
     *
     * @return Collection<int, User>
     */
    public function destinatarios(Pedido $pedido): Collection
    {
        if (blank($pedido->escola_id)) {
            return collect();
        }

        try {
            return User::permission(self::PERMISSAO_LISTAR_PEDIDOS)
                ->get()
                ->filter(fn (User $user): bool => $this->podeReceber($user, $pedido))
                ->values();
        } catch (PermissionDoesNotExist) {
            return collect();
        }
    }

    public function podeAcessarCentral(User $user): bool
    {
        return $user->hasPermissionTo(self::PERMISSAO_LISTAR_PEDIDOS)
            && $user->idsEscolasVinculadas() !== [];
    }
}
