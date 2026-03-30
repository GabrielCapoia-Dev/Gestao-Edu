<?php

namespace App\Policies;

use App\Models\ComponenteCurricular;
use App\Models\User;

class ComponenteCurricularPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Componente Curricular');
    }

    public function view(User $user, ComponenteCurricular $model): bool
    {
        return $user->hasPermissionTo('Listar Componente Curricular');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Componente Curricular');
    }

    public function update(User $user, ComponenteCurricular $model): bool
    {
        return $user->hasPermissionTo('Editar Componente Curricular');
    }

    public function delete(User $user, ComponenteCurricular $model): bool
    {
        return $user->hasPermissionTo('Excluir Componente Curricular');
    }
}