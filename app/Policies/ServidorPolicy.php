<?php

namespace App\Policies;

use App\Models\Escola;
use App\Models\Servidor;
use App\Models\User;
use App\Services\UserSetorAccessService;

class ServidorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Servidores');
    }

    public function view(User $user, Servidor $servidor): bool
    {
        return $this->viewAny($user) && $this->podeAcessarServidor($user, $servidor);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Servidores');
    }

    public function update(User $user, Servidor $servidor): bool
    {
        return $user->hasPermissionTo('Editar Servidores')
            && $this->podeAcessarServidor($user, $servidor);
    }

    public function delete(User $user, Servidor $servidor): bool
    {
        return $user->hasPermissionTo('Excluir Servidores')
            && $this->podeAcessarServidor($user, $servidor)
            && ! $servidor->professores()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('Excluir Servidores');
    }

    private function podeAcessarServidor(User $user, Servidor $servidor): bool
    {
        $access = app(UserSetorAccessService::class);

        if ($access->hasGlobalAccess($user)) {
            return true;
        }

        $setorId = $servidor->setor_id;

        if (blank($setorId) && filled($servidor->id_escola)) {
            $setorId = Escola::query()->whereKey($servidor->id_escola)->value('setor_id');
        }

        return filled($setorId) && $access->canAccessSetor($user, (int) $setorId);
    }
}
