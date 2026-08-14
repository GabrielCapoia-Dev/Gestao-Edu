<?php

namespace App\Policies;

use App\Models\LocalTrabalho;
use App\Models\Lotacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class LotacaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Escolas');
    }

    public function applyViewAnyScope(User $user, Builder $query): Builder
    {
        $locaisVisiveis = app(LocalTrabalhoPolicy::class)
            ->applyViewAnyScope($user, LocalTrabalho::query())
            ->select('id');

        return $query->whereIn('escola_id', $locaisVisiveis);
    }

    public function view(User $user, Lotacao $model): bool
    {
        return $this->viewAny($user) && $this->localEstaNoEscopo($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Editar Escolas');
    }

    public function update(User $user, Lotacao $model): bool
    {
        return $user->hasPermissionTo('Editar Escolas') && $this->localEstaNoEscopo($user, $model);
    }

    public function delete(User $user, Lotacao $model): bool
    {
        return $user->hasPermissionTo('Editar Escolas') && $this->localEstaNoEscopo($user, $model);
    }

    private function localEstaNoEscopo(User $user, Lotacao $model): bool
    {
        return app(LocalTrabalhoPolicy::class)
            ->applyViewAnyScope($user, LocalTrabalho::query())
            ->whereKey($model->escola_id)
            ->exists();
    }
}
