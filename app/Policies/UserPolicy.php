<?php

namespace App\Policies;

use App\Models\Escola;
use App\Models\Enums\ListaPermissoes;
use App\Models\User;
use App\Services\UserPresenceService;
use App\Services\PessoaScopeService;
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
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return ! $model->trashed()
            && ($user->hasPermissionTo('Editar Usuarios')
                || $user->hasPermissionTo('Editar Usuários'))
            && $this->podeAcessarUsuario($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        if ($model->trashed() || $this->usuarioProtegido($user, $model)) {
            return false;
        }

        return ($user->hasPermissionTo('Excluir Usuarios')
                || $user->hasPermissionTo('Excluir Usuários'))
            && $this->podeAcessarUsuario($user, $model);
    }

    public function restore(User $user, User $model): bool
    {
        if (! $model->trashed() || $this->usuarioProtegido($user, $model)) {
            return false;
        }

        return ($user->hasPermissionTo('Excluir Usuarios')
                || $user->hasPermissionTo('Excluir Usuários'))
            && $this->podeAcessarUsuario($user, $model);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    public function changeOperationalStatus(User $user, User $model): bool
    {
        if ($model->trashed() || $this->usuarioProtegido($user, $model)) {
            return false;
        }

        return ($user->hasPermissionTo('Editar Usuarios')
                || $user->hasPermissionTo('Editar Usuários'))
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
        if ($target->trashed() || $target->id === 1 || $target->id === $user->id || $target->hasRole('Admin')) {
            return false;
        }

        return $this->applyPermissionsAny($user)
            && $this->podeAcessarUsuario($user, $target);
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

        if ($target && ($target->trashed() || $target->hasRole('Admin') || $target->id === $user->id)) {
            return false;
        }

        return true;
    }

    public function updateAny(User $user): bool
    {
        return $user->hasPermissionTo('Editar Usuários')
            || $user->hasPermissionTo('Editar Usuarios');
    }

    public function resetPassword(User $user, User $target): bool
    {
        if ($target->trashed() || $target->id === 1 || $target->id === $user->id || $target->hasRole('Admin')) {
            return false;
        }

        return $this->resetPasswordAny($user)
            && $this->podeAcessarUsuario($user, $target);
    }

    public function resetPasswordAny(User $user): bool
    {
        return $user->hasRole('Admin')
            || $user->hasPermissionTo(ListaPermissoes::RedefinirSenhasDeUsuarios->label());
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

    public function viewOnlineUsers(User $user): bool
    {
        return $user->hasPermissionTo(UserPresenceService::PERMISSION);
    }

    public function previewProfile(User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Select de Perfis');
    }

    private function podeAcessarUsuario(User $user, User $model): bool
    {
        $scope = app(PessoaScopeService::class);
        $access = app(UserSetorAccessService::class);

        if ($scope->hasGlobalAccess($user)) {
            return true;
        }

        $servidor = $scope->servidorDaPessoa($model);

        if ($servidor && app(ServidorPolicy::class)->view($user, $servidor)) {
            return true;
        }

        $setorId = $model->setor_id;

        if (blank($setorId) && filled($model->id_escola)) {
            $setorId = Escola::query()->whereKey($model->id_escola)->value('setor_id');
        }

        return filled($setorId) && $access->canAccessSetor($user, (int) $setorId);
    }

    private function usuarioProtegido(User $user, User $model): bool
    {
        return $model->id === 1
            || $model->id === $user->id
            || $model->hasRole('Admin');
    }
}
