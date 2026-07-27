<?php

namespace App\Services\Dashboard\Calendar\Sources;

use App\Contracts\Dashboard\CalendarEventSource;
use App\Filament\Admin\Resources\ReservasVeiculos\ReservaVeiculoResource;
use App\Models\Enums\DashboardPrioridade;
use App\Models\ReservaVeiculo;
use App\Support\Dashboard\Calendar\CalendarEventData;
use App\Support\Dashboard\Calendar\CalendarEventDetailData;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ReservaVeiculoCalendarEventSource implements CalendarEventSource
{
    public function key(): string
    {
        return 'reservas_veiculos';
    }

    public function supports(CalendarQueryContext $context): bool
    {
        return (bool) config('dashboard.calendar.sources.reservas_veiculos', true);
    }

    public function events(CalendarQueryContext $context): iterable
    {
        $reservas = $this->visibleQuery($context)
            ->where('data_inicio', '<=', $context->fim)
            ->where('data_fim', '>=', $context->inicio)
            ->with([
                'veiculo:id,placa,identificacao,cor',
                'usuario:id,name',
                'escola:id,nome',
            ])
            ->orderBy('data_inicio')
            ->limit(max(1, (int) config('dashboard.calendar.max_events', 500)) + 1)
            ->get();

        foreach ($reservas as $reserva) {
            yield $this->map($context, $reserva);
        }
    }

    public function detail(
        CalendarQueryContext $context,
        string $reference,
    ): ?CalendarEventDetailData {
        $id = filter_var($reference, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! $id) {
            return null;
        }

        $reserva = $this->visibleQuery($context)
            ->with(['veiculo:id,placa,identificacao,cor', 'usuario:id,name', 'escola:id,nome'])
            ->find($id);

        if (! $reserva) {
            return null;
        }

        return new CalendarEventDetailData(
            event: $this->map($context, $reserva),
            descricao: $reserva->atividade,
            metadata: [
                'Servidor' => $reserva->usuario?->name,
                'Veículo' => $this->nomeVeiculo($reserva),
                'Destino' => $reserva->local_nome,
            ],
        );
    }

    private function visibleQuery(CalendarQueryContext $context): Builder
    {
        $query = ReservaVeiculo::query()->ativas();

        if ($context->somenteReservasVeiculos || $context->redeCompleta) {
            return $query;
        }

        $query->where(function (Builder $visiveis) use ($context): void {
            $visiveis->where('usuario_id', $context->user->id);

            if ($context->userContext->escolaIds !== []) {
                $visiveis->orWhereIn('escola_id', $context->userContext->escolaIds);
            }
        });

        if ($context->escolaId) {
            $query->where('escola_id', $context->escolaId);
        }

        return $query;
    }

    private function map(
        CalendarQueryContext $context,
        ReservaVeiculo $reserva,
    ): CalendarEventData {
        return new CalendarEventData(
            id: $this->key().':'.$reserva->id,
            source: $this->key(),
            reference: (string) $reserva->id,
            titulo: $this->nomeVeiculo($reserva),
            resumo: $reserva->atividade,
            inicio: $reserva->data_inicio->toImmutable(),
            fim: $reserva->data_fim->toImmutable(),
            diaInteiro: false,
            categoria: 'veiculos',
            categoriaLabel: 'Veículo',
            assunto: $reserva->atividade,
            status: $reserva->status->value,
            statusLabel: $reserva->status->label(),
            prioridade: DashboardPrioridade::Normal,
            progresso: null,
            cor: 'azul',
            escolaId: $reserva->escola_id,
            escola: $reserva->escola?->nome,
            setorId: null,
            setor: null,
            origem: 'Reserva de veículo',
            actionUrl: Gate::forUser($context->user)->allows('viewAny', ReservaVeiculo::class)
                ? ReservaVeiculoResource::getUrl()
                : null,
            actionLabel: 'Gerenciar reservas',
            local: $reserva->local_nome,
            corDestaque: $reserva->veiculo?->cor,
        );
    }

    private function nomeVeiculo(ReservaVeiculo $reserva): string
    {
        return $reserva->veiculo?->identificacao
            ?: $reserva->veiculo?->placa
            ?: 'Veículo reservado';
    }
}
