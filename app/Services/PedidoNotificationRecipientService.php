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

    /** @var array<int, Collection<int, User>> */
    private array $destinatariosPorEscola = [];

    private bool $destinatariosIndexados = false;

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

        $escolaId = (int) $pedido->escola_id;

        if (array_key_exists($escolaId, $this->destinatariosPorEscola)) {
            return $this->destinatariosPorEscola[$escolaId];
        }

        if (! $this->destinatariosIndexados) {
            $this->indexarDestinatarios();
        }

        return $this->destinatariosPorEscola[$escolaId] ?? collect();
    }

    public function podeAcessarCentral(User $user): bool
    {
        return $user->hasPermissionTo(self::PERMISSAO_LISTAR_PEDIDOS)
            && $user->idsEscolasVinculadas() !== [];
    }

    private function indexarDestinatarios(): void
    {
        $this->destinatariosIndexados = true;

        try {
            $usuarios = User::query()
                ->permission(self::PERMISSAO_LISTAR_PEDIDOS)
                ->canAuthenticate()
                ->get();
        } catch (PermissionDoesNotExist) {
            return;
        }

        foreach ($usuarios as $user) {
            foreach ($user->idsEscolasVinculadas() as $escolaId) {
                $escolaId = (int) $escolaId;

                if ($escolaId <= 0) {
                    continue;
                }

                $this->destinatariosPorEscola[$escolaId] ??= collect();
                $this->destinatariosPorEscola[$escolaId]->push($user);
            }
        }
    }
}
