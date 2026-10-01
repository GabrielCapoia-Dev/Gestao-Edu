<?php

namespace App\Services\Dashboard;

use App\Models\Enums\ListaPermissoes;
use App\Models\EventoCalendario;
use App\Models\User;
use App\Services\PessoaScopeService;
use Illuminate\Database\Eloquent\Builder;

class EventoCalendarioAccessService
{
    public function __construct(private readonly PessoaScopeService $scope) {}

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
            return $this->aplicarEscopoAssessoria($user, $query);
        }

        $listarTransporte = $this->podeListarTransporte($user);
        $listarProprios = $this->podeListarProprios($user);

        $query = $query->where(function (Builder $eventos) use ($user, $listarTransporte, $listarProprios): void {
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

        return $this->aplicarEscopoAssessoria($user, $query);
    }

    public function podeVisualizar(User $user, EventoCalendario $evento): bool
    {
        if (! $this->podeListar($user)) {
            return false;
        }

        if (($this->scope->ehAssessoriaPedagogica($user) || $this->scope->ehRh($user))
            && ! $this->podeListarGeral($user)
            && (int) $evento->criado_por_id !== (int) $user->getKey()) {
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

    private function aplicarEscopoAssessoria(User $user, Builder $query): Builder
    {
        if (! $this->scope->ehAssessoriaPedagogica($user) && ! $this->scope->ehRh($user)) {
            return $query;
        }

        // Sem permissão ampla vinda de outro papel, a Assessoria opera
        // exclusivamente os eventos que criou. Eventos de escopo "todas as
        // escolas" podem não ter linhas em escolas_agendadas.
        if (! $this->podeListarGeral($user)) {
            return $query->where('eventos_calendario.criado_por_id', $user->getKey());
        }

        return $query;
    }
}
