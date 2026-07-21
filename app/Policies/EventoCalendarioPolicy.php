<?php

namespace App\Policies;

use App\Models\EventoCalendario;
use App\Models\Enums\ListaPermissoes;
use App\Models\User;
use App\Services\Dashboard\PublicoAlvoService;
use Illuminate\Database\Eloquent\Builder;

class EventoCalendarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::ListarEventos->label());
    }

    public function view(User $user, EventoCalendario $evento): bool
    {
        return $this->viewAny($user) && $this->estaNoContexto($user, $evento);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::CriarEventos->label());
    }

    public function update(User $user, EventoCalendario $evento): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::EditarEventos->label()) && $this->estaNoContexto($user, $evento);
    }

    public function delete(User $user, EventoCalendario $evento): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::ExcluirEventos->label()) && $this->estaNoContexto($user, $evento);
    }

    public function restore(User $user, EventoCalendario $evento): bool
    {
        return $this->delete($user, $evento);
    }

    public function forceDelete(User $user, EventoCalendario $evento): bool
    {
        return false;
    }

    public function publish(User $user, ?EventoCalendario $evento = null): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::PublicarEventos->label())
            && (! $evento || $this->estaNoContexto($user, $evento));
    }

    public function manageAudience(User $user, ?EventoCalendario $evento = null): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::GerenciarPublicoAlvoDeEventos->label())
            && (! $evento || $this->estaNoContexto($user, $evento));
    }

    public function duplicate(User $user, EventoCalendario $evento): bool
    {
        return $this->create($user)
            && $this->view($user, $evento);
    }

    public function applyViewAnyScope(User $user, Builder $query): Builder
    {
        if (! $this->viewAny($user)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn(
            'eventos_calendario.publico_alvo_id',
            app(PublicoAlvoService::class)
                ->queryGerenciavelPor($user)
                ->select('publicos_alvo.id'),
        );
    }

    private function estaNoContexto(User $user, EventoCalendario $evento): bool
    {
        return app(PublicoAlvoService::class)->podeGerenciar(
            $evento->publicoAlvo,
            $user,
        );
    }
}
