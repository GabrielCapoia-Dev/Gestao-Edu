<?php

namespace App\Policies;

use App\Models\Enums\ListaPermissoes;
use App\Models\ReservaVeiculo;
use App\Models\User;
use App\Services\PessoaScopeService;

class ReservaVeiculoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::ListarReservasVeiculos->label());
    }

    public function view(User $user, ReservaVeiculo $reserva): bool
    {
        return $this->viewAny($user) && $this->noEscopoEscolar($user, $reserva);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::CriarReservasVeiculos->label());
    }

    public function update(User $user, ReservaVeiculo $reserva): bool
    {
        return $reserva->pertenceAo($user)
            && $reserva->aindaPodeSerAlterada()
            && $this->noEscopoEscolar($user, $reserva)
            && $user->hasPermissionTo(ListaPermissoes::EditarReservasVeiculos->label());
    }

    public function cancel(User $user, ReservaVeiculo $reserva): bool
    {
        return $reserva->pertenceAo($user)
            && $reserva->aindaPodeSerAlterada()
            && $this->noEscopoEscolar($user, $reserva)
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

    private function noEscopoEscolar(User $user, ReservaVeiculo $reserva): bool
    {
        $scope = app(PessoaScopeService::class);
        if ($scope->ehRh($user) && ! $scope->hasGlobalAccess($user)) {
            return $reserva->pertenceAo($user);
        }
        if ($scope->hasGlobalAccess($user)) {
            return true;
        }

        $ids = $scope->escolaIdsDosVinculos($user);
        if ($ids === []) {
            return false;
        }

        $reserva->loadMissing('escolas');
        $escolaIds = $reserva->escolas->modelKeys();
        if ($reserva->escola_id) {
            $escolaIds[] = (int) $reserva->escola_id;
        }

        return $escolaIds !== [] && array_diff($escolaIds, $ids) === [];
    }
}
