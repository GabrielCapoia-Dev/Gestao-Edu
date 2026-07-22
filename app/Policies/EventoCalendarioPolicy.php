<?php

namespace App\Policies;

use App\Models\Enums\EventoCalendarioStatus;
use App\Models\Enums\ListaPermissoes;
use App\Models\EventoCalendario;
use App\Models\User;
use App\Services\Dashboard\EventoCalendarioAccessService;
use Illuminate\Database\Eloquent\Builder;

class EventoCalendarioPolicy
{
    public function __construct(private readonly EventoCalendarioAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->podeListar($user);
    }

    public function view(User $user, EventoCalendario $evento): bool
    {
        return $this->access->podeVisualizar($user, $evento);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::CriarEventos->label());
    }

    public function update(User $user, EventoCalendario $evento): bool
    {
        return $this->view($user, $evento)
            && $user->hasPermissionTo(ListaPermissoes::EditarEventos->label());
    }

    public function delete(User $user, EventoCalendario $evento): bool
    {
        return false;
    }

    public function restore(User $user, EventoCalendario $evento): bool
    {
        return false;
    }

    public function forceDelete(User $user, EventoCalendario $evento): bool
    {
        return false;
    }

    public function publish(User $user, ?EventoCalendario $evento = null): bool
    {
        if (! $evento) {
            return $this->publishCommon($user)
                || $user->hasPermissionTo(ListaPermissoes::PublicarEventosTransporte->label());
        }

        if (! $this->view($user, $evento)) {
            return false;
        }

        $possuiTransporte = $evento->possuiTransporte();

        if ($possuiTransporte && $evento->status === EventoCalendarioStatus::REJEITADO) {
            return false;
        }

        return $possuiTransporte
            ? $user->hasPermissionTo(ListaPermissoes::PublicarEventosTransporte->label())
            : $this->publishCommon($user);
    }

    public function publishCommon(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::PublicarEventos->label());
    }

    public function deactivate(User $user, ?EventoCalendario $evento = null): bool
    {
        if (! $evento) {
            return $user->hasPermissionTo(ListaPermissoes::DesativarEventos->label())
                || $user->hasPermissionTo(ListaPermissoes::DesativarEventosTransporte->label());
        }

        if (! $this->view($user, $evento)) {
            return false;
        }

        return $evento->possuiTransporte()
            ? $user->hasPermissionTo(ListaPermissoes::DesativarEventosTransporte->label())
            : $user->hasPermissionTo(ListaPermissoes::DesativarEventos->label());
    }

    public function reject(User $user, ?EventoCalendario $evento = null): bool
    {
        if (! $evento) {
            return $user->hasPermissionTo(ListaPermissoes::RejeitarEventosTransporte->label());
        }

        return $this->view($user, $evento)
            && $evento->possuiTransporte()
            && $user->hasPermissionTo(ListaPermissoes::RejeitarEventosTransporte->label());
    }

    public function manageTransport(User $user, ?EventoCalendario $evento = null): bool
    {
        if (! $user->hasPermissionTo(ListaPermissoes::GerenciarTransporteDeEventos->label())) {
            return false;
        }

        return ! $evento || ($this->view($user, $evento) && $evento->possuiTransporte());
    }

    public function manageAudience(User $user, ?EventoCalendario $evento = null): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::GerenciarPublicoAlvoDeEventos->label())
            && (! $evento || $this->view($user, $evento));
    }

    public function applyViewAnyScope(User $user, Builder $query): Builder
    {
        return $this->access->aplicarEscopo($user, $query);
    }
}
