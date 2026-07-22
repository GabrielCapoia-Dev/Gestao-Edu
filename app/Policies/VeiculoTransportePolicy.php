<?php

namespace App\Policies;

use App\Models\Enums\ListaPermissoes;
use App\Models\User;
use App\Models\VeiculoTransporte;

class VeiculoTransportePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->podeGerenciar($user);
    }

    public function view(User $user, VeiculoTransporte $veiculo): bool
    {
        return $this->podeGerenciar($user);
    }

    public function create(User $user): bool
    {
        return $this->podeGerenciar($user);
    }

    public function update(User $user, VeiculoTransporte $veiculo): bool
    {
        return $this->podeGerenciar($user);
    }

    public function activate(User $user, VeiculoTransporte $veiculo): bool
    {
        return $this->podeGerenciar($user);
    }

    public function deactivate(User $user, VeiculoTransporte $veiculo): bool
    {
        return $this->podeGerenciar($user);
    }

    public function delete(User $user, VeiculoTransporte $veiculo): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function podeGerenciar(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::GerenciarTransporteDeEventos->label());
    }
}
