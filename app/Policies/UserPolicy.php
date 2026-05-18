<?php

namespace App\Policies;

use App\Models\Escola;
use App\Models\User;
use App\Services\UserSetorAccessService;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Usuarios')
            || $user->hasPermissionTo('Listar Usuários')
            || $user->hasPermissionTo('Listar UsuÃ¡rios');
    }

    public function view(User $user, User $model): bool
    {
        return $this->viewAny($user)
            && $this->podeAcessarUsuario($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Usuarios')
            || $user->hasPermissionTo('Criar Usuários')
            || $user->hasPermissionTo('Criar UsuÃ¡rios');
    }

    public function update(User $user, User $model): bool
    {
        return ($user->hasPermissionTo('Editar Usuarios')
                || $user->hasPermissionTo('Editar Usuários')
                || $user->hasPermissionTo('Editar UsuÃ¡rios'))
            && $this->podeAcessarUsuario($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return ($user->hasPermissionTo('Excluir Usuarios')
                || $user->hasPermissionTo('Excluir Usuários')
                || $user->hasPermissionTo('Excluir UsuÃ¡rios'))
            && $this->podeAcessarUsuario($user, $model);
    }

    private function podeAcessarUsuario(User $user, User $model): bool
    {
        $access = app(UserSetorAccessService::class);

        if ($access->hasGlobalAccess($user)) {
            return true;
        }

        $setorId = $model->setor_id;

        if (blank($setorId) && filled($model->id_escola)) {
            $setorId = Escola::query()->whereKey($model->id_escola)->value('setor_id');
        }

        return filled($setorId) && $access->canAccessSetor($user, (int) $setorId);
    }
}
