<?php

namespace App\Services\Dashboard\Calendar\Sources;

use App\Contracts\Dashboard\CalendarEventSource;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\EventoCalendario;
use App\Services\Dashboard\PublicoAlvoService;
use App\Services\Dashboard\EventoCalendarioPublicoService;
use App\Support\Dashboard\Calendar\CalendarEventData;
use App\Support\Dashboard\Calendar\CalendarEventDetailData;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ManualCalendarEventSource implements CalendarEventSource
{
    public function __construct(
        private readonly PublicoAlvoService $publicos,
        private readonly EventoCalendarioPublicoService $publicoEventos,
    ) {}

    public function key(): string
    {
        return 'manual';
    }

    public function supports(CalendarQueryContext $context): bool
    {
        return (bool) config('dashboard.calendar.sources.manual', true);
    }

    public function events(CalendarQueryContext $context): iterable
    {
        $eventos = $this->visibleQuery($context)
            ->noPeriodo($context->inicio, $context->fim)
            ->with($this->relations($context))
            ->orderBy('data_inicio')
            ->limit(max(1, (int) config('dashboard.calendar.max_events', 500)) + 1)
            ->get();

        foreach ($eventos as $evento) {
            yield $this->map($evento, $context);
        }
    }

    public function detail(CalendarQueryContext $context, string $reference): ?CalendarEventDetailData
    {
        $eventoId = $this->parseReference($reference);

        if (! $eventoId) {
            return null;
        }

        $evento = $this->visibleQuery($context)
            ->with($this->relations($context))
            ->find($eventoId);

        if (! $evento) {
            return null;
        }

        $event = $this->map($evento, $context);
        $metadata = [
            'Origem' => $evento->origem?->value === 'planilha'
                ? 'Importado por planilha'
                : 'Cadastrado manualmente',
        ];

        $estimativa = $evento->escolasAgendadas->where('precisa_transporte', true)
            ->sum('quantidade_estimada_transporte');
        if ($estimativa > 0) {
            $metadata['Transporte'] = $estimativa
                .' estudante(s) estimado(s)';
        }

        return new CalendarEventDetailData(
            event: $event,
            descricao: $evento->descricao,
            metadata: $metadata,
        );
    }

    private function visibleQuery(CalendarQueryContext $context): Builder
    {
        $query = EventoCalendario::query()->publicados();

        if (! $context->redeCompleta) {
            $query->where(function (Builder $visiveis) use ($context): void {
                $visiveis->where(function (Builder $publicos) use ($context): void {
                    $publicos->where('eventos_calendario.publico_tipo', 'segmentado')
                        ->whereHas('publicoRegras');
                    $this->publicoEventos->aplicarEscopo($publicos, $context->user);
                });

                $visiveis->orWhere(function (Builder $legado) use ($context): void {
                    $legado->where('eventos_calendario.publico_tipo', '!=', 'segmentado');
                    $this->publicos->aplicarEscopo($legado, $context->user, 'eventos_calendario.publico_alvo_id');
                });

            });
        }

        if (! $context->redeCompleta) {
            if ($context->userContext->escolaIds === []) {
                return $query->whereRaw('1 = 0');
            }

            $query->where(function (Builder $distribuicao) use ($context): void {
                $distribuicao->where('eventos_calendario.enviar_todas_escolas', true);

                $distribuicao->orWhere(function (Builder $especificas) use ($context): void {
                    $especificas
                        ->where('eventos_calendario.enviar_todas_escolas', false)
                        ->whereHas(
                            'escolasAgendadas',
                            fn (Builder $escolas): Builder => $escolas->whereIn(
                                'evento_calendario_escolas.escola_id',
                                $context->userContext->escolaIds,
                            ),
                        );
                });
            });

            // Compatibilidade de leitura para eventos antigos segmentados somente por setor.
            if (! $context->userContext->escopoGlobal) {
                $query->where(function (Builder $setores) use ($context): void {
                    $setores->whereNull('eventos_calendario.setor_id');

                    if ($context->userContext->setorVisivelIds !== []) {
                        $setores->orWhereIn('eventos_calendario.setor_id', $context->userContext->setorVisivelIds);
                    }
                });
            }
        }

        if ($context->escolaId) {
            $query->where(function (Builder $escola) use ($context): void {
                $escola
                    ->where('eventos_calendario.enviar_todas_escolas', true)
                    ->orWhereHas(
                        'escolasAgendadas',
                        fn (Builder $relacao): Builder => $relacao->where(
                            'evento_calendario_escolas.escola_id',
                            $context->escolaId,
                        ),
                    );
            });
        }

        return $query;
    }

    /** @return array<string, callable|string> */
    private function relations(CalendarQueryContext $context): array
    {
        return [
            'publicoAlvo.escolas' => function ($query) use ($context): void {
                if (! $context->redeCompleta) {
                    $context->userContext->escolaIds !== []
                        ? $query->whereIn('escolas.id', $context->userContext->escolaIds)
                        : $query->whereRaw('1 = 0');
                }
            },
            'escolasAgendadas' => function ($query) use ($context): void {
                if (! $context->redeCompleta) {
                    $context->userContext->escolaIds !== []
                        ? $query->whereIn('escola_id', $context->userContext->escolaIds)
                        : $query->whereRaw('1 = 0');
                }

                if ($context->escolaId) {
                    $query->where('escola_id', $context->escolaId);
                }

                $query->orderBy('escola_id');
            },
            'escolasAgendadas.escola:id,nome',
            'escolasAgendadas.series:id,nome',
            'escolasAgendadas.turmas:id,nome,id_escola,id_serie,turno',
            'alocacoesTransporteAtivas.veiculo:id,placa,identificacao',
            'alocacoesTransporteAtivas.motorista:id,nome',
            'alocacoesTransporteAtivas.turmas:id,nome,id_escola,id_serie',
            'alocacoesTransporteAtivas.turmas.serie:id,nome',
        ];
    }

    private function map(
        EventoCalendario $evento,
        CalendarQueryContext $context,
    ): CalendarEventData {
        $categoria = $evento->categoria;
        $timezone = (string) config('dashboard.calendar.timezone', config('app.timezone'));
        $agendamentos = $evento->escolasAgendadas;
        $primeiro = $agendamentos->sortBy('hora_inicio')->first();
        $ultimo = $agendamentos->sortByDesc('hora_fim')->first();
        $inicio = $primeiro
            ? CarbonImmutable::parse(
                $evento->data_inicio->toDateString().' '.substr((string) $primeiro->hora_inicio, 0, 5),
                $timezone,
            )
            : $evento->data_inicio->toImmutable();
        $fim = $ultimo
            ? CarbonImmutable::parse(
                $evento->data_inicio->toDateString().' '.substr((string) $ultimo->hora_fim, 0, 5),
                $timezone,
            )
            : $evento->data_fim->toImmutable();
        $escolas = $agendamentos->pluck('escola')->filter();
        if ($escolas->isEmpty()) {
            $escolas = $evento->publicoAlvo?->escolas ?? collect();
        }
        $escolasCount = $escolas->count();
        $escolaLabel = $escolasCount === 1
            ? $escolas->first()?->nome
            : ($escolasCount > 1
                ? $escolasCount.($context->redeCompleta ? ' escolas' : ' escolas vinculadas')
                : null);
        $transportes = $agendamentos->where('precisa_transporte', true);
        $turmasCount = $agendamentos->flatMap->turmas->pluck('id')->unique()->count();

        return new CalendarEventData(
            id: $this->key().':'.$evento->getKey(),
            source: $this->key(),
            reference: (string) $evento->getKey(),
            titulo: $evento->titulo,
            resumo: filled($evento->descricao) ? Str::limit(strip_tags((string) $evento->descricao), 180) : null,
            inicio: $inicio,
            fim: $fim,
            diaInteiro: false,
            categoria: $categoria->value,
            categoriaLabel: $categoria === EventoCalendarioCategoria::OUTRO && filled($evento->categoria_detalhe)
                ? (string) $evento->categoria_detalhe
                : $categoria->label(),
            assunto: null,
            status: null,
            statusLabel: null,
            prioridade: $evento->prioridade,
            progresso: null,
            cor: $evento->cor->value,
            escolaId: $escolasCount === 1 ? (int) $escolas->first()->getKey() : null,
            escola: $escolaLabel,
            setorId: null,
            setor: null,
            origem: 'Evento cadastrado',
            actionUrl: $evento->linkAcaoSeguro(),
            actionLabel: $evento->texto_botao ?: ($evento->linkAcaoSeguro() ? 'Acessar' : null),
            transporteEstimado: $transportes->isNotEmpty()
                ? (int) $transportes->sum('quantidade_estimada_transporte')
                : null,
            local: $evento->local,
            transporteAlocacoes: $transportes->isNotEmpty()
                ? $evento->alocacoesTransporteAtivas
                    ->map(function ($alocacao) use ($agendamentos): ?string {
                        $turmas = $alocacao->turmas
                            ->whereIn('id_escola', $agendamentos->pluck('escola_id'))
                            ->map(fn ($turma): string => trim(($turma->serie?->nome ? $turma->serie->nome.' ' : '').$turma->nome))
                            ->join(', ');

                        if ($turmas === '') {
                            return null;
                        }

                        $veiculo = $alocacao->veiculo?->identificacao ?: $alocacao->veiculo?->placa;

                        return collect([$veiculo, $alocacao->motoristaNomeExibicao(), $turmas])->filter()->implode(' — ');
                    })
                    ->filter()->unique()->values()->all()
                : [],
            escolasCount: $escolasCount,
            turmasCount: $turmasCount,
            alunosCount: $transportes->isNotEmpty() ? (int) $transportes->sum('quantidade_estimada_transporte') : null,
        );
    }

    private function parseReference(string $reference): ?int
    {
        [$evento] = explode('@', $reference, 2);
        $eventoId = filter_var($evento, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $eventoId ? (int) $eventoId : null;
    }
}
