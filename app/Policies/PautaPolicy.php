<?php

namespace App\Policies;

use App\Models\Pauta;
use App\Models\User;
use App\Models\AvaliacaoSnapshotEvento;
use App\Models\AvaliacaoRespostaOperacional;

class PautaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Pautas');
    }

    public function view(User $user, Pauta $model): bool
    {
        return $user->hasPermissionTo('Listar Pautas');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Pautas');
    }

    public function update(User $user, Pauta $model): bool
    {
        return $user->hasPermissionTo('Editar Pautas');
    }

    public function delete(User $user, Pauta $model): bool
    {
        return $user->hasPermissionTo('Excluir Pautas')
            && ! AvaliacaoRespostaOperacional::query()->where('pauta_id', (int) $model->id)->exists()
            && ! AvaliacaoSnapshotEvento::query()->whereIn('avaliacao_id', $model->avaliacoes()->select('avaliacoes.id'))->exists();
    }
}
