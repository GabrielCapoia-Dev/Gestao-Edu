<?php

namespace App\Services\Dashboard\Calendar\Sources;

use App\Contracts\Dashboard\CalendarEventSource;
use App\Models\EventoCalendario;
use App\Services\Dashboard\PublicoAlvoService;
use App\Services\Dashboard\SetorPathLabelService;
use App\Support\Dashboard\Calendar\CalendarEventData;
use App\Support\Dashboard\Calendar\CalendarEventDetailData;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ManualCalendarEventSource implements CalendarEventSource
{
    public function __construct(
        private readonly PublicoAlvoService $publicos,
        private readonly SetorPathLabelService $setorLabels,
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
        $query = $this->visibleQuery($context)
            ->noPeriodo($context->inicio, $context->fim)
            ->with(['escola:id,nome', 'setor:id,nome,path'])
            ->orderBy('data_inicio')
            ->limit(max(1, (int) config('dashboard.calendar.max_events', 500)) + 1);

        $eventos = $query->get();
        $setorLabels = $this->setorLabels->labels($eventos->pluck('setor'));

        foreach ($eventos as $evento) {
            yield $this->map($evento, $setorLabels[(int) $evento->setor_id] ?? null);
        }
    }

    public function detail(CalendarQueryContext $context, string $reference): ?CalendarEventDetailData
    {
        $id = filter_var($reference, FILTER_VALIDATE_INT);

        if (! $id) {
            return null;
        }

        $evento = $this->visibleQuery($context)
            ->with(['escola:id,nome', 'setor:id,nome,path'])
            ->find($id);

        if (! $evento) {
            return null;
        }

        return new CalendarEventDetailData(
            event: $this->map(
                $evento,
                $this->setorLabels->labels([$evento->setor])[(int) $evento->setor_id] ?? null,
            ),
            descricao: $evento->descricao,
            metadata: [
                'Origem' => $evento->origem?->value === 'planilha' ? 'Importado por planilha' : 'Cadastrado manualmente',
                'Assunto' => $evento->assunto,
            ],
        );
    }

    private function visibleQuery(CalendarQueryContext $context): Builder
    {
        $query = EventoCalendario::query()->publicados();
        $this->publicos->aplicarEscopo($query, $context->user, 'eventos_calendario.publico_alvo_id');

        if (! $context->userContext->escopoGlobal) {
            $query
                ->where(function (Builder $schools) use ($context): void {
                    $schools->whereNull('eventos_calendario.escola_id');

                    if ($context->userContext->escolaIds !== []) {
                        $schools->orWhereIn(
                            'eventos_calendario.escola_id',
                            $context->userContext->escolaIds,
                        );
                    }
                })
                ->where(function (Builder $sectors) use ($context): void {
                    $sectors->whereNull('eventos_calendario.setor_id');

                    if ($context->userContext->setorVisivelIds !== []) {
                        $sectors->orWhereIn(
                            'eventos_calendario.setor_id',
                            $context->userContext->setorVisivelIds,
                        );
                    }
                });
        }

        if ($context->escolaId) {
            $query->where('eventos_calendario.escola_id', $context->escolaId);
        }

        if ($context->setorId) {
            $query->where('eventos_calendario.setor_id', $context->setorId);
        }

        return $query;
    }

    private function map(EventoCalendario $evento, ?string $setorLabel = null): CalendarEventData
    {
        $categoria = $evento->categoria;
        $status = $evento->statusEfetivo();

        return new CalendarEventData(
            id: $this->key().':'.$evento->getKey(),
            source: $this->key(),
            reference: (string) $evento->getKey(),
            titulo: $evento->titulo,
            resumo: filled($evento->descricao) ? Str::limit(strip_tags((string) $evento->descricao), 180) : null,
            inicio: $evento->data_inicio->toImmutable(),
            fim: $evento->data_fim->toImmutable(),
            diaInteiro: $evento->data_inicio->format('H:i:s') === '00:00:00'
                && $evento->data_fim->format('H:i:s') === '23:59:59',
            categoria: $categoria->value,
            categoriaLabel: $categoria->label(),
            assunto: $evento->assunto,
            status: $status->value,
            statusLabel: $status->label(),
            prioridade: $evento->prioridade,
            progresso: $evento->progresso,
            cor: $evento->cor->value,
            escolaId: $evento->escola_id ? (int) $evento->escola_id : null,
            escola: $evento->escola?->nome,
            setorId: $evento->setor_id ? (int) $evento->setor_id : null,
            setor: $setorLabel ?? $evento->setor?->nome,
            origem: 'Evento cadastrado',
            actionUrl: $evento->linkAcaoSeguro(),
            actionLabel: $evento->texto_botao ?: ($evento->linkAcaoSeguro() ? 'Acessar' : null),
        );
    }
}
