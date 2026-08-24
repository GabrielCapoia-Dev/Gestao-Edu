<?php

namespace App\Observers;

use App\Models\Aluno;
use App\Services\Avaliacoes\AvaliacaoDashboardFactsService;
use App\Services\Avaliacoes\AvaliacaoDashboardMetricsService;
use App\Services\Avaliacoes\TurmaAvaliacaoAlunoScopeService;
use Illuminate\Support\Facades\DB;

class AvaliacaoDashboardAlunoObserver
{
    private const AFFECTED_EVALUATIONS_RELATION = '__avaliacao_dashboard_avaliacoes_afetadas';

    private const AFFECTED_INTEGRAL_SCOPES_RELATION = '__avaliacao_dashboard_escopos_integrais';

    public function saved(Aluno $aluno): void
    {
        if (! $aluno->wasRecentlyCreated
            && ! $aluno->wasChanged(['id_turma', 'tipo_vinculo', 'status'])
        ) {
            return;
        }

        $this->requestFor($aluno, 'vinculo_aluno_alterado');
    }

    public function deleting(Aluno $aluno): void
    {
        $avaliacaoIds = DB::table('avaliacao_dashboard_fatos')
            ->where('aluno_id', (int) $aluno->id)
            ->pluck('avaliacao_id')
            ->merge(DB::table('avaliacao_aluno_documentos')
                ->where('aluno_id', (int) $aluno->id)
                ->pluck('avaliacao_id'))
            ->map(fn ($avaliacaoId): int => (int) $avaliacaoId)
            ->unique()
            ->values()
            ->all();
        $escopos = app(TurmaAvaliacaoAlunoScopeService::class)
            ->avaliacoesTurmasIntegraisAfetadas(collect([(int) $aluno->id_turma]));

        $aluno->setRelation(self::AFFECTED_EVALUATIONS_RELATION, $avaliacaoIds);
        $aluno->setRelation(self::AFFECTED_INTEGRAL_SCOPES_RELATION, $escopos->all());
    }

    public function deleted(Aluno $aluno): void
    {
        $avaliacaoIds = $aluno->relationLoaded(self::AFFECTED_EVALUATIONS_RELATION)
            ? (array) $aluno->getRelation(self::AFFECTED_EVALUATIONS_RELATION)
            : [];
        $escopos = $aluno->relationLoaded(self::AFFECTED_INTEGRAL_SCOPES_RELATION)
            ? (array) $aluno->getRelation(self::AFFECTED_INTEGRAL_SCOPES_RELATION)
            : [];

        DB::afterCommit(function () use ($avaliacaoIds, $escopos): void {
            foreach ($avaliacaoIds as $avaliacaoId) {
                app(AvaliacaoDashboardMetricsService::class)
                    ->forgetForAvaliacao((int) $avaliacaoId);
            }

            foreach ($escopos as $escopo) {
                app(AvaliacaoDashboardFactsService::class)->requestSyncTurma(
                    (int) $escopo['avaliacao_id'],
                    (int) $escopo['turma_id'],
                    'vinculo_aluno_excluido',
                );
            }
        });
    }

    private function requestFor(Aluno $aluno, string $motivo): void
    {
        $turmaIds = collect([
            $aluno->id_turma,
            $aluno->getOriginal('id_turma'),
        ])->filter()->map(fn ($id): int => (int) $id)->unique()->values();
        $escoposIntegrais = app(TurmaAvaliacaoAlunoScopeService::class)
            ->avaliacoesTurmasIntegraisAfetadas($turmaIds);

        $avaliacaoIds = DB::table('avaliacao_turma')
            ->whereIn('turma_id', $turmaIds->all())
            ->pluck('avaliacao_id')
            ->merge(DB::table('avaliacao_dashboard_fatos')
                ->where('aluno_id', (int) $aluno->id)
                ->pluck('avaliacao_id'))
            ->merge(DB::table('avaliacao_aluno_documentos')
                ->where('aluno_id', (int) $aluno->id)
                ->pluck('avaliacao_id'))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->diff($escoposIntegrais->pluck('avaliacao_id')->map(fn ($id): int => (int) $id))
            ->values();

        foreach ($avaliacaoIds as $avaliacaoId) {
            app(AvaliacaoDashboardFactsService::class)->requestSyncDocumento(
                $avaliacaoId,
                (int) $aluno->id,
                $motivo,
            );
        }

        foreach ($escoposIntegrais as $escopo) {
            app(AvaliacaoDashboardFactsService::class)->requestSyncTurma(
                (int) $escopo['avaliacao_id'],
                (int) $escopo['turma_id'],
                'origem_turma_integral_alterada',
            );
        }
    }
}
