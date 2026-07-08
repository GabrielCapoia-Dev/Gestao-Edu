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
            || $user->hasPermissionTo('Listar Usuários');
    }

    public function view(User $user, User $model): bool
    {
        return $this->viewAny($user)
            && $this->podeAcessarUsuario($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Usuarios')
            || $user->hasPermissionTo('Criar Usuários');
    }

    public function update(User $user, User $model): bool
    {
        return ($user->hasPermissionTo('Editar Usuarios')
                || $user->hasPermissionTo('Editar Usuários'))
            && $this->podeAcessarUsuario($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return ($user->hasPermissionTo('Excluir Usuarios')
                || $user->hasPermissionTo('Excluir Usuários'))
            && $this->podeAcessarUsuario($user, $model);
    }

    public function viewDashboard(User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Tela de Inicio');
    }

    public function viewPersonalizedPanel(User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Painel Personalizado');
    }

    public function viewSetor(User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Setor do Usuário');
    }

    public function applyPermissions(User $user, User $target): bool
    {
        if ($target->id === $user->id || $target->hasRole('Admin')) {
            return false;
        }

        return $this->applyPermissionsAny($user);
    }

    public function applyPermissionsAny(User $user): bool
    {
        return $user->hasPermissionTo('Aplicar Permissoes');
    }

    public function toggleEmailApproval(User $user, ?User $target = null, string $context = 'table'): bool
    {
        if ($context === 'create') {
            return true;
        }

        if (! $user->hasRole('Admin')) {
            return false;
        }

        if ($target && ($target->hasRole('Admin') || $target->id === $user->id)) {
            return false;
        }

        return true;
    }

    public function updateAny(User $user): bool
    {
        return $user->hasPermissionTo('Editar Usuários')
            || $user->hasPermissionTo('Editar Usuarios');
    }

    public function editSchool(User $user): bool
    {
        return $user->hasPermissionTo('Editar Escola do Usuario');
    }

    public function editSetor(User $user): bool
    {
        return $user->hasPermissionTo('Editar Setor do Usuário');
    }

    public function exportReports(User $user): bool
    {
        return $user->hasPermissionTo('Exportar Relatórios');
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
