<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionLike('listar gestao de estoque');
    }

    public function view(User $user, Item $model): bool
    {
        return $user->hasPermissionLike('listar gestao de estoque');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionLike('listar gestao de estoque');
    }

    public function update(User $user, Item $model): bool
    {
        return $user->hasPermissionLike('listar gestao de estoque');
    }

    public function delete(User $user, Item $model): bool
    {
        return $user->hasPermissionLike('excluir itens');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionLike('excluir itens em massa');
    }
}
