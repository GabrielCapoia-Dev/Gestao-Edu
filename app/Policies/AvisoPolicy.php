<?php

namespace App\Policies;

use App\Models\Aviso;
use App\Models\Enums\ListaPermissoes;
use App\Models\User;
use App\Services\Dashboard\PublicoAlvoService;
use Illuminate\Database\Eloquent\Builder;

class AvisoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::ListarAvisos->label());
    }

    public function view(User $user, Aviso $aviso): bool
    {
        return $this->viewAny($user) && $this->estaNoEscopoGerenciavel($user, $aviso) && $this->proprioAvisoSeRh($user, $aviso);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::CriarAvisos->label());
    }

    public function update(User $user, Aviso $aviso): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::EditarAvisos->label())
            && $this->estaNoEscopoGerenciavel($user, $aviso)
            && $this->proprioAvisoSeRh($user, $aviso);
    }

    public function delete(User $user, Aviso $aviso): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::ExcluirAvisos->label())
            && $this->estaNoEscopoGerenciavel($user, $aviso)
            && $this->proprioAvisoSeRh($user, $aviso);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::ExcluirAvisos->label());
    }

    public function publish(User $user, ?Aviso $aviso = null): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::PublicarAvisos->label())
            && (! $aviso || ($this->estaNoEscopoGerenciavel($user, $aviso) && $this->proprioAvisoSeRh($user, $aviso)));
    }

    public function manageAudience(User $user, ?Aviso $aviso = null): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::GerenciarPublicoAlvoDeAvisos->label())
            && (! $aviso || ($this->estaNoEscopoGerenciavel($user, $aviso) && $this->proprioAvisoSeRh($user, $aviso)));
    }

    public function duplicate(User $user, Aviso $aviso): bool
    {
        return $this->create($user)
            && $this->view($user, $aviso)
            && $this->manageAudience($user, $aviso);
    }

    public function restore(User $user, Aviso $aviso): bool
    {
        return false;
    }

    public function forceDelete(User $user, Aviso $aviso): bool
    {
        return false;
    }

    public function applyViewAnyScope(User $user, Builder $query): Builder
    {
        if (! $this->viewAny($user)) {
            return $query->whereRaw('1 = 0');
        }

        $query = $query->whereIn(
            $query->getModel()->qualifyColumn('publico_alvo_id'),
            app(PublicoAlvoService::class)
                ->queryGerenciavelPor($user)
                ->select('publicos_alvo.id'),
        );
        if (app(\App\Services\PessoaScopeService::class)->ehRh($user)) {
            $query->where('criado_por_id', $user->getKey());
        }
        return $query;
    }

    private function estaNoEscopoGerenciavel(User $user, Aviso $aviso): bool
    {
        $aviso->loadMissing('publicoAlvo');

        return $aviso->publicoAlvo
            ? app(PublicoAlvoService::class)->podeGerenciar($aviso->publicoAlvo, $user)
            : false;
    }

    private function proprioAvisoSeRh(User $user, Aviso $aviso): bool
    {
        $scope = app(\App\Services\PessoaScopeService::class);
        return ! ($scope->ehRh($user) && ! $scope->hasGlobalAccess($user))
            || (int) $aviso->criado_por_id === (int) $user->getKey();
    }
}
