<?php

namespace App\Observers;

use App\Models\Avaliacao;
use App\Models\Pauta;
use App\Models\Turma;
use App\Services\Avaliacoes\AvaliacaoDashboardFactsService;
use App\Services\Avaliacoes\AvaliacaoDashboardMetricsService;
use App\Services\Avaliacoes\TurmaAvaliacaoAlunoScopeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AvaliacaoDashboardSourceObserver
{
    private const AFFECTED_EVALUATIONS_RELATION = '__avaliacao_dashboard_avaliacoes_afetadas';

    private const AFFECTED_INTEGRAL_SCOPES_RELATION = '__avaliacao_dashboard_escopos_integrais';

    public function saved(Model $model): void
    {
        $this->requestFor($model, 'salvo');
    }

    public function updating(Model $model): void
    {
        if ($model instanceof Turma) {
            $model->setRelation(
                self::AFFECTED_INTEGRAL_SCOPES_RELATION,
                app(TurmaAvaliacaoAlunoScopeService::class)
                    ->avaliacoesTurmasIntegraisAfetadas(collect([(int) $model->getKey()]))
                    ->all(),
            );
        }
    }

    public function deleting(Model $model): void
    {
        if ($model instanceof Avaliacao) {
            return;
        }

        // Guarda os vínculos antes das cascatas. Pauta, turma, alunos,
        // documentos e fatos são removidos por FK; resta invalidar o cache
        // somente depois de a exclusão efetivamente ocorrer.
        $model->setRelation(
            self::AFFECTED_EVALUATIONS_RELATION,
            $this->avaliacaoIdsFor($model),
        );

        if ($model instanceof Turma) {
            $model->setRelation(
                self::AFFECTED_INTEGRAL_SCOPES_RELATION,
                app(TurmaAvaliacaoAlunoScopeService::class)
                    ->avaliacoesTurmasIntegraisAfetadas(collect([(int) $model->getKey()]))
                    ->reject(fn (array $scope): bool => (int) $scope['turma_id'] === (int) $model->getKey())
                    ->all(),
            );
        }
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof Avaliacao
            || ! $model->relationLoaded(self::AFFECTED_EVALUATIONS_RELATION)
        ) {
            return;
        }

        $avaliacaoIds = (array) $model->getRelation(self::AFFECTED_EVALUATIONS_RELATION);
        $integralScopes = $model->relationLoaded(self::AFFECTED_INTEGRAL_SCOPES_RELATION)
            ? (array) $model->getRelation(self::AFFECTED_INTEGRAL_SCOPES_RELATION)
            : [];

        DB::afterCommit(function () use ($avaliacaoIds, $integralScopes): void {
            foreach ($avaliacaoIds as $avaliacaoId) {
                app(AvaliacaoDashboardMetricsService::class)
                    ->forgetForAvaliacao((int) $avaliacaoId);
            }

            foreach ($integralScopes as $scope) {
                app(AvaliacaoDashboardFactsService::class)->requestSyncTurma(
                    (int) $scope['avaliacao_id'],
                    (int) $scope['turma_id'],
                    'turma_origem_excluida',
                );
            }
        });
    }

    private function requestFor(Model $model, string $evento): void
    {
        $service = app(AvaliacaoDashboardFactsService::class);

        if ($model instanceof Avaliacao) {
            app(AvaliacaoDashboardMetricsService::class)
                ->forgetForAvaliacao((int) $model->getKey());

            return;
        }

        $ids = $this->avaliacaoIdsFor($model);

        foreach ($ids as $id) {
            if ($model instanceof Pauta) {
                $service->requestSyncPauta(
                    (int) $id,
                    (int) $model->getKey(),
                    'pauta_'.$evento,
                );

                continue;
            }

            if ($model instanceof Turma) {
                $service->requestSyncTurma(
                    (int) $id,
                    (int) $model->getKey(),
                    'turma_'.$evento,
                );
            }
        }

        if ($model instanceof Turma) {
            $integralScopes = collect(
                $model->relationLoaded(self::AFFECTED_INTEGRAL_SCOPES_RELATION)
                    ? (array) $model->getRelation(self::AFFECTED_INTEGRAL_SCOPES_RELATION)
                    : [],
            )->merge(
                app(TurmaAvaliacaoAlunoScopeService::class)
                    ->avaliacoesTurmasIntegraisAfetadas(collect([(int) $model->getKey()])),
            )->unique(fn (array $scope): string => $scope['avaliacao_id'].'|'.$scope['turma_id']);

            foreach ($integralScopes as $scope) {
                $service->requestSyncTurma(
                    (int) $scope['avaliacao_id'],
                    (int) $scope['turma_id'],
                    'turma_origem_'.$evento,
                );
            }
        }
    }

    /** @return list<int> */
    private function avaliacaoIdsFor(Model $model): array
    {
        $ids = match (true) {
            $model instanceof Pauta => DB::table('avaliacao_pauta')
                ->where('pauta_id', (int) $model->getKey())
                ->pluck('avaliacao_id')
                ->merge(DB::table('avaliacao_dashboard_fatos')
                    ->where('pauta_id', (int) $model->getKey())
                    ->pluck('avaliacao_id')),
            $model instanceof Turma => DB::table('avaliacao_turma')
                ->where('turma_id', (int) $model->getKey())
                ->pluck('avaliacao_id')
                ->merge(DB::table('avaliacao_dashboard_fatos')
                    ->where('turma_id', (int) $model->getKey())
                    ->pluck('avaliacao_id')),
            default => collect(),
        };

        return $ids
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
