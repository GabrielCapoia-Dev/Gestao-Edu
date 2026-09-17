<?php

namespace App\Services\Dashboard\Calendar\Sources;

use App\Contracts\Dashboard\CalendarEventSource;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\EventoCalendario;
use App\Models\EventoCalendarioEscola;
use App\Services\Dashboard\PublicoAlvoService;
use App\Support\Dashboard\Calendar\CalendarEventData;
use App\Support\Dashboard\Calendar\CalendarEventDetailData;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ManualCalendarEventSource implements CalendarEventSource
{
    public function __construct(private readonly PublicoAlvoService $publicos) {}

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
            if ($evento->enviar_todas_escolas) {
                yield $this->map($evento);

                continue;
            }

            foreach ($evento->escolasAgendadas as $agendamento) {
                yield $this->map($evento, $agendamento);
            }
        }
    }

    public function detail(CalendarQueryContext $context, string $reference): ?CalendarEventDetailData
    {
        [$eventoId, $escolaId] = $this->parseReference($reference);

        if (! $eventoId) {
            return null;
        }

        $evento = $this->visibleQuery($context)
            ->with($this->relations($context))
            ->find($eventoId);

        if (! $evento) {
            return null;
        }

        $agendamento = null;

        if (! $evento->enviar_todas_escolas) {
            $agendamento = $escolaId
                ? $evento->escolasAgendadas->firstWhere('escola_id', $escolaId)
                : $evento->escolasAgendadas->first();

            if (! $agendamento) {
                return null;
            }
        }

        $event = $this->map($evento, $agendamento);
        $metadata = [
            'Origem' => $evento->origem?->value === 'planilha'
                ? 'Importado por planilha'
                : 'Cadastrado manualmente',
        ];

        if ($agendamento?->precisa_transporte) {
            $metadata['Transporte'] = ($agendamento->quantidade_estimada_transporte ?? 0)
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
                $this->publicos->aplicarEscopo(
                    $visiveis,
                    $context->user,
                    'eventos_calendario.publico_alvo_id',
                );

                // "Para mim" também inclui o que o próprio usuário publicou. Isso
                // não transforma usuários globais em destinatários de todas as escolas.
                $visiveis->orWhere(
                    'eventos_calendario.criado_por_id',
                    $context->user->getKey(),
                );
            });
        }

        if (! $context->redeCompleta && ! $context->userContext->escopoGlobal) {
            $query->where(function (Builder $distribuicao) use ($context): void {
                $distribuicao->where('eventos_calendario.enviar_todas_escolas', true);

                if ($context->userContext->escolaIds !== []) {
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
                }
            });

            // Compatibilidade de leitura para eventos antigos segmentados somente por setor.
            $query->where(function (Builder $setores) use ($context): void {
                $setores->whereNull('eventos_calendario.setor_id');

                if ($context->userContext->setorVisivelIds !== []) {
                    $setores->orWhereIn('eventos_calendario.setor_id', $context->userContext->setorVisivelIds);
                }
            });
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
            'escolasAgendadas' => function ($query) use ($context): void {
                if (! $context->redeCompleta && ! $context->userContext->escopoGlobal) {
                    $query->whereIn('escola_id', $context->userContext->escolaIds);
                }

                if ($context->escolaId) {
                    $query->where('escola_id', $context->escolaId);
                }

                $query->orderBy('escola_id');
            },
            'escolasAgendadas.escola:id,nome',
            'alocacoesTransporteAtivas.veiculo:id,placa,identificacao',
            'alocacoesTransporteAtivas.motorista:id,nome',
            'alocacoesTransporteAtivas.turmas:id,nome,id_escola,id_serie',
            'alocacoesTransporteAtivas.turmas.serie:id,nome',
        ];
    }

    private function map(
        EventoCalendario $evento,
        ?EventoCalendarioEscola $agendamento = null,
    ): CalendarEventData {
        $categoria = $evento->categoria;
        $timezone = (string) config('dashboard.calendar.timezone', config('app.timezone'));
        $inicio = $agendamento
            ? CarbonImmutable::parse(
                $evento->data_inicio->toDateString().' '.substr((string) $agendamento->hora_inicio, 0, 5),
                $timezone,
            )
            : $evento->data_inicio->toImmutable();
        $fim = $agendamento
            ? CarbonImmutable::parse(
                $evento->data_inicio->toDateString().' '.substr((string) $agendamento->hora_fim, 0, 5),
                $timezone,
            )
            : $evento->data_fim->toImmutable();
        $suffix = $agendamento ? (string) $agendamento->escola_id : 'all';

        return new CalendarEventData(
            id: $this->key().':'.$evento->getKey().':'.$suffix,
            source: $this->key(),
            reference: $evento->getKey().'@'.$suffix,
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
            escolaId: $agendamento ? (int) $agendamento->escola_id : null,
            escola: $agendamento?->escola?->nome,
            setorId: null,
            setor: null,
            origem: 'Evento cadastrado',
            actionUrl: $evento->linkAcaoSeguro(),
            actionLabel: $evento->texto_botao ?: ($evento->linkAcaoSeguro() ? 'Acessar' : null),
            transporteEstimado: $agendamento?->precisa_transporte
                ? (int) ($agendamento->quantidade_estimada_transporte ?? 0)
                : null,
            local: $evento->local,
            transporteAlocacoes: $agendamento
                ? $evento->alocacoesTransporteAtivas
                    ->map(function ($alocacao) use ($agendamento): ?string {
                        $turmas = $alocacao->turmas
                            ->where('id_escola', $agendamento->escola_id)
                            ->map(fn ($turma): string => trim(($turma->serie?->nome ? $turma->serie->nome.' ' : '').$turma->nome))
                            ->join(', ');

                        if ($turmas === '') {
                            return null;
                        }

                        $veiculo = $alocacao->veiculo?->identificacao ?: $alocacao->veiculo?->placa;

                        return collect([$veiculo, $alocacao->motoristaNomeExibicao(), $turmas])->filter()->implode(' — ');
                    })
                    ->filter()->values()->all()
                : [],
        );
    }

    /** @return array{0: int|null, 1: int|null} */
    private function parseReference(string $reference): array
    {
        [$evento, $escola] = array_pad(explode('@', $reference, 2), 2, null);
        $eventoId = filter_var($evento, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $escolaId = $escola && $escola !== 'all'
            ? filter_var($escola, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : null;

        return [$eventoId ? (int) $eventoId : null, $escolaId ? (int) $escolaId : null];
    }
}
