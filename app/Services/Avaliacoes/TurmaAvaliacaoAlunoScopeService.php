<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use App\Models\Serie;
use App\Models\Turma;
use Illuminate\Support\Collection;

class TurmaAvaliacaoAlunoScopeService
{
    private const SUFIXO_SERIE_INTEGRAL = ' - Integral';

    /**
     * Resolve de qual turma devem vir os alunos de cada turma avaliativa.
     *
     * Turmas integrais legadas podem concentrar alunos na turma da serie regular,
     * mantendo professores, componentes e pautas em uma turma paralela "- Integral".
     *
     * @param  Collection<int, Turma>  $turmas
     * @return array<int, int> turma avaliativa => turma de origem dos alunos
     */
    public function origensPorTurma(Collection $turmas): array
    {
        $turmas = $turmas
            ->filter(fn ($turma): bool => $turma instanceof Turma && (int) $turma->id > 0)
            ->keyBy(fn (Turma $turma): int => (int) $turma->id);

        if ($turmas->isEmpty()) {
            return [];
        }

        $turmas = Turma::query()
            ->whereIn('id', $turmas->keys()->all())
            ->with('serie:id,nome')
            ->get(['id', 'nome', 'turno', 'id_serie', 'id_escola'])
            ->keyBy(fn (Turma $turma): int => (int) $turma->id);

        $comAlunosDiretos = Aluno::query()
            ->whereIn('id_turma', $turmas->keys()->all())
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
            ->pluck('id_turma')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->flip();

        $nomesBase = $turmas
            ->reject(fn (Turma $turma): bool => $comAlunosDiretos->has((int) $turma->id))
            ->map(fn (Turma $turma): ?string => $this->nomeBaseIntegral($turma->serie?->nome))
            ->filter()
            ->unique()
            ->values();

        $seriesBase = Serie::query()
            ->whereIn('nome', $nomesBase->all())
            ->get(['id', 'nome'])
            ->keyBy('nome');

        $escolasIds = $turmas->pluck('id_escola')->map(fn ($id): int => (int) $id)->unique()->all();
        $seriesBaseIds = $seriesBase->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $candidatas = $seriesBaseIds === []
            ? collect()
            : Turma::query()
                ->whereIn('id_escola', $escolasIds)
                ->whereIn('id_serie', $seriesBaseIds)
                ->whereHas('alunos', fn ($query) => $query->where('tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL))
                ->get(['id', 'nome', 'turno', 'id_serie', 'id_escola'])
                ->groupBy(fn (Turma $turma): string => $this->chaveTurma(
                    (int) $turma->id_escola,
                    (int) $turma->id_serie,
                    (string) $turma->nome,
                    (string) $turma->turno,
                ));

        return $turmas->mapWithKeys(function (Turma $turma) use ($comAlunosDiretos, $seriesBase, $candidatas): array {
            $turmaId = (int) $turma->id;

            if ($comAlunosDiretos->has($turmaId)) {
                return [$turmaId => $turmaId];
            }

            $nomeBase = $this->nomeBaseIntegral($turma->serie?->nome);
            $serieBase = $nomeBase ? $seriesBase->get($nomeBase) : null;

            if (! $serieBase) {
                return [$turmaId => $turmaId];
            }

            $pares = $candidatas->get($this->chaveTurma(
                (int) $turma->id_escola,
                (int) $serieBase->id,
                (string) $turma->nome,
                (string) $turma->turno,
            ), collect());

            if ($pares->count() !== 1) {
                return [$turmaId => $turmaId];
            }

            return [$turmaId => (int) $pares->first()->id];
        })->all();
    }

    private function nomeBaseIntegral(?string $nomeSerie): ?string
    {
        if (! $nomeSerie || ! str_ends_with($nomeSerie, self::SUFIXO_SERIE_INTEGRAL)) {
            return null;
        }

        return substr($nomeSerie, 0, -strlen(self::SUFIXO_SERIE_INTEGRAL));
    }

    private function chaveTurma(int $escolaId, int $serieId, string $nome, string $turno): string
    {
        return implode('|', [$escolaId, $serieId, mb_strtolower(trim($nome)), mb_strtolower(trim($turno))]);
    }
}
