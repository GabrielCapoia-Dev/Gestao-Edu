<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use App\Models\Serie;
use App\Models\Turma;
use Illuminate\Support\Collection;

class TurmaAvaliacaoAlunoScopeService
{
    private const SUFIXO_SERIE_INTEGRAL = ' - Integral';

    private const CODIGO_SERIE_SRM = 'srm_serie';

    private const NOME_SERIE_SRM = 'Sala de Recursos Multifuncionais';

    /**
     * Resolve a turma de origem e o tipo de vinculo elegivel para cada turma avaliativa.
     *
     * @param  Collection<int, Turma>  $turmas
     * @return array<int, array{turma_origem_id: int, tipo_vinculo: string}>
     */
    public function escoposPorTurma(Collection $turmas): array
    {
        $turmas = $turmas
            ->filter(fn ($turma): bool => $turma instanceof Turma && (int) $turma->id > 0)
            ->keyBy(fn (Turma $turma): int => (int) $turma->id);

        if ($turmas->isEmpty()) {
            return [];
        }

        $turmas = Turma::query()
            ->whereIn('id', $turmas->keys()->all())
            ->with('serie:id,codigo,nome')
            ->get(['id', 'nome', 'turno', 'id_serie', 'id_escola'])
            ->keyBy(fn (Turma $turma): int => (int) $turma->id);

        $turmasSrm = $turmas
            ->filter(fn (Turma $turma): bool => $this->serieEhSrm($turma->serie))
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->flip();

        $turmasPrincipais = $turmas
            ->reject(fn (Turma $turma): bool => $turmasSrm->has((int) $turma->id));

        $comAlunosDiretos = Aluno::query()
            ->whereIn('id_turma', $turmasPrincipais->keys()->all())
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
            ->where('status', '!=', Aluno::STATUS_PENDENTE)
            ->pluck('id_turma')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->flip();

        $nomesBase = $turmasPrincipais
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
                ->whereHas('alunos', fn ($query) => $query
                    ->where('tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
                    ->where('status', '!=', Aluno::STATUS_PENDENTE))
                ->get(['id', 'nome', 'turno', 'id_serie', 'id_escola'])
                ->groupBy(fn (Turma $turma): string => $this->chaveTurma(
                    (int) $turma->id_escola,
                    (int) $turma->id_serie,
                    (string) $turma->nome,
                    (string) $turma->turno,
                ));

        return $turmas->mapWithKeys(function (Turma $turma) use ($turmasSrm, $comAlunosDiretos, $seriesBase, $candidatas): array {
            $turmaId = (int) $turma->id;

            if ($turmasSrm->has($turmaId)) {
                return [$turmaId => [
                    'turma_origem_id' => $turmaId,
                    'tipo_vinculo' => Aluno::TIPO_VINCULO_CONTRA_TURNO,
                ]];
            }

            $origemId = $turmaId;

            if (! $comAlunosDiretos->has($turmaId)) {
                $nomeBase = $this->nomeBaseIntegral($turma->serie?->nome);
                $serieBase = $nomeBase ? $seriesBase->get($nomeBase) : null;

                if ($serieBase) {
                    $pares = $candidatas->get($this->chaveTurma(
                        (int) $turma->id_escola,
                        (int) $serieBase->id,
                        (string) $turma->nome,
                        (string) $turma->turno,
                    ), collect());

                    if ($pares->count() === 1) {
                        $origemId = (int) $pares->first()->id;
                    }
                }
            }

            return [$turmaId => [
                'turma_origem_id' => $origemId,
                'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            ]];
        })->all();
    }

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
        return collect($this->escoposPorTurma($turmas))
            ->map(fn (array $escopo): int => $escopo['turma_origem_id'])
            ->all();
    }

    private function nomeBaseIntegral(?string $nomeSerie): ?string
    {
        if (! $nomeSerie || ! str_ends_with($nomeSerie, self::SUFIXO_SERIE_INTEGRAL)) {
            return null;
        }

        return substr($nomeSerie, 0, -strlen(self::SUFIXO_SERIE_INTEGRAL));
    }

    private function serieEhSrm(?Serie $serie): bool
    {
        return $serie !== null
            && ($serie->codigo === self::CODIGO_SERIE_SRM || $serie->nome === self::NOME_SERIE_SRM);
    }

    private function chaveTurma(int $escolaId, int $serieId, string $nome, string $turno): string
    {
        return implode('|', [$escolaId, $serieId, mb_strtolower(trim($nome)), mb_strtolower(trim($turno))]);
    }
}
