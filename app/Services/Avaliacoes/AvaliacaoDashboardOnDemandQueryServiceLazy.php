<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\AvaliacaoTurmaCiclo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Leitura de transição sem driver global e sem conversão em massa.
 *
 * - ciclos operacionalmente inicializados: respostas relacionais;
 * - ciclos ainda não acessados desde o corte: JSON legado;
 * - dashboard/calendário apenas leem; não pagam nem provocam a conversão;
 * - assim que todas as turmas do recorte estão inicializadas, volta ao caminho
 *   relacional puro, sem JSON_TABLE/json_each no SQL cotidiano.
 */
class AvaliacaoDashboardOnDemandQueryServiceLazy extends AvaliacaoDashboardOnDemandQueryService
{
    /** @var array<int, true> */
    private array $estruturaSincronizada = [];

    public function __construct(
        TurmaAvaliacaoAlunoScopeService $turmaAlunoScopeService,
        private readonly AvaliacaoTurmaCicloService $ciclos,
    ) {
        parent::__construct($turmaAlunoScopeService);
    }

    public function esperados(array $avaliacaoIds): QueryBuilder
    {
        $avaliacaoIds = $this->ids($avaliacaoIds);
        $this->garantirEstrutura($avaliacaoIds);

        return parent::esperados($avaliacaoIds);
    }

    public function respostas(array $avaliacaoIds, bool $somenteCompletas = false): QueryBuilder
    {
        $avaliacaoIds = $this->ids($avaliacaoIds);
        $this->garantirEstrutura($avaliacaoIds);

        if ($avaliacaoIds === []) {
            return DB::query()
                ->selectRaw('NULL AS avaliacao_id, NULL AS aluno_id, NULL AS turma_id, NULL AS pauta_id, NULL AS alternativa_id')
                ->whereRaw('1 = 0');
        }

        $possuiLegadoPendente = DB::table('avaliacao_turma_ciclos')
            ->whereIn('avaliacao_id', $avaliacaoIds)
            ->where('status', AvaliacaoTurmaCiclo::STATUS_ABERTA)
            ->whereNull('operacional_inicializado_em')
            ->exists();

        // Caminho normal depois que as turmas já passaram pelo primeiro acesso.
        if (! $possuiLegadoPendente) {
            return parent::respostas($avaliacaoIds, $somenteCompletas);
        }

        $possuiRelacional = DB::table('avaliacao_turma_ciclos')
            ->whereIn('avaliacao_id', $avaliacaoIds)
            ->whereIn('status', [
                AvaliacaoTurmaCiclo::STATUS_ABERTA,
                AvaliacaoTurmaCiclo::STATUS_REABERTA,
            ])
            ->where(function (QueryBuilder $query): void {
                $query
                    ->whereNotNull('operacional_inicializado_em')
                    ->orWhere('status', AvaliacaoTurmaCiclo::STATUS_REABERTA);
            })
            ->exists();

        $legado = $this->respostasLegado($avaliacaoIds);

        if ($possuiRelacional) {
            $relacional = DB::table('avaliacao_respostas_operacionais as ro')
                ->join('avaliacao_turma_ciclos as ciclo_ro', 'ciclo_ro.id', '=', 'ro.ciclo_id')
                ->whereIn('ro.avaliacao_id', $avaliacaoIds)
                ->whereIn('ciclo_ro.status', [
                    AvaliacaoTurmaCiclo::STATUS_ABERTA,
                    AvaliacaoTurmaCiclo::STATUS_REABERTA,
                ])
                ->where(function (QueryBuilder $query): void {
                    $query
                        ->whereNotNull('ciclo_ro.operacional_inicializado_em')
                        ->orWhere('ciclo_ro.status', AvaliacaoTurmaCiclo::STATUS_REABERTA);
                })
                ->select([
                    'ro.avaliacao_id',
                    'ro.aluno_id',
                    'ro.turma_avaliativa_id as turma_id',
                    'ro.pauta_id',
                    'ro.alternativa_id',
                    'ro.professor_id',
                    'ro.observacao',
                    'ro.respondido_em',
                    'ro.componente_curricular_id',
                ]);

            $fontes = $relacional->unionAll($legado);
        } else {
            $fontes = $legado;
        }

        $query = DB::query()
            ->fromSub($fontes, 'ar')
            ->join('turmas as t', 't.id', '=', 'ar.turma_id')
            ->join('alunos as aln', 'aln.id', '=', 'ar.aluno_id')
            ->join('pautas as p', 'p.id', '=', 'ar.pauta_id')
            ->join('alternativas as alt', 'alt.id', '=', 'ar.alternativa_id')
            ->where('p.status', true)
            ->where(function (QueryBuilder $pautas): void {
                $pautas
                    ->whereNull('p.serie_id')
                    ->orWhereColumn('p.serie_id', 't.id_serie');
            });

        $this->aplicarElegibilidadeAluno($query);

        if ($somenteCompletas) {
            $query->where(function (QueryBuilder $completas): void {
                $completas
                    ->where('alt.tem_observacao', false)
                    ->orWhereRaw("TRIM(COALESCE(ar.observacao, '')) <> ''");
            });
        }

        return $query;
    }

    /** @param list<int> $avaliacaoIds */
    private function respostasLegado(array $avaliacaoIds): QueryBuilder
    {
        return match (DB::connection()->getDriverName()) {
            'mysql' => $this->respostasLegadoMysql($avaliacaoIds),
            'sqlite' => $this->respostasLegadoSqlite($avaliacaoIds),
            default => throw new RuntimeException('Driver de banco não suportado para leitura do legado de avaliações.'),
        };
    }

    /** @param list<int> $avaliacaoIds */
    private function respostasLegadoMysql(array $avaliacaoIds): QueryBuilder
    {
        return DB::table('avaliacao_aluno_documentos as d')
            ->join('avaliacao_turma_ciclos as ciclo_json', function ($join): void {
                $join
                    ->on('ciclo_json.avaliacao_id', '=', 'd.avaliacao_id')
                    ->on('ciclo_json.turma_origem_id', '=', 'd.turma_id');
            })
            ->crossJoin(DB::raw(<<<'SQL'
JSON_TABLE(
    COALESCE(d.payload, JSON_OBJECT()),
    '$.pautas.*' COLUMNS (
        pauta_id BIGINT PATH '$.pauta_id' NULL ON EMPTY NULL ON ERROR,
        alternativa_id BIGINT PATH '$.alternativa_id' NULL ON EMPTY NULL ON ERROR,
        professor_id BIGINT PATH '$.professor_id' NULL ON EMPTY NULL ON ERROR,
        observacao TEXT PATH '$.observacao' NULL ON EMPTY NULL ON ERROR,
        respondido_em VARCHAR(64) PATH '$.respondido_em' NULL ON EMPTY NULL ON ERROR,
        componente_curricular_id BIGINT PATH '$.componente_curricular_id' NULL ON EMPTY NULL ON ERROR
    )
) AS item_payload
SQL))
            ->whereIn('d.avaliacao_id', $avaliacaoIds)
            ->whereNull('ciclo_json.operacional_inicializado_em')
            ->where('ciclo_json.status', AvaliacaoTurmaCiclo::STATUS_ABERTA)
            ->whereNotNull('item_payload.pauta_id')
            ->whereNotNull('item_payload.alternativa_id')
            ->select([
                'd.avaliacao_id',
                'd.aluno_id',
                'ciclo_json.turma_avaliativa_id as turma_id',
                'item_payload.pauta_id',
                'item_payload.alternativa_id',
                'item_payload.professor_id',
                'item_payload.observacao',
                'item_payload.respondido_em',
                'item_payload.componente_curricular_id',
            ]);
    }

    /** @param list<int> $avaliacaoIds */
    private function respostasLegadoSqlite(array $avaliacaoIds): QueryBuilder
    {
        return DB::table('avaliacao_aluno_documentos as d')
            ->join('avaliacao_turma_ciclos as ciclo_json', function ($join): void {
                $join
                    ->on('ciclo_json.avaliacao_id', '=', 'd.avaliacao_id')
                    ->on('ciclo_json.turma_origem_id', '=', 'd.turma_id');
            })
            ->crossJoin(DB::raw(<<<'SQL'
json_each(
    COALESCE(d.payload, '{"pautas":{}}'),
    '$.pautas'
) AS item_payload
SQL))
            ->whereIn('d.avaliacao_id', $avaliacaoIds)
            ->whereNull('ciclo_json.operacional_inicializado_em')
            ->where('ciclo_json.status', AvaliacaoTurmaCiclo::STATUS_ABERTA)
            ->select([
                'd.avaliacao_id',
                'd.aluno_id',
                'ciclo_json.turma_avaliativa_id as turma_id',
            ])
            ->selectRaw("COALESCE(CAST(json_extract(item_payload.value, '$.pauta_id') AS INTEGER), CAST(item_payload.key AS INTEGER)) AS pauta_id")
            ->selectRaw("CAST(json_extract(item_payload.value, '$.alternativa_id') AS INTEGER) AS alternativa_id")
            ->selectRaw("CAST(json_extract(item_payload.value, '$.professor_id') AS INTEGER) AS professor_id")
            ->selectRaw("json_extract(item_payload.value, '$.observacao') AS observacao")
            ->selectRaw("json_extract(item_payload.value, '$.respondido_em') AS respondido_em")
            ->selectRaw("CAST(json_extract(item_payload.value, '$.componente_curricular_id') AS INTEGER) AS componente_curricular_id");
    }

    /** @param list<int> $avaliacaoIds */
    private function garantirEstrutura(array $avaliacaoIds): void
    {
        foreach ($avaliacaoIds as $avaliacaoId) {
            if (isset($this->estruturaSincronizada[$avaliacaoId])) {
                continue;
            }

            $totalTurmas = DB::table('avaliacao_turma')
                ->where('avaliacao_id', $avaliacaoId)
                ->count();
            $totalCiclos = DB::table('avaliacao_turma_ciclos')
                ->where('avaliacao_id', $avaliacaoId)
                ->count();

            if ($totalTurmas > $totalCiclos) {
                $avaliacao = Avaliacao::query()->find($avaliacaoId);
                if ($avaliacao) {
                    $this->ciclos->sincronizarAvaliacao($avaliacao);
                }
            }

            $this->estruturaSincronizada[$avaliacaoId] = true;
        }
    }

    private function aplicarElegibilidadeAluno(QueryBuilder $query): void
    {
        $query
            ->whereIn('aln.status', [
                Aluno::STATUS_MATRICULADO,
                Aluno::STATUS_PENDENTE,
            ])
            ->where(function (QueryBuilder $alunos): void {
                $alunos
                    ->where('aln.status', '!=', Aluno::STATUS_PENDENTE)
                    ->orWhereNull('aln.pendencia_origem_aluno_id')
                    ->orWhere('aln.pendencia_origem_aluno_id', '<=', 0);
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
