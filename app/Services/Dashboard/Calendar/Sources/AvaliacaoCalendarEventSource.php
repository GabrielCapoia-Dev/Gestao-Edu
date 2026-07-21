<?php

namespace App\Services\Dashboard\Calendar\Sources;

use App\Contracts\Dashboard\CalendarEventSource;
use App\Filament\Admin\Pages\AvaliacoesProfessor;
use App\Filament\Admin\Pages\Relatorios\DashboardAvaliacoes;
use App\Models\Avaliacao;
use App\Models\Enums\DashboardPrioridade;
use App\Models\TurmaComponenteProfessor;
use App\Services\Avaliacoes\AvaliacaoDashboardProgressService;
use App\Support\Avaliacoes\AvaliacaoDashboardProgressData;
use App\Support\Dashboard\Calendar\CalendarEventData;
use App\Support\Dashboard\Calendar\CalendarEventDetailData;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Throwable;

class AvaliacaoCalendarEventSource implements CalendarEventSource
{
    public function __construct(private readonly AvaliacaoDashboardProgressService $progressService) {}

    public function key(): string
    {
        return 'avaliacoes';
    }

    public function supports(CalendarQueryContext $context): bool
    {
        return (bool) config('dashboard.calendar.sources.avaliacoes', true)
            && (Gate::forUser($context->user)->allows('follow', Avaliacao::class)
                || Gate::forUser($context->user)->allows('accessProfessorPage', Avaliacao::class));
    }

    public function events(CalendarQueryContext $context): iterable
    {
        $professorTurmaIds = $this->professorTurmaIds($context);
        $avaliacoes = $this->baseQuery($context, professorTurmaIds: $professorTurmaIds)
            ->with([
                'turmas' => function (BelongsToMany $query) use ($context, $professorTurmaIds): void {
                    $this->scopeTurmas($query, $context, $professorTurmaIds);
                    $query->select(['turmas.id', 'turmas.id_escola']);
                },
                'turmas.escola:id,nome',
            ])
            ->orderByRaw('COALESCE(data_inicio_preenchimento, data_inicio)')
            ->limit(max(1, (int) config('dashboard.calendar.max_events', 500)) + 1)
            ->get();

        $progressos = $this->progressos($avaliacoes->pluck('id')->all(), $context);

        foreach ($avaliacoes as $avaliacao) {
            foreach ($this->marcosNoPeriodo($avaliacao, $context) as $marco => $data) {
                yield $this->map(
                    $avaliacao,
                    $progressos[(int) $avaliacao->id] ?? null,
                    $context,
                    $data,
                    $marco,
                );
            }
        }
    }

    public function detail(CalendarQueryContext $context, string $reference): ?CalendarEventDetailData
    {
        $id = filter_var($reference, FILTER_VALIDATE_INT);

        if (! $id) {
            return null;
        }

        $professorTurmaIds = $this->professorTurmaIds($context);
        $avaliacao = $this->baseQuery($context, period: false, professorTurmaIds: $professorTurmaIds)
            ->with([
                'tipo:id,nome',
                'periodo:id,nome',
                'turmas' => function (BelongsToMany $query) use ($context, $professorTurmaIds): void {
                    $this->scopeTurmas($query, $context, $professorTurmaIds);
                    $query->select(['turmas.id', 'turmas.id_escola']);
                },
                'turmas.escola:id,nome',
            ])
            ->find($id);

        if (! $avaliacao) {
            return null;
        }

        $progresso = $this->progressos([(int) $avaliacao->id], $context)[(int) $avaliacao->id] ?? null;
        [$inicio] = $this->periodoPreenchimento($avaliacao);

        return new CalendarEventDetailData(
            event: $this->map($avaliacao, $progresso, $context, $inicio, 'inicio'),
            descricao: 'Período de preenchimento da avaliação '.$avaliacao->nome.'.',
            metadata: [
                'Tipo' => $avaliacao->tipo?->nome,
                'Período' => $avaliacao->periodo?->nome,
                'Escolas' => $this->schoolLabel($avaliacao->turmas),
                'Progresso' => $progresso?->emAtualizacao() ? 'Progresso em atualização' : null,
            ],
        );
    }

    /** @param list<int>|null $professorTurmaIds */
    private function baseQuery(
        CalendarQueryContext $context,
        bool $period = true,
        ?array $professorTurmaIds = null,
    ): Builder {
        $query = Avaliacao::query()
            ->where('status', Avaliacao::STATUS_ATIVA)
            ->whereHas('turmas', function (Builder $turmas) use ($context, $professorTurmaIds): void {
                $this->scopeTurmas($turmas, $context, $professorTurmaIds);
            });

        if ($period) {
            $inicio = $context->inicio->toDateString();
            $fim = $context->fim->toDateString();

            $query->where(function (Builder $marcos) use ($inicio, $fim): void {
                $marcos
                    ->whereRaw(
                        'COALESCE(data_inicio_preenchimento, data_inicio) between ? and ?',
                        [$inicio, $fim],
                    )
                    ->orWhereRaw(
                        'COALESCE(data_fim_preenchimento, data_fim) between ? and ?',
                        [$inicio, $fim],
                    );
            });
        }

        return $query;
    }

    /** @param list<int>|null $professorTurmaIds */
    private function scopeTurmas(
        Builder|BelongsToMany $query,
        CalendarQueryContext $context,
        ?array $professorTurmaIds = null,
    ): void {
        $schoolIds = $context->escolaId
            ? [$context->escolaId]
            : ($context->redeCompleta || $context->userContext->escopoGlobal
                ? null
                : $context->userContext->escolaIds);

        if (is_array($schoolIds)) {
            $schoolIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('turmas.id_escola', $schoolIds);
        }

        if ($professorTurmaIds !== null) {
            $professorTurmaIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('turmas.id', $professorTurmaIds);
        }
    }

    /** @return list<int>|null */
    private function professorTurmaIds(CalendarQueryContext $context): ?array
    {
        if (Gate::forUser($context->user)->allows('follow', Avaliacao::class)) {
            return null;
        }

        $professorIds = $context->user->professores()
            ->where('ativo', true)
            ->pluck('id');

        if ($professorIds->isEmpty()) {
            return [];
        }

        return TurmaComponenteProfessor::query()
            ->whereIn('professor_id', $professorIds)
            ->where('tem_professor', true)
            ->distinct()
            ->pluck('turma_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @param list<int> $avaliacaoIds @return array<int, AvaliacaoDashboardProgressData> */
    private function progressos(array $avaliacaoIds, CalendarQueryContext $context): array
    {
        if ($avaliacaoIds === []) {
            return [];
        }

        $schoolIds = $context->escolaId
            ? [$context->escolaId]
            : ($context->redeCompleta || $context->userContext->escopoGlobal
                ? null
                : $context->userContext->escolaIds);
        $professorIds = Gate::forUser($context->user)->allows('follow', Avaliacao::class)
            ? null
            : $context->user->professores()->where('ativo', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return $this->progressService->batch($avaliacaoIds, $schoolIds, $professorIds);
    }

    private function map(
        Avaliacao $avaliacao,
        ?AvaliacaoDashboardProgressData $progressData,
        CalendarQueryContext $context,
        CarbonImmutable $data,
        string $marco,
    ): CalendarEventData {
        $progresso = $progressData?->percentual;
        [$inicioPreenchimento, $fimPreenchimento] = $this->periodoPreenchimento($avaliacao);
        $mesmoDia = $inicioPreenchimento->isSameDay($fimPreenchimento);
        $tipoMarco = $mesmoDia ? 'unico' : $marco;
        $status = match ($tipoMarco) {
            'inicio' => 'inicio_preenchimento',
            'fim' => 'fim_preenchimento',
            default => 'periodo_preenchimento',
        };
        $statusLabel = match ($tipoMarco) {
            'inicio' => 'Início do preenchimento',
            'fim' => 'Prazo final para preenchimento',
            default => 'Período de preenchimento',
        };
        $escolas = $avaliacao->turmas->pluck('escola')->filter()->unique('id')->values();
        $escola = $escolas->count() === 1 ? $escolas->first() : null;

        return new CalendarEventData(
            id: $this->key().':'.$avaliacao->getKey().':'.$tipoMarco,
            source: $this->key(),
            reference: (string) $avaliacao->getKey(),
            titulo: $avaliacao->nome,
            resumo: $progressData?->emAtualizacao()
                ? $statusLabel.' · Progresso em atualização'
                : $statusLabel.($progresso !== null ? ' · '.number_format($progresso, 0).'% concluído' : ''),
            inicio: $data->startOfDay(),
            fim: $data->endOfDay(),
            diaInteiro: true,
            categoria: 'avaliacao',
            categoriaLabel: 'Avaliação',
            assunto: $statusLabel,
            status: $status,
            statusLabel: $statusLabel,
            prioridade: $fimPreenchimento->lte(now()->addDays(2)->endOfDay()) ? DashboardPrioridade::Alta : DashboardPrioridade::Normal,
            progresso: $progresso,
            cor: 'verde',
            escolaId: $escola ? (int) $escola->id : null,
            escola: $escola?->nome ?? $this->schoolLabel($avaliacao->turmas),
            setorId: null,
            setor: null,
            origem: 'Módulo de avaliações',
            actionUrl: $actionUrl = $this->actionUrl($avaliacao, $context),
            actionLabel: $actionUrl ? 'Acessar avaliação' : null,
        );
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function periodoPreenchimento(Avaliacao $avaliacao): array
    {
        return [
            CarbonImmutable::parse($avaliacao->data_inicio_preenchimento ?? $avaliacao->data_inicio)->startOfDay(),
            CarbonImmutable::parse($avaliacao->data_fim_preenchimento ?? $avaliacao->data_fim)->endOfDay(),
        ];
    }

    /** @return array<string, CarbonImmutable> */
    private function marcosNoPeriodo(Avaliacao $avaliacao, CalendarQueryContext $context): array
    {
        [$inicio, $fim] = $this->periodoPreenchimento($avaliacao);
        $marcos = [];

        if ($inicio->lte($context->fim) && $inicio->endOfDay()->gte($context->inicio)) {
            $marcos['inicio'] = $inicio;
        }

        if (! $inicio->isSameDay($fim) && $fim->startOfDay()->lte($context->fim) && $fim->gte($context->inicio)) {
            $marcos['fim'] = $fim;
        }

        return $marcos;
    }

    private function schoolLabel(Collection $turmas): ?string
    {
        $nomes = $turmas->pluck('escola.nome')->filter()->unique()->values();

        return match ($nomes->count()) {
            0 => null,
            1 => (string) $nomes->first(),
            default => $nomes->count().' escolas',
        };
    }

    private function actionUrl(Avaliacao $avaliacao, CalendarQueryContext $context): ?string
    {
        try {
            if (Gate::forUser($context->user)->allows('follow', Avaliacao::class)) {
                return DashboardAvaliacoes::getUrl(['avaliacao' => $avaliacao->getKey()]);
            }

            return AvaliacoesProfessor::getUrl(['avaliacao' => $avaliacao->getKey()]);
        } catch (Throwable) {
            return null;
        }
    }
}
