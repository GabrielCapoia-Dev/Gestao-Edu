<?php

namespace App\Policies;

use App\Models\Enums\ListaPermissoes;
use App\Models\ReservaVeiculo;
use App\Models\User;

class ReservaVeiculoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::ListarReservasVeiculos->label());
    }

    public function view(User $user, ReservaVeiculo $reserva): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::CriarReservasVeiculos->label());
    }

    public function update(User $user, ReservaVeiculo $reserva): bool
    {
        return $reserva->estaAtiva()
            && $user->hasPermissionTo(ListaPermissoes::EditarReservasVeiculos->label());
    }

    public function cancel(User $user, ReservaVeiculo $reserva): bool
    {
        return $reserva->estaAtiva()
            && $user->hasPermissionTo(ListaPermissoes::CancelarReservasVeiculos->label());
    }

    public function delete(User $user, ReservaVeiculo $reserva): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
