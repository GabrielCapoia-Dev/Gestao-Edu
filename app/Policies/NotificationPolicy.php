<?php

namespace App\Policies;

use App\Models\User;
use App\Services\NotificationCenterService;
use App\Services\PedidoNotificationRecipientService;

class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Notificações')
            || app(PedidoNotificationRecipientService::class)->podeAcessarCentral($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Notificações');
    }

    public function sendToDestination(User $user, string $tipo): bool
    {
        $permission = NotificationCenterService::DESTINATION_PERMISSIONS[$tipo] ?? null;

        if (! $permission) {
            return false;
        }

        return $user->hasRole('Admin') || $user->hasPermissionTo($permission);
    }
}
