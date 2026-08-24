<?php

namespace App\Policies;

use App\Models\Enums\ListaPermissoes;
use App\Models\Servidor;
use App\Models\User;
use App\Services\PessoaScopeService;
use Illuminate\Database\Eloquent\Builder;

class ServidorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::ListarPessoas->label());
    }

    public function applyViewAnyScope(User $user, Builder $query): Builder
    {
        return app(PessoaScopeService::class)->applyPessoaScope($query, $user);
    }

    public function view(User $user, Servidor $servidor): bool
    {
        return $this->viewAny($user) && $this->podeAcessarServidor($user, $servidor);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::CriarPessoas->label());
    }

    public function update(User $user, Servidor $servidor): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::EditarPessoas->label())
            && $this->podeAcessarServidor($user, $servidor);
    }

    public function delete(User $user, Servidor $servidor): bool
    {
        if ($this->pessoaComUsuarioProtegido($user, $servidor)) {
            return false;
        }

        return $user->hasPermissionTo(ListaPermissoes::ExcluirPessoas->label())
            && $this->podeAcessarServidor($user, $servidor);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::ExcluirPessoas->label());
    }

    public function restore(User $user, Servidor $servidor): bool
    {
        if ($this->pessoaComUsuarioProtegido($user, $servidor)) {
            return false;
        }

        return $user->hasPermissionTo(ListaPermissoes::ExcluirPessoas->label())
            && $this->podeAcessarServidor($user, $servidor);
    }

    public function restoreAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function forceDelete(User $user, Servidor $servidor): bool
    {
        if (! $servidor->trashed() || $this->pessoaComUsuarioProtegido($user, $servidor)) {
            return false;
        }

        return $user->hasPermissionTo(ListaPermissoes::ExcluirPessoasDefinitivamente->label())
            && $this->podeAcessarServidor($user, $servidor);
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function editBasicData(User $user, ?Servidor $servidor = null): bool
    {
        if (! $user->hasPermissionTo(ListaPermissoes::EditarDadosDePessoas->label())) {
            return false;
        }

        return $servidor === null || $this->update($user, $servidor);
    }

    public function editTeachingAssignments(User $user, ?Servidor $servidor = null): bool
    {
        if (! $user->hasPermissionTo(ListaPermissoes::EditarTurmasEComponentesDePessoas->label())) {
            return false;
        }

        return $servidor === null || $this->update($user, $servidor);
    }

    public function manageStructure(User $user, ?Servidor $servidor = null): bool
    {
        if (
            ! $user->hasRole('Admin')
            && ! $user->hasPermissionTo(ListaPermissoes::GerenciarVinculosEstruturaisDePessoas->label())
        ) {
            return false;
        }

        if ($servidor && $this->pessoaComUsuarioProtegido($user, $servidor)) {
            return false;
        }

        return $servidor === null || $this->update($user, $servidor);
    }

    public function manageVinculos(User $user, ?Servidor $servidor = null): bool
    {
        return $this->manageStructure($user, $servidor);
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
        if (! $user->hasPermissionTo(ListaPermissoes::ListarPessoas->label())) {
            return false;
        }

        return $servidor === null || $this->podeAcessarServidor($user, $servidor);
    }

    private function podeAcessarServidor(User $user, Servidor $servidor): bool
    {
        return app(PessoaScopeService::class)->canAccessPessoa($user, $servidor);
    }

    private function pessoaComUsuarioProtegido(User $operador, Servidor $servidor): bool
    {
        $alvo = $servidor->user;

        return $alvo
            && ($alvo->id === 1
                || $alvo->id === $operador->id
                || $alvo->hasRole('Admin'));
    }
}
