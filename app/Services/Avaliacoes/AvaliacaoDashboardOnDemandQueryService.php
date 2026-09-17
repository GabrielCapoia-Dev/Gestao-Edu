<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use App\Models\Turma;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AvaliacaoDashboardOnDemandQueryService
{
    /** @var array<string, array<int, int>> turma avaliativa => turma de origem */
    private array $origensPorEscopo = [];

    public function __construct(
        private readonly TurmaAvaliacaoAlunoScopeService $turmaAlunoScopeService,
    ) {}

    /**
     * Matriz canônica de preenchimentos esperados.
     *
     * Aliases disponíveis para os consumidores:
     * - at: avaliacao_id, turma_id (alvo) e turma_origem_id;
     * - t: turma avaliativa;
     * - aln: aluno elegível da turma de origem;
     * - p: pauta ativa aplicável à série da turma avaliativa.
     *
     * @param  list<int>  $avaliacaoIds
     */
    public function esperados(array $avaliacaoIds): QueryBuilder
    {
        $avaliacaoIds = $this->ids($avaliacaoIds);

        $query = DB::query()
            ->fromSub($this->escoposAvaliativosQuery($avaliacaoIds), 'at')
            ->join('turmas as t', 't.id', '=', 'at.turma_id')
            ->join('alunos as aln', 'aln.id_turma', '=', 'at.turma_origem_id')
            ->join('avaliacao_pauta as ap', 'ap.avaliacao_id', '=', 'at.avaliacao_id')
            ->join('pautas as p', 'p.id', '=', 'ap.pauta_id')
            ->join('avaliacoes as av', 'av.id', '=', 'at.avaliacao_id')
            ->where('p.status', true)
            ->where(function (QueryBuilder $pautas): void {
                $pautas
                    ->whereNull('p.serie_id')
                    ->orWhereColumn('p.serie_id', 't.id_serie');
            });

        $this->aplicarElegibilidadeAluno($query);

        return $query;
    }

    /**
     * Respostas canônicas extraídas do payload dos documentos.
     *
     * O documento é filtrado por avaliação antes da expansão do JSON. A turma
     * retornada em ar.turma_id é sempre a turma avaliativa, inclusive quando o
     * aluno pertence à turma regular que serve de origem para uma turma integral.
     *
     * Aliases disponíveis para os consumidores:
     * - ar: resposta normalizada;
     * - t, aln, p e alt: entidades canônicas relacionadas.
     *
     * @param  list<int>  $avaliacaoIds
     */
    public function respostas(array $avaliacaoIds, bool $somenteCompletas = false): QueryBuilder
    {
        $avaliacaoIds = $this->ids($avaliacaoIds);
        if (app(AvaliacaoPersistencia::class)->leRelacional()) {
            $respostas = DB::table('avaliacao_respostas_operacionais')
                ->whereIn('avaliacao_id', $avaliacaoIds)
                ->select([
                    'avaliacao_id',
                    'aluno_id',
                    'turma_avaliativa_id as turma_id',
                    'pauta_id',
                    'alternativa_id',
                    'professor_id',
                    'observacao',
                    'respondido_em',
                    'componente_curricular_id',
                ]);
        } else {
            $respostasExpandidas = match (DB::connection()->getDriverName()) {
                'mysql' => $this->respostasExpandidasMysqlQuery($avaliacaoIds),
                'sqlite' => $this->respostasExpandidasSqliteQuery($avaliacaoIds),
                default => throw new RuntimeException(
                    'Driver de banco não suportado para leitura sob demanda das avaliações.'
                ),
            };
            $respostas = $this->normalizarRespostasQuery($avaliacaoIds, $respostasExpandidas);
        }

        $query = DB::query()
            ->fromSub($respostas, 'ar')
            ->join('turmas as t', 't.id', '=', 'ar.turma_id')
            ->join('alunos as aln', 'aln.id', '=', 'ar.aluno_id')
            ->join('avaliacoes as av', 'av.id', '=', 'ar.avaliacao_id')
            ->join('pautas as p', 'p.id', '=', 'ar.pauta_id')
            ->join('alternativas as alt', 'alt.id', '=', 'ar.alternativa_id');

        if ($somenteCompletas) {
            $query->where(function (QueryBuilder $completas): void {
                $completas
                    ->where('alt.tem_observacao', false)
                    ->orWhereRaw("TRIM(COALESCE(ar.observacao, '')) <> ''");
            });
        }

        $query->where(function (QueryBuilder $alunos): void {
            $alunos->whereNull('aln.data_matricula')->orWhereColumn('aln.data_matricula', '<=', 'av.data_fim');
        });

        return $query;
    }

    /** @param list<int> $avaliacaoIds */
    private function respostasExpandidasMysqlQuery(array $avaliacaoIds): QueryBuilder
    {
        return DB::query()
            ->fromSub($this->documentosQuery($avaliacaoIds), 'd')
            ->crossJoin(DB::raw(<<<'SQL'
JSON_TABLE(
    COALESCE(d.payload, JSON_OBJECT()),
    '$.pautas.*' COLUMNS (
        pauta_id BIGINT PATH '$.pauta_id' NULL ON EMPTY NULL ON ERROR,
        alternativa_id BIGINT PATH '$.alternativa_id' NULL ON EMPTY NULL ON ERROR,
        professor_id BIGINT PATH '$.professor_id' NULL ON EMPTY NULL ON ERROR,
        observacao TEXT PATH '$.observacao' NULL ON EMPTY NULL ON ERROR,
        respondido_em VARCHAR(64) PATH '$.respondido_em' NULL ON EMPTY NULL ON ERROR
    )
) AS item_payload
SQL))
            ->select([
                'd.avaliacao_id',
                'd.aluno_id',
                'item_payload.pauta_id',
                'item_payload.alternativa_id',
                'item_payload.professor_id',
                'item_payload.observacao',
                'item_payload.respondido_em',
            ]);
    }

    /** @param list<int> $avaliacaoIds */
    private function respostasExpandidasSqliteQuery(array $avaliacaoIds): QueryBuilder
    {
        return DB::query()
            ->fromSub($this->documentosQuery($avaliacaoIds), 'd')
            ->crossJoin(DB::raw(<<<'SQL'
json_each(
    COALESCE(d.payload, '{"pautas":{}}'),
    '$.pautas'
) AS item_payload
SQL))
            ->select([
                'd.avaliacao_id',
                'd.aluno_id',
            ])
            ->selectRaw(
                "COALESCE(CAST(json_extract(item_payload.value, '$.pauta_id') AS INTEGER), CAST(item_payload.key AS INTEGER)) AS pauta_id"
            )
            ->selectRaw("CAST(json_extract(item_payload.value, '$.alternativa_id') AS INTEGER) AS alternativa_id")
            ->selectRaw("CAST(json_extract(item_payload.value, '$.professor_id') AS INTEGER) AS professor_id")
            ->selectRaw("json_extract(item_payload.value, '$.observacao') AS observacao")
            ->selectRaw("json_extract(item_payload.value, '$.respondido_em') AS respondido_em");
    }

    /** @param list<int> $avaliacaoIds */
    private function documentosQuery(array $avaliacaoIds): QueryBuilder
    {
        return DB::table('avaliacao_aluno_documentos')
            ->whereIn('avaliacao_id', $avaliacaoIds)
            ->select(['avaliacao_id', 'aluno_id', 'payload']);
    }

    /** @param list<int> $avaliacaoIds */
    private function normalizarRespostasQuery(
        array $avaliacaoIds,
        QueryBuilder $respostasExpandidas,
    ): QueryBuilder {
        $query = DB::query()
            ->fromSub($respostasExpandidas, 'payload_resposta')
            ->joinSub($this->escoposAvaliativosQuery($avaliacaoIds), 'at', function ($join): void {
                $join
                    ->on('at.avaliacao_id', '=', 'payload_resposta.avaliacao_id');
            })
            ->join('alunos as aln_matriz', function ($join): void {
                $join
                    ->on('aln_matriz.id', '=', 'payload_resposta.aluno_id')
                    ->on('aln_matriz.id_turma', '=', 'at.turma_origem_id');
            })
            ->join('turmas as t_matriz', 't_matriz.id', '=', 'at.turma_id')
            ->join('avaliacao_pauta as ap_matriz', function ($join): void {
                $join
                    ->on('ap_matriz.avaliacao_id', '=', 'at.avaliacao_id')
                    ->on('ap_matriz.pauta_id', '=', 'payload_resposta.pauta_id');
            })
            ->join('pautas as p_matriz', 'p_matriz.id', '=', 'ap_matriz.pauta_id')
            ->where('p_matriz.status', true)
            ->where(function (QueryBuilder $pautas): void {
                $pautas
                    ->whereNull('p_matriz.serie_id')
                    ->orWhereColumn('p_matriz.serie_id', 't_matriz.id_serie');
            })
            ->select([
                'at.avaliacao_id',
                'aln_matriz.id as aluno_id',
                'at.turma_id',
                'p_matriz.id as pauta_id',
                'payload_resposta.alternativa_id',
                'payload_resposta.professor_id',
                'payload_resposta.observacao',
                'payload_resposta.respondido_em',
                'p_matriz.componente_curricular_id',
            ]);

        $this->aplicarElegibilidadeAluno($query, 'aln_matriz');

        return $query;
    }

    /**
     * @param  list<int>  $avaliacaoIds
     */
    private function escoposAvaliativosQuery(array $avaliacaoIds): QueryBuilder
    {
        if ($avaliacaoIds === []) {
            return $this->escoposVaziosQuery();
        }

        if (app(AvaliacaoPersistencia::class)->leRelacional()) {
            return DB::table('avaliacao_turma_ciclos as ciclo_scope')
                ->join('avaliacoes as avaliacao_scope', 'avaliacao_scope.id', '=', 'ciclo_scope.avaliacao_id')
                ->whereIn('ciclo_scope.avaliacao_id', $avaliacaoIds)
                ->whereIn('ciclo_scope.status', ['aberta', 'reaberta'])
                ->select([
                    'ciclo_scope.avaliacao_id',
                    'ciclo_scope.turma_avaliativa_id as turma_id',
                    'ciclo_scope.turma_origem_id',
                    'avaliacao_scope.data_fim',
                ]);
        }

        $cacheKey = implode(',', $avaliacaoIds);
        $origensPorTurma = $this->origensPorEscopo[$cacheKey]
            ??= $this->resolverOrigensPorTurma($avaliacaoIds);

        if ($origensPorTurma === []) {
            return $this->escoposVaziosQuery();
        }

        $case = 'CASE at_scope.turma_id';
        $bindings = [];

        foreach ($origensPorTurma as $turmaId => $turmaOrigemId) {
            $case .= ' WHEN ? THEN ?';
            $bindings[] = (int) $turmaId;
            $bindings[] = (int) $turmaOrigemId;
        }

        $case .= ' ELSE at_scope.turma_id END';

        return DB::table('avaliacao_turma as at_scope')
            ->join('avaliacoes as avaliacao_scope', 'avaliacao_scope.id', '=', 'at_scope.avaliacao_id')
            ->select(['at_scope.avaliacao_id', 'at_scope.turma_id'])
            ->select('avaliacao_scope.data_fim')
            ->selectRaw("{$case} AS turma_origem_id", $bindings)
            ->whereIn('at_scope.avaliacao_id', $avaliacaoIds)
            ->whereIn('at_scope.turma_id', array_map('intval', array_keys($origensPorTurma)));
    }

    /** @param list<int> $avaliacaoIds @return array<int, int> */
    private function resolverOrigensPorTurma(array $avaliacaoIds): array
    {
        $turmaIds = DB::table('avaliacao_turma')
            ->whereIn('avaliacao_id', $avaliacaoIds)
            ->distinct()
            ->pluck('turma_id')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values();

        if ($turmaIds->isEmpty()) {
            return [];
        }

        $turmas = Turma::query()
            ->whereIn('id', $turmaIds->all())
            ->get(['id']);

        return $this->turmaAlunoScopeService->origensPorTurma($turmas);
    }

    private function escoposVaziosQuery(): QueryBuilder
    {
        return DB::query()
            ->selectRaw('NULL AS avaliacao_id, NULL AS turma_id, NULL AS turma_origem_id')
            ->whereRaw('1 = 0');
    }

    private function aplicarElegibilidadeAluno(QueryBuilder $query, string $alias = 'aln'): void
    {
        $query
            ->whereIn($alias.'.status', [
                Aluno::STATUS_MATRICULADO,
                Aluno::STATUS_PENDENTE,
            ])
            ->where(function (QueryBuilder $alunos) use ($alias): void {
                $alunos
                    ->where($alias.'.status', '!=', Aluno::STATUS_PENDENTE)
                    ->orWhereNull($alias.'.pendencia_origem_aluno_id')
                    ->orWhere($alias.'.pendencia_origem_aluno_id', '<=', 0);
            });

        $query->where(function (QueryBuilder $alunos) use ($alias): void {
            $alunos->whereNull($alias.'.data_matricula')->orWhereColumn($alias.'.data_matricula', '<=', 'at.data_fim');
        });
    }

    /** @return list<int> */
    private function ids(iterable $ids): array
    {
        return collect($ids)
            ->filter(fn ($id): bool => filled($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
