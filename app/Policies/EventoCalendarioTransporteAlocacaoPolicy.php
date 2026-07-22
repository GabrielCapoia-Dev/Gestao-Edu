<?php

namespace App\Policies;

use App\Models\EventoCalendario;
use App\Models\EventoCalendarioTransporteAlocacao;
use App\Models\User;

class EventoCalendarioTransporteAlocacaoPolicy
{
    public function __construct(private readonly EventoCalendarioPolicy $eventos) {}

    public function viewAny(User $user): bool
    {
        return $this->eventos->viewAny($user);
    }

    public function view(User $user, EventoCalendarioTransporteAlocacao $alocacao): bool
    {
        return $this->eventos->view($user, $alocacao->evento);
    }

    public function create(User $user, EventoCalendario $evento): bool
    {
        return $this->eventos->manageTransport($user, $evento);
    }

    public function remove(User $user, EventoCalendarioTransporteAlocacao $alocacao): bool
    {
        return $this->eventos->manageTransport($user, $alocacao->evento);
    }

    public function delete(User $user, EventoCalendarioTransporteAlocacao $alocacao): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, EventoCalendarioTransporteAlocacao $alocacao): bool
    {
        return false;
    }

    public function forceDelete(User $user, EventoCalendarioTransporteAlocacao $alocacao): bool
    {
        return false;
    }
}
