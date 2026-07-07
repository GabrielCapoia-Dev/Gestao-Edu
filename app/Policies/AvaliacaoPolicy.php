<?php

namespace App\Policies;

use App\Models\Avaliacao;
use App\Models\User;

class AvaliacaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Avaliações');
    }

    public function view(User $user, Avaliacao $model): bool
    {
        return $user->hasPermissionTo('Listar Avaliações');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Avaliações');
    }

    public function update(User $user, Avaliacao $model): bool
    {
        return $user->hasPermissionTo('Editar Avaliações');
    }

    public function delete(User $user, Avaliacao $model): bool
    {
        return $user->hasPermissionTo('Excluir Avaliações');
    }

    public function follow(User $user): bool
    {
        return $user->hasPermissionTo('Acompanhar Avaliações');
    }

    public function export(User $user): bool
    {
        return $user->hasPermissionLike('exportar avaliacoes');
    }
}
