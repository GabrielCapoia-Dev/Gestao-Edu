<?php

namespace App\Policies;

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
}
