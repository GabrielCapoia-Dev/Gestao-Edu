<?php

namespace App\Services\Avaliacoes;

use App\Support\Avaliacoes\AvaliacaoDashboardProgressData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AvaliacaoDashboardProgressService
{
    public function __construct(
        private readonly AvaliacaoDashboardOnDemandQueryService $queries,
    ) {}

    /**
     * Calcula o progresso diretamente dos documentos e da estrutura atual.
     *
     * @param  list<int>  $avaliacaoIds
     * @param  list<int>|null  $escolaIds  Null representa escopo global.
     * @param  list<int>|null  $professorIds  Null representa acompanhamento amplo.
     * @return array<int, AvaliacaoDashboardProgressData>
     */
    public function batch(
        array $avaliacaoIds,
        ?array $escolaIds,
        ?array $professorIds = null,
    ): array {
        $avaliacaoIds = $this->ids($avaliacaoIds);

        if ($avaliacaoIds === []) {
            return [];
        }

        $result = collect($avaliacaoIds)->mapWithKeys(fn (int $avaliacaoId): array => [
            $avaliacaoId => new AvaliacaoDashboardProgressData(
                consolidacaoStatus: 'consolidado',
                percentual: null,
            ),
        ])->all();

        if ($escolaIds === []) {
            return $result;
        }

        $esperados = $this->queries->esperados($avaliacaoIds);
        $respondidos = $this->queries->respostas($avaliacaoIds, somenteCompletas: true);

        if (is_array($escolaIds)) {
            $escolaIds = $this->ids($escolaIds);
            $esperados->whereIn('t.id_escola', $escolaIds);
            $respondidos->whereIn('t.id_escola', $escolaIds);
        }

        if (is_array($professorIds)) {
            $professorIds = $this->ids($professorIds);
            $this->aplicarEscopoProfessor($esperados, $professorIds, 'tcp_progresso_esperado');
            $this->aplicarEscopoProfessor($respondidos, $professorIds, 'tcp_progresso_respondido');
        }

        $distinctEsperado = $this->distinctCombinacaoExpr('at.avaliacao_id', 'at.turma_id', 'p.id', 'aln.id');
        $distinctRespondido = $this->distinctCombinacaoExpr('ar.avaliacao_id', 'ar.turma_id', 'ar.pauta_id', 'ar.aluno_id');
        $totais = $esperados
            ->groupBy('at.avaliacao_id')
            ->selectRaw("at.avaliacao_id, COUNT(DISTINCT {$distinctEsperado}) AS total")
            ->get()
            ->pluck('total', 'avaliacao_id');
        $concluidos = $respondidos
            ->groupBy('ar.avaliacao_id')
            ->selectRaw("ar.avaliacao_id, COUNT(DISTINCT {$distinctRespondido}) AS total")
            ->get()
            ->pluck('total', 'avaliacao_id');

        foreach ($avaliacaoIds as $avaliacaoId) {
            $total = (int) ($totais[$avaliacaoId] ?? 0);
            $concluido = min((int) ($concluidos[$avaliacaoId] ?? 0), $total);

            $result[$avaliacaoId] = new AvaliacaoDashboardProgressData(
                consolidacaoStatus: 'consolidado',
                percentual: $total > 0 ? round(($concluido / $total) * 100, 2) : null,
            );
        }

        return $result;
    }

    /** @param list<int> $professorIds */
    private function aplicarEscopoProfessor(Builder $query, array $professorIds, string $alias): void
    {
        if ($professorIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query
            ->join("turma_componente_professor as {$alias}", function ($join) use ($alias): void {
                $join->on("{$alias}.turma_id", '=', 't.id')
                    ->where("{$alias}.tem_professor", true)
                    ->where(function ($join) use ($alias): void {
                        $join->whereNull('p.componente_curricular_id')
                            ->orOn("{$alias}.componente_curricular_id", '=', 'p.componente_curricular_id');
                    });
            })
            ->whereIn("{$alias}.professor_id", $professorIds);
    }

    private function distinctCombinacaoExpr(string ...$colunas): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return implode(" || ':' || ", array_map(
                fn (string $coluna): string => "CAST({$coluna} AS TEXT)",
                $colunas,
            ));
        }

        return 'CONCAT_WS(\':\', '.implode(', ', $colunas).')';
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
