<?php

namespace App\Policies;

use App\Models\FeedbackPedido;
use App\Models\User;

class FeedbackPedidoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Feedback de Pedidos');
    }

    public function view(User $user, FeedbackPedido $model): bool
    {
        return $this->viewAny($user);
    }
}