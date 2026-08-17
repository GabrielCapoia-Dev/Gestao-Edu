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
     * Resolve a turma de origem e o tipo de vínculo de referência
     * para cada turma avaliativa.
     *
     * IMPORTANTE:
     * O tipo_vinculo retornado aqui é apenas informativo/compatibilidade.
     * A seleção dos alunos no workspace NÃO deve mais ser filtrada por ele.
     *
     * Qualquer aluno pertencente à turma de origem e com status elegível
     * poderá participar da avaliação, independentemente de ser:
     * - principal;
     * - contra_turno;
     * - ou outro tipo de vínculo que venha a existir.
     *
     * @param  Collection<int, Turma>  $turmas
     * @return array<int, array{turma_origem_id: int, tipo_vinculo: string}>
     */
    public function escoposPorTurma(Collection $turmas): array
    {
        $turmas = $turmas
            ->filter(
                fn ($turma): bool =>
                    $turma instanceof Turma
                    && (int) $turma->id > 0
            )
            ->keyBy(
                fn (Turma $turma): int =>
                    (int) $turma->id
            );

        if ($turmas->isEmpty()) {
            return [];
        }

        /*
         * Recarrega as turmas diretamente do banco para garantir
         * que série, escola e demais dados estejam atualizados.
         */
        $turmas = Turma::query()
            ->whereIn('id', $turmas->keys()->all())
            ->with('serie:id,codigo,nome')
            ->get([
                'id',
                'nome',
                'turno',
                'id_serie',
                'id_escola',
            ])
            ->keyBy(
                fn (Turma $turma): int =>
                    (int) $turma->id
            );

        /*
         * Identifica turmas de Sala de Recursos Multifuncionais.
         *
         * A SRM continua usando sua própria turma como origem.
         */
        $turmasSrm = $turmas
            ->filter(
                fn (Turma $turma): bool =>
                    $this->serieEhSrm($turma->serie)
            )
            ->keys()
            ->map(
                fn ($id): int =>
                    (int) $id
            )
            ->flip();

        /*
         * Demais turmas serão analisadas normalmente,
         * incluindo a compatibilidade com turmas integrais legadas.
         */
        $turmasPrincipais = $turmas
            ->reject(
                fn (Turma $turma): bool =>
                    $turmasSrm->has((int) $turma->id)
            );

        /*
         * Verifica quais turmas já possuem alunos diretamente vinculados.
         *
         * NÃO filtramos mais por tipo_vinculo.
         *
         * Portanto:
         * - principal conta;
         * - contra_turno conta;
         * - qualquer vínculo futuro também conta.
         */
        $comAlunosDiretos = Aluno::query()
            ->whereIn(
                'id_turma',
                $turmasPrincipais->keys()->all()
            )
            ->whereIn('status', [
                Aluno::STATUS_MATRICULADO,
                Aluno::STATUS_PENDENTE,
            ])
            ->pluck('id_turma')
            ->map(
                fn ($id): int =>
                    (int) $id
            )
            ->unique()
            ->flip();

        /*
         * Procura apenas séries integrais que não possuam
         * alunos diretamente vinculados.
         *
         * Exemplo:
         *
         * "2º Ano - Integral"
         *
         * pode utilizar alunos da série:
         *
         * "2º Ano"
         */
        $nomesBase = $turmasPrincipais
            ->reject(
                fn (Turma $turma): bool =>
                    $comAlunosDiretos->has((int) $turma->id)
            )
            ->map(
                fn (Turma $turma): ?string =>
                    $this->nomeBaseIntegral($turma->serie?->nome)
            )
            ->filter()
            ->unique()
            ->values();

        $seriesBase = Serie::query()
            ->whereIn('nome', $nomesBase->all())
            ->get([
                'id',
                'nome',
            ])
            ->keyBy('nome');

        $escolasIds = $turmas
            ->pluck('id_escola')
            ->map(
                fn ($id): int =>
                    (int) $id
            )
            ->unique()
            ->all();

        $seriesBaseIds = $seriesBase
            ->pluck('id')
            ->map(
                fn ($id): int =>
                    (int) $id
            )
            ->all();

        /*
         * Busca possíveis turmas regulares correspondentes
         * às turmas integrais legadas.
         *
         * Novamente, não existe filtro por tipo_vinculo.
         */
        $candidatas = $seriesBaseIds === []
            ? collect()
            : Turma::query()
                ->whereIn('id_escola', $escolasIds)
                ->whereIn('id_serie', $seriesBaseIds)
                ->whereHas(
                    'alunos',
                    fn ($query) =>
                        $query->whereIn('status', [
                            Aluno::STATUS_MATRICULADO,
                            Aluno::STATUS_PENDENTE,
                        ])
                )
                ->get([
                    'id',
                    'nome',
                    'turno',
                    'id_serie',
                    'id_escola',
                ])
                ->groupBy(
                    fn (Turma $turma): string =>
                        $this->chaveTurma(
                            (int) $turma->id_escola,
                            (int) $turma->id_serie,
                            (string) $turma->nome,
                            (string) $turma->turno,
                        )
                );

        return $turmas
            ->mapWithKeys(
                function (Turma $turma) use (
                    $turmasSrm,
                    $comAlunosDiretos,
                    $seriesBase,
                    $candidatas
                ): array {
                    $turmaId = (int) $turma->id;

                    /*
                     * SRM usa a própria turma como origem.
                     *
                     * tipo_vinculo permanece apenas por compatibilidade
                     * com possíveis consumidores antigos deste Service.
                     *
                     * O workspace atualizado ignora esta propriedade.
                     */
                    if ($turmasSrm->has($turmaId)) {
                        return [
                            $turmaId => [
                                'turma_origem_id' => $turmaId,
                                'tipo_vinculo' => Aluno::TIPO_VINCULO_CONTRA_TURNO,
                            ],
                        ];
                    }

                    /*
                     * Por padrão, a própria turma é a origem dos alunos.
                     */
                    $origemId = $turmaId;

                    /*
                     * Somente tenta resolver uma turma alternativa
                     * quando a turma avaliativa não possui alunos
                     * diretamente vinculados.
                     */
                    if (! $comAlunosDiretos->has($turmaId)) {
                        $nomeBase = $this->nomeBaseIntegral(
                            $turma->serie?->nome
                        );

                        $serieBase = $nomeBase
                            ? $seriesBase->get($nomeBase)
                            : null;

                        if ($serieBase) {
                            $pares = $candidatas->get(
                                $this->chaveTurma(
                                    (int) $turma->id_escola,
                                    (int) $serieBase->id,
                                    (string) $turma->nome,
                                    (string) $turma->turno,
                                ),
                                collect()
                            );

                            /*
                             * Só utiliza a turma alternativa se houver
                             * exatamente uma correspondência inequívoca.
                             */
                            if ($pares->count() === 1) {
                                $origemId = (int) $pares
                                    ->first()
                                    ->id;
                            }
                        }
                    }

                    /*
                     * Mantemos tipo_vinculo por compatibilidade.
                     *
                     * O AvaliacaoTurmaWorkspace NÃO utiliza mais
                     * este valor para filtrar os alunos.
                     */
                    return [
                        $turmaId => [
                            'turma_origem_id' => $origemId,
                            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
                        ],
                    ];
                }
            )
            ->all();
    }

    /**
     * Resolve apenas a turma de origem dos alunos
     * para cada turma avaliativa.
     *
     * Turmas integrais legadas podem concentrar alunos
     * na turma da série regular, mantendo professores,
     * componentes e pautas em uma turma paralela "- Integral".
     *
     * @param  Collection<int, Turma>  $turmas
     * @return array<int, int> turma avaliativa => turma de origem dos alunos
     */
    public function origensPorTurma(Collection $turmas): array
    {
        return collect(
            $this->escoposPorTurma($turmas)
        )
            ->map(
                fn (array $escopo): int =>
                    (int) $escopo['turma_origem_id']
            )
            ->all();
    }

    /**
     * Obtém o nome da série regular correspondente
     * a uma série integral.
     *
     * Exemplo:
     *
     * "2º Ano - Integral"
     * vira
     * "2º Ano"
     */
    private function nomeBaseIntegral(?string $nomeSerie): ?string
    {
        if (
            ! $nomeSerie
            || ! str_ends_with(
                $nomeSerie,
                self::SUFIXO_SERIE_INTEGRAL
            )
        ) {
            return null;
        }

        return substr(
            $nomeSerie,
            0,
            -strlen(self::SUFIXO_SERIE_INTEGRAL)
        );
    }

    /**
     * Verifica se a série representa uma
     * Sala de Recursos Multifuncionais.
     */
    private function serieEhSrm(?Serie $serie): bool
    {
        return $serie !== null
            && (
                $serie->codigo === self::CODIGO_SERIE_SRM
                || $serie->nome === self::NOME_SERIE_SRM
            );
    }

    /**
     * Gera uma chave única para localizar
     * turmas correspondentes.
     */
    private function chaveTurma(
        int $escolaId,
        int $serieId,
        string $nome,
        string $turno
    ): string {
        return implode('|', [
            $escolaId,
            $serieId,
            mb_strtolower(trim($nome)),
            mb_strtolower(trim($turno)),
        ]);
    }
}