<?php

namespace App\Policies;

use App\Models\Alternativa;
use App\Models\User;
use App\Models\AvaliacaoSnapshotEvento;
use App\Models\AvaliacaoRespostaOperacional;

class AlternativaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Alternativas');
    }

    public function view(User $user, Alternativa $model): bool
    {
        return $user->hasPermissionTo('Listar Alternativas');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Alternativas');
    }

    public function update(User $user, Alternativa $model): bool
    {
        return $user->hasPermissionTo('Editar Alternativas');
    }

    public function delete(User $user, Alternativa $model): bool
    {
        return $user->hasPermissionTo('Excluir Alternativas')
            && ! AvaliacaoRespostaOperacional::query()->where('alternativa_id', (int) $model->id)->exists()
            && ! AvaliacaoSnapshotEvento::query()
                ->whereIn('avaliacao_id', $model->pautas()
                    ->join('avaliacao_pauta', 'avaliacao_pauta.pauta_id', '=', 'pautas.id')
                    ->select('avaliacao_pauta.avaliacao_id'))
                ->exists();
    }
}
