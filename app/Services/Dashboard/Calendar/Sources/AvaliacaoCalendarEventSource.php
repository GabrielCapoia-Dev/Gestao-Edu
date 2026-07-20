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
                'turmas' => fn ($query) => $this->scopeTurmas($query, $context, $professorTurmaIds)
                    ->select(['turmas.id', 'turmas.id_escola']),
                'turmas.escola:id,nome',
            ])
            ->orderByRaw('COALESCE(data_inicio_preenchimento, data_inicio)')
            ->limit(max(1, (int) config('dashboard.calendar.max_events', 500)) + 1)
            ->get();

        $progressos = $this->progressos($avaliacoes->pluck('id')->all(), $context);

        foreach ($avaliacoes as $avaliacao) {
            yield $this->map($avaliacao, $progressos[(int) $avaliacao->id] ?? null, $context);
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
                'turmas' => fn ($query) => $this->scopeTurmas($query, $context, $professorTurmaIds)
                    ->select(['turmas.id', 'turmas.id_escola']),
                'turmas.escola:id,nome',
            ])
            ->find($id);

        if (! $avaliacao) {
            return null;
        }

        $progresso = $this->progressos([(int) $avaliacao->id], $context)[(int) $avaliacao->id] ?? null;

        return new CalendarEventDetailData(
            event: $this->map($avaliacao, $progresso, $context),
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
    ): Builder
    {
        $query = Avaliacao::query()
            ->where('status', Avaliacao::STATUS_ATIVA)
            ->whereHas('turmas', fn (Builder $turmas): Builder => $this->scopeTurmas(
                $turmas,
                $context,
                $professorTurmaIds,
            ));

        if ($period) {
            $query
                ->whereRaw('COALESCE(data_inicio_preenchimento, data_inicio) <= ?', [$context->fim->toDateString()])
                ->whereRaw('COALESCE(data_fim_preenchimento, data_fim) >= ?', [$context->inicio->toDateString()]);
        }

        return $query;
    }

    /** @param list<int>|null $professorTurmaIds */
    private function scopeTurmas(
        Builder $query,
        CalendarQueryContext $context,
        ?array $professorTurmaIds = null,
    ): Builder
    {
        $schoolIds = $context->escolaId
            ? [$context->escolaId]
            : ($context->userContext->escopoGlobal ? null : $context->userContext->escolaIds);

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

        return $query;
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
            : ($context->userContext->escopoGlobal ? null : $context->userContext->escolaIds);
        $professorIds = Gate::forUser($context->user)->allows('follow', Avaliacao::class)
            ? null
            : $context->user->professores()->where('ativo', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return $this->progressService->batch($avaliacaoIds, $schoolIds, $professorIds);
    }

    private function map(
        Avaliacao $avaliacao,
        ?AvaliacaoDashboardProgressData $progressData,
        CalendarQueryContext $context,
    ): CalendarEventData
    {
        $progresso = $progressData?->percentual;
        $inicio = CarbonImmutable::parse($avaliacao->data_inicio_preenchimento ?? $avaliacao->data_inicio)->startOfDay();
        $fim = CarbonImmutable::parse($avaliacao->data_fim_preenchimento ?? $avaliacao->data_fim)->endOfDay();
        $status = $inicio->isFuture() ? 'agendado' : ($fim->isPast() ? 'concluido' : 'em_andamento');
        $escolas = $avaliacao->turmas->pluck('escola')->filter()->unique('id')->values();
        $escola = $escolas->count() === 1 ? $escolas->first() : null;

        return new CalendarEventData(
            id: $this->key().':'.$avaliacao->getKey(),
            source: $this->key(),
            reference: (string) $avaliacao->getKey(),
            titulo: $avaliacao->nome,
            resumo: $progressData?->emAtualizacao()
                ? 'Preenchimento de avaliação · Progresso em atualização'
                : 'Preenchimento de avaliação'.($progresso !== null ? ' · '.number_format($progresso, 0).'% concluído' : ''),
            inicio: $inicio,
            fim: $fim,
            diaInteiro: true,
            categoria: 'avaliacao',
            categoriaLabel: 'Avaliação',
            assunto: 'Período de preenchimento',
            status: $status,
            statusLabel: match ($status) {
                'agendado' => 'Agendada',
                'concluido' => 'Encerrada',
                default => 'Em andamento',
            },
            prioridade: $fim->lte(now()->addDays(2)->endOfDay()) ? DashboardPrioridade::Alta : DashboardPrioridade::Normal,
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
