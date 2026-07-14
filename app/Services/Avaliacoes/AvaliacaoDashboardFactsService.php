<?php

namespace App\Services\Avaliacoes;

use App\Jobs\RebuildAvaliacaoDashboardFactsJob;
use App\Models\AvaliacaoDashboardFato;
use Illuminate\Support\Facades\DB;

class AvaliacaoDashboardFactsService
{
    public function requestRebuild(int $avaliacaoId): void
    {
        if ($avaliacaoId <= 0) {
            return;
        }

        DB::table('avaliacao_dashboard_consolidacoes')->upsert([
            [
                'avaliacao_id' => $avaliacaoId,
                'status' => 'pendente',
                'solicitada_em' => now(),
                'erro' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        ], ['avaliacao_id'], ['status', 'solicitada_em', 'erro', 'updated_at']);

        RebuildAvaliacaoDashboardFactsJob::dispatch($avaliacaoId)->afterCommit();
    }

    public function status(int $avaliacaoId): ?object
    {
        return DB::table('avaliacao_dashboard_consolidacoes')
            ->where('avaliacao_id', $avaliacaoId)
            ->first();
    }

    public function rebuild(int $avaliacaoId): void
    {
        DB::table('avaliacao_dashboard_consolidacoes')
            ->where('avaliacao_id', $avaliacaoId)
            ->update(['status' => 'processando', 'iniciada_em' => now(), 'erro' => null, 'updated_at' => now()]);

        try {
            DB::transaction(function () use ($avaliacaoId): void {
                DB::table('avaliacao_dashboard_fatos')->where('avaliacao_id', $avaliacaoId)->delete();

                $esperados = DB::table('avaliacao_turma as at')
                    ->join('turmas as t', 't.id', '=', 'at.turma_id')
                    ->join('alunos as aln', 'aln.id_turma', '=', 't.id')
                    ->join('avaliacao_pauta as ap', 'ap.avaliacao_id', '=', 'at.avaliacao_id')
                    ->join('pautas as p', 'p.id', '=', 'ap.pauta_id')
                    ->where('at.avaliacao_id', $avaliacaoId)
                    ->where('aln.status', '!=', 'pendente')
                    ->where('aln.tipo_vinculo', 'principal')
                    ->where('p.status', true)
                    ->where(function ($query): void {
                        $query->whereNull('p.serie_id')->orWhereColumn('p.serie_id', 't.id_serie');
                    })
                    ->selectRaw('at.avaliacao_id, aln.id as aluno_id, t.id as turma_id, t.id_escola as escola_id, t.id_serie as serie_id, p.id as pauta_id, p.componente_curricular_id, NULL as professor_id, NULL as alternativa_id, 0 as respondida, 0 as observacao_pendente, "pendente" as status_resposta, null as respondida_em, 1 as origem_version, NOW() as created_at, NOW() as updated_at');

                DB::table('avaliacao_dashboard_fatos')->insertUsing([
                    'avaliacao_id', 'aluno_id', 'turma_id', 'escola_id', 'serie_id', 'pauta_id',
                    'componente_curricular_id', 'professor_id', 'alternativa_id', 'respondida',
                    'observacao_pendente', 'status_resposta', 'respondida_em', 'origem_version', 'created_at', 'updated_at',
                ], $esperados);

                $this->aplicarRespostas($avaliacaoId);
            });

            DB::table('avaliacao_dashboard_consolidacoes')
                ->where('avaliacao_id', $avaliacaoId)
                ->update(['status' => 'consolidado', 'consolidada_em' => now(), 'updated_at' => now()]);

            app(AvaliacaoDashboardMetricsService::class)->forgetForAvaliacao($avaliacaoId);
        } catch (\Throwable $exception) {
            $this->markFailed($avaliacaoId, $exception->getMessage());
            throw $exception;
        }
    }

    public function markFailed(int $avaliacaoId, ?string $erro): void
    {
        DB::table('avaliacao_dashboard_consolidacoes')
            ->where('avaliacao_id', $avaliacaoId)
            ->update(['status' => 'erro', 'erro' => $erro, 'updated_at' => now()]);
    }

    private function aplicarRespostas(int $avaliacaoId): void
    {
        $sql = <<<'SQL'
UPDATE avaliacao_dashboard_fatos f
JOIN (
    SELECT d.avaliacao_id, d.aluno_id,
           jt.pauta_id, jt.alternativa_id, jt.professor_id,
           jt.componente_curricular_id, jt.observacao, jt.respondido_em,
           d.version
    FROM avaliacao_aluno_documentos d
    CROSS JOIN JSON_TABLE(
        COALESCE(d.payload, JSON_OBJECT()), '$.pautas.*'
        COLUMNS (
            pauta_id INT PATH '$.pauta_id' NULL ON ERROR,
            alternativa_id INT PATH '$.alternativa_id' NULL ON ERROR,
            professor_id INT PATH '$.professor_id' NULL ON ERROR,
            componente_curricular_id INT PATH '$.componente_curricular_id' NULL ON ERROR,
            observacao TEXT PATH '$.observacao' NULL ON ERROR,
            respondido_em VARCHAR(64) PATH '$.respondido_em' NULL ON ERROR
        )
    ) jt
    WHERE d.avaliacao_id = ? AND jt.pauta_id IS NOT NULL AND jt.alternativa_id IS NOT NULL
) r ON r.avaliacao_id = f.avaliacao_id AND r.aluno_id = f.aluno_id AND r.pauta_id = f.pauta_id
LEFT JOIN alternativas alt ON alt.id = r.alternativa_id
SET f.alternativa_id = r.alternativa_id,
    f.professor_id = NULLIF(r.professor_id, 0),
    f.componente_curricular_id = COALESCE(NULLIF(r.componente_curricular_id, 0), f.componente_curricular_id),
    f.respondida = 1,
    f.observacao_pendente = CASE WHEN COALESCE(alt.tem_observacao, 0) = 1 AND NULLIF(TRIM(r.observacao), '') IS NULL THEN 1 ELSE 0 END,
    f.status_resposta = CASE WHEN COALESCE(alt.tem_observacao, 0) = 1 AND NULLIF(TRIM(r.observacao), '') IS NULL THEN 'pendente_observacao' ELSE 'respondida' END,
    f.respondida_em = r.respondido_em,
    f.origem_version = r.version,
    f.updated_at = NOW()
SQL;

        DB::statement($sql, [$avaliacaoId]);
    }
}
