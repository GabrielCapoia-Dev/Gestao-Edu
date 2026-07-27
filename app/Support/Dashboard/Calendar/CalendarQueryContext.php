<?php

namespace App\Support\Dashboard\Calendar;

use App\Models\Enums\ListaPermissoes;
use App\Models\User;
use App\Support\Dashboard\DashboardUserContext;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class CalendarQueryContext
{
    /**
     * @param  list<string>  $categorias
     * @param  list<string>  $status
     * @param  list<string>  $prioridades
     */
    public function __construct(
        public User $user,
        public DashboardUserContext $userContext,
        public CarbonImmutable $inicio,
        public CarbonImmutable $fim,
        public array $categorias = [],
        public array $status = [],
        public array $prioridades = [],
        public ?int $escolaId = null,
        public ?int $setorId = null,
        public ?string $assunto = null,
        public bool $redeCompleta = false,
        public bool $somenteReservasVeiculos = false,
    ) {
        if ($fim->lt($inicio)) {
            throw new InvalidArgumentException('A data final deve ser posterior ou igual à data inicial.');
        }

        $maxDays = max(1, (int) config('dashboard.calendar.max_days', 31));

        if ($inicio->startOfDay()->diffInDays($fim->startOfDay()) + 1 > $maxDays) {
            throw new InvalidArgumentException("O calendário permite consultar no máximo {$maxDays} dias.");
        }

        if ($escolaId && ! $userContext->escopoGlobal && ! in_array($escolaId, $userContext->escolaIds, true)) {
            throw new InvalidArgumentException('A escola selecionada não pertence ao contexto do usuário.');
        }

        if ($setorId && ! $userContext->escopoGlobal && ! in_array($setorId, $userContext->setorVisivelIds, true)) {
            throw new InvalidArgumentException('O setor selecionado não pertence ao contexto do usuário.');
        }

        if ($redeCompleta && ! $user->hasPermissionTo(ListaPermissoes::VisualizarAgendaDeTodaARede->label())) {
            throw new InvalidArgumentException('Seu usuário não possui acesso ao calendário de toda a rede.');
        }

        if ($somenteReservasVeiculos
            && ! $user->hasPermissionTo(ListaPermissoes::ListarReservasVeiculos->label())) {
            throw new InvalidArgumentException('Seu usuário não possui acesso às reservas de veículos.');
        }
    }
}
