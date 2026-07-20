<?php

namespace App\Services\Avaliacoes;

use App\Models\TurmaComponenteProfessor;
use App\Support\Avaliacoes\AvaliacaoDashboardProgressData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AvaliacaoDashboardProgressService
{
    /**
     * Calcula o progresso em lote usando os fatos consolidados. Quando professorIds
     * é informado, o denominador fica restrito aos pares turma/componente atribuídos
     * ao professor, incluindo fatos ainda não respondidos (professor_id nulo).
     *
     * @param list<int> $avaliacaoIds
     * @param list<int>|null $escolaIds Null representa escopo global.
     * @param list<int>|null $professorIds Null representa acompanhamento amplo.
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

        $statuses = DB::table('avaliacao_dashboard_consolidacoes')
            ->whereIn('avaliacao_id', $avaliacaoIds)
            ->pluck('status', 'avaliacao_id');

        $result = [];

        foreach ($avaliacaoIds as $avaliacaoId) {
            $result[$avaliacaoId] = new AvaliacaoDashboardProgressData(
                consolidacaoStatus: (string) ($statuses[$avaliacaoId] ?? 'pendente'),
                percentual: null,
            );
        }

        $consolidadas = collect($result)
            ->filter(fn (AvaliacaoDashboardProgressData $item): bool => ! $item->emAtualizacao())
            ->keys()
            ->all();

        if ($consolidadas === [] || $escolaIds === []) {
            return $result;
        }

        $query = DB::table('avaliacao_dashboard_fatos')
            ->whereIn('avaliacao_id', $consolidadas);

        if (is_array($escolaIds)) {
            $query->whereIn('escola_id', $this->ids($escolaIds));
        }

        if (is_array($professorIds)) {
            $this->applyProfessorScope($query, $professorIds);
        }

        $progressos = $query
            ->selectRaw('avaliacao_id, COUNT(*) AS total, SUM(CASE WHEN respondida = 1 AND observacao_pendente = 0 THEN 1 ELSE 0 END) AS respondidas')
            ->groupBy('avaliacao_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                (int) $row->avaliacao_id => (int) $row->total > 0
                    ? round(((int) $row->respondidas / (int) $row->total) * 100, 2)
                    : null,
            ]);

        foreach ($consolidadas as $avaliacaoId) {
            $result[$avaliacaoId] = new AvaliacaoDashboardProgressData(
                consolidacaoStatus: 'consolidado',
                percentual: $progressos->get($avaliacaoId),
            );
        }

        return $result;
    }

    /** @param list<int> $professorIds */
    private function applyProfessorScope(Builder $query, array $professorIds): void
    {
        $vinculos = TurmaComponenteProfessor::query()
            ->whereIn('professor_id', $this->ids($professorIds))
            ->where('tem_professor', true)
            ->get(['turma_id', 'componente_curricular_id'])
            ->groupBy('turma_id');

        if ($vinculos->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $pares) use ($vinculos): void {
            foreach ($vinculos as $turmaId => $items) {
                $pares->orWhere(function (Builder $par) use ($turmaId, $items): void {
                    $par
                        ->where('turma_id', (int) $turmaId)
                        ->whereIn(
                            'componente_curricular_id',
                            $items->pluck('componente_curricular_id')->map(fn ($id): int => (int) $id)->all(),
                        );
                });
            }
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
