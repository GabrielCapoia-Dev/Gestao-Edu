<?php

namespace App\Policies;

use App\Models\Escola;
use App\Models\Servidor;
use App\Models\User;
use App\Services\PessoaScopeService;
use App\Services\ServidorService;
use App\Services\UserSetorAccessService;
use Illuminate\Database\Eloquent\Builder;

class ServidorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Servidores');
    }

    public function applyViewAnyScope(User $user, Builder $query): Builder
    {
        return app(ServidorService::class)->aplicarEscopoVisibilidade($query, $user);
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
            && app(ServidorService::class)->pessoaPodeSerExcluida($servidor);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('Excluir Servidores');
    }

    public function manageVinculos(User $user, ?Servidor $servidor = null): bool
    {
        if (! $user->hasPermissionTo('Editar Servidores')) {
            return false;
        }

        return $servidor === null || $this->podeAcessarServidor($user, $servidor);
    }

    public function createUserAccess(User $user, ?Servidor $servidor = null): bool
    {
        if (! $user->hasPermissionTo('Criar Usuários') && ! $user->hasPermissionTo('Criar Usuarios')) {
            return false;
        }

        return $servidor === null || $this->podeAcessarServidor($user, $servidor);
    }

    public function viewPedagogicalProfile(User $user, ?Servidor $servidor = null): bool
    {
        if (! $user->hasPermissionTo('Listar Professores')) {
            return false;
        }

        return $servidor === null || $this->podeAcessarServidor($user, $servidor);
    }

    private function podeAcessarServidor(User $user, Servidor $servidor): bool
    {
        $scope = app(PessoaScopeService::class);
        $access = app(UserSetorAccessService::class);

        if ($scope->hasGlobalAccess($user)) {
            return true;
        }

        $escolaIdsVisiveis = $scope->escolaIdsDosVinculos($user);

        $servidor->loadMissing('vinculosAtivos');

        foreach ($servidor->vinculosAtivos as $vinculo) {
            if (
                filled($vinculo->id_escola)
                && in_array((int) $vinculo->id_escola, $escolaIdsVisiveis, true)
            ) {
                return true;
            }

            if (
                filled($vinculo->setor_id)
                && $scope->canAccessSetor($user, (int) $vinculo->setor_id)
            ) {
                return true;
            }
        }

        if (
            filled($servidor->id_escola)
            && in_array((int) $servidor->id_escola, $escolaIdsVisiveis, true)
        ) {
            return true;
        }

        $setorId = $servidor->setor_id;

        if (blank($setorId) && filled($servidor->id_escola)) {
            $setorId = Escola::query()->whereKey($servidor->id_escola)->value('setor_id');
        }

        return filled($setorId) && $access->canAccessSetor($user, (int) $setorId);
    }
}
