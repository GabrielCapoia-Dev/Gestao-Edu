<?php

namespace App\Policies;

use App\Models\Enums\ListaPermissoes;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;

class ServidorFuncaoAdministrativaPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Gerenciar Vínculos Estruturais de Pessoas')
            || $user->hasPermissionTo('Gerenciar VÃ­nculos Estruturais de Pessoas');
    }

    public function update(User $user, ServidorFuncaoAdministrativa $vinculo): bool
    {
        return $this->create($user)
            && $vinculo->servidor
            && app(ServidorPolicy::class)->view($user, $vinculo->servidor);
    }

    public function delete(User $user, ServidorFuncaoAdministrativa $vinculo): bool
    {
        return $this->update($user, $vinculo);
    }

    public function viewAnyMotoristas(User $user): bool
    {
        return $this->podeGerenciarTransporte($user);
    }

    public function createMotorista(User $user): bool
    {
        return $this->podeGerenciarTransporte($user);
    }

    public function updateMotorista(User $user, ServidorFuncaoAdministrativa $vinculo): bool
    {
        return $this->podeGerenciarTransporte($user) && $this->vinculoEhMotorista($vinculo);
    }

    public function activateMotorista(User $user, ServidorFuncaoAdministrativa $vinculo): bool
    {
        return $this->updateMotorista($user, $vinculo);
    }

    public function deactivateMotorista(User $user, ServidorFuncaoAdministrativa $vinculo): bool
    {
        return $this->updateMotorista($user, $vinculo);
    }

    private function podeGerenciarTransporte(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::GerenciarTransporteDeEventos->label());
    }

    private function vinculoEhMotorista(ServidorFuncaoAdministrativa $vinculo): bool
    {
        $vinculo->loadMissing('funcaoAdministrativa');

        return $vinculo->funcaoAdministrativa?->ehMotorista() ?? false;
    }
}
