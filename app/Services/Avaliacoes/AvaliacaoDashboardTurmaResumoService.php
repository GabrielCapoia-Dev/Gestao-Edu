<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use App\Models\AvaliacaoTurmaCiclo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mantém uma projeção pequena dos indicadores operacionais por turma.
 *
 * O dashboard consulta esta projeção em vez de recontar a matriz aluno x pauta
 * para cada cartão, gráfico e listagem. O cálculo é sempre restrito à turma
 * afetada e não interpreta o JSON legado.
 */
class AvaliacaoDashboardTurmaResumoService
{
    public const TOTAL_COMPONENT_KEY = 4294967295;

    public function disponivel(): bool
    {
        return Schema::hasTable('avaliacao_dashboard_turma_resumos');
    }

    public function recalcular(int $avaliacaoId, int $turmaId): void
    {
        if (! $this->disponivel() || $avaliacaoId <= 0 || $turmaId <= 0) {
            return;
        }

        $ciclo = AvaliacaoTurmaCiclo::query()
            ->where('avaliacao_id', $avaliacaoId)
            ->where('turma_avaliativa_id', $turmaId)
            ->first();

        if (! $ciclo || $ciclo->operacional_inicializado_em === null || ! in_array($ciclo->status, [
            AvaliacaoTurmaCiclo::STATUS_ABERTA,
            AvaliacaoTurmaCiclo::STATUS_REABERTA,
        ], true)) {
            DB::table('avaliacao_dashboard_turma_resumos')
                ->where('avaliacao_id', $avaliacaoId)
                ->where('turma_id', $turmaId)
                ->delete();

            return;
        }

        $serieId = DB::table('turmas')->where('id', $turmaId)->value('id_serie');
        $esperados = $this->esperados(
            $avaliacaoId,
            (int) $ciclo->turma_origem_id,
            $serieId !== null ? (int) $serieId : null,
        );
        $respondidos = $this->respondidos($ciclo);

        $esperadosPorComponente = $esperados->groupBy('componente_chave');
        $respondidosPorComponente = $respondidos->groupBy('componente_chave');
        $esperadosPorAluno = $esperados->groupBy('aluno_id')->map->count();
        $respondidosPorAluno = $respondidos->groupBy('aluno_id')->map->count();
        $alunosPendentes = $esperadosPorAluno
            ->filter(fn (int $total, int|string $alunoId): bool => (int) ($respondidosPorAluno->get($alunoId, 0)) < $total)
            ->count();
        $alunosTotal = $esperadosPorAluno->count();
        $pautasTotal = $esperados->pluck('pauta_id')->unique()->count();
        $ultimaResposta = $respondidos->pluck('respondido_em')->filter()->max();
        $agora = now();
        $linhas = [];

        $chaves = $esperadosPorComponente->keys()
            ->merge($respondidosPorComponente->keys())
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        foreach ($chaves as $componenteChave) {
            $esperadas = $esperadosPorComponente->get($componenteChave, collect());
            $respondidas = $respondidosPorComponente->get($componenteChave, collect());
            $totalEsperado = $esperadas->count();
            $totalRespondido = min($respondidas->count(), $totalEsperado);

            $linhas[] = [
                'avaliacao_id' => $avaliacaoId,
                'turma_id' => $turmaId,
                'componente_curricular_id' => $componenteChave > 0 ? $componenteChave : null,
                'componente_chave' => $componenteChave,
                'preenchimentos_esperados' => $totalEsperado,
                'preenchimentos_respondidos' => $totalRespondido,
                'alunos_total' => 0,
                'alunos_pendentes' => 0,
                'pautas_total' => 0,
                'ultima_resposta_em' => null,
                'calculado_em' => $agora,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        $linhas[] = [
            'avaliacao_id' => $avaliacaoId,
            'turma_id' => $turmaId,
            'componente_curricular_id' => null,
            'componente_chave' => self::TOTAL_COMPONENT_KEY,
            'preenchimentos_esperados' => $esperados->count(),
            'preenchimentos_respondidos' => min($respondidos->count(), $esperados->count()),
            'alunos_total' => $alunosTotal,
            'alunos_pendentes' => $alunosPendentes,
            'pautas_total' => $pautasTotal,
            'ultima_resposta_em' => $ultimaResposta,
            'calculado_em' => $agora,
            'created_at' => $agora,
            'updated_at' => $agora,
        ];

        DB::transaction(function () use ($avaliacaoId, $turmaId, $linhas, $chaves): void {
            DB::table('avaliacao_dashboard_turma_resumos')
                ->where('avaliacao_id', $avaliacaoId)
                ->where('turma_id', $turmaId)
                ->whereNotIn('componente_chave', $chaves->merge([self::TOTAL_COMPONENT_KEY])->all())
                ->delete();

            DB::table('avaliacao_dashboard_turma_resumos')->upsert(
                $linhas,
                ['avaliacao_id', 'turma_id', 'componente_chave'],
                [
                    'componente_curricular_id',
                    'preenchimentos_esperados',
                    'preenchimentos_respondidos',
                    'alunos_total',
                    'alunos_pendentes',
                    'pautas_total',
                    'ultima_resposta_em',
                    'calculado_em',
                    'updated_at',
                ],
            );
        }, 3);
    }

    public function recalcularAvaliacao(int $avaliacaoId, ?array $turmaIds = null): int
    {
        if (! $this->disponivel() || $avaliacaoId <= 0) {
            return 0;
        }

        $ids = AvaliacaoTurmaCiclo::query()
            ->where('avaliacao_id', $avaliacaoId)
            ->whereIn('status', [AvaliacaoTurmaCiclo::STATUS_ABERTA, AvaliacaoTurmaCiclo::STATUS_REABERTA])
            ->when($turmaIds !== null, fn ($query) => $query->whereIn('turma_avaliativa_id', $turmaIds))
            ->pluck('turma_avaliativa_id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        foreach ($ids as $turmaId) {
            $this->recalcular($avaliacaoId, $turmaId);
        }

        return $ids->count();
    }

    /** @return Collection<int, object> */
    private function esperados(int $avaliacaoId, int $turmaOrigemId, ?int $serieId): Collection
    {
        return DB::table('avaliacao_pauta as ap')
            ->join('pautas as p', 'p.id', '=', 'ap.pauta_id')
            ->join('alunos as aln', 'aln.id_turma', '=', DB::raw((string) $turmaOrigemId))
            ->where('ap.avaliacao_id', $avaliacaoId)
            ->where('p.status', true)
            ->whereIn('aln.status', [Aluno::STATUS_MATRICULADO, Aluno::STATUS_PENDENTE])
            ->where(function (Builder $query): void {
                $query
                    ->where('aln.status', '!=', Aluno::STATUS_PENDENTE)
                    ->orWhereNull('aln.pendencia_origem_aluno_id')
                    ->orWhere('aln.pendencia_origem_aluno_id', '<=', 0);
            })
            ->where(function (Builder $query) use ($serieId): void {
                $query
                    ->whereNull('p.serie_id')
                    ->when($serieId === null, fn (Builder $query) => $query->whereNull('p.serie_id'))
                    ->when($serieId !== null, fn (Builder $query) => $query->orWhere('p.serie_id', $serieId));
            })
            ->select('aln.id as aluno_id', 'p.id as pauta_id')
            ->selectRaw('COALESCE(p.componente_curricular_id, 0) as componente_chave')
            ->distinct()
            ->get();
    }

    /** @return Collection<int, object> */
    private function respondidos(AvaliacaoTurmaCiclo $ciclo): Collection
    {
        return DB::table('avaliacao_respostas_operacionais as ar')
            ->join('pautas as p', 'p.id', '=', 'ar.pauta_id')
            ->join('alternativas as alt', 'alt.id', '=', 'ar.alternativa_id')
            ->join('alunos as aln', 'aln.id', '=', 'ar.aluno_id')
            ->where('ar.ciclo_id', (int) $ciclo->id)
            ->where('p.status', true)
            ->whereIn('aln.status', [Aluno::STATUS_MATRICULADO, Aluno::STATUS_PENDENTE])
            ->where(function (Builder $query): void {
                $query
                    ->where('aln.status', '!=', Aluno::STATUS_PENDENTE)
                    ->orWhereNull('aln.pendencia_origem_aluno_id')
                    ->orWhere('aln.pendencia_origem_aluno_id', '<=', 0);
            })
            ->where(function (Builder $completas): void {
                $completas
                    ->where('alt.tem_observacao', false)
                    ->orWhereRaw("TRIM(COALESCE(ar.observacao, '')) <> ''");
            })
            ->select('ar.aluno_id', 'ar.pauta_id', 'ar.respondido_em')
            ->selectRaw('COALESCE(p.componente_curricular_id, 0) as componente_chave')
            ->distinct()
            ->get();
    }
}
