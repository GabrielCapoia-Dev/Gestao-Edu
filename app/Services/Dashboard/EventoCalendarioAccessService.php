<?php

namespace App\Services\Dashboard;

use App\Models\Enums\ListaPermissoes;
use App\Models\EventoCalendario;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class EventoCalendarioAccessService
{
    public function podeListar(User $user): bool
    {
        return $this->podeListarGeral($user)
            || $this->podeListarTransporte($user)
            || $this->podeListarProprios($user);
    }

    public function podeListarGeral(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::ListarEventosGeral->label());
    }

    public function podeListarTransporte(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::ListarEventosTransporte->label());
    }

    public function podeListarProprios(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::ListarMeusEventos->label());
    }

    public function aplicarEscopo(User $user, Builder $query): Builder
    {
        if (! $this->podeListar($user)) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->podeListarGeral($user)) {
            return $query;
        }

        $listarTransporte = $this->podeListarTransporte($user);
        $listarProprios = $this->podeListarProprios($user);

        return $query->where(function (Builder $eventos) use ($user, $listarTransporte, $listarProprios): void {
            if ($listarTransporte) {
                $eventos->whereHas(
                    'escolasAgendadas',
                    fn (Builder $escolas): Builder => $escolas->where('precisa_transporte', true),
                );
            }

            if ($listarProprios) {
                $metodo = $listarTransporte ? 'orWhere' : 'where';
                $eventos->{$metodo}('eventos_calendario.criado_por_id', $user->getKey());
            }
        });
    }

    public function podeVisualizar(User $user, EventoCalendario $evento): bool
    {
        if (! $this->podeListar($user)) {
            return false;
        }

        if ($this->podeListarGeral($user)) {
            return true;
        }

        if ($this->podeListarProprios($user) && (int) $evento->criado_por_id === (int) $user->getKey()) {
            return true;
        }

        return $this->podeListarTransporte($user) && $evento->possuiTransporte();
    }
}
