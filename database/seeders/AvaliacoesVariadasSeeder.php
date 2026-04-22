<?php

namespace Database\Seeders;

use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoInformacaoComplementar;
use App\Models\AvaliacaoResposta;
use App\Models\ComponenteCurricular;
use App\Models\PeriodoAvaliacao;
use App\Models\Pauta;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AvaliacoesVariadasSeeder extends Seeder
{
    public function run(): void
    {
        $referencia = now()->startOfDay();
        $agora = now();

        $periodos = $this->garantirPeriodosBase();

        $alternativasPorTipo = Alternativa::query()
            ->where('status', true)
            ->whereNotNull('tipo_avaliacao_id')
            ->orderBy('nome')
            ->get(['id', 'tipo_avaliacao_id'])
            ->groupBy('tipo_avaliacao_id')
            ->map(fn (Collection $itens): array => $itens
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all());

        $tiposDisponiveisIds = $alternativasPorTipo
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($tiposDisponiveisIds === []) {
            if ($this->command) {
                $this->command->warn('AvaliacoesVariadasSeeder: nenhuma alternativa ativa com tipo encontrada.');
            }

            return;
        }

        $turmas = Turma::query()
            ->with([
                'componentes:id',
                'alunos:id,id_turma',
            ])
            ->whereHas('alunos')
            ->whereHas('componentes')
            ->orderBy('id')
            ->get(['id', 'id_serie', 'id_escola']);

        if ($turmas->isEmpty()) {
            if ($this->command) {
                $this->command->warn('AvaliacoesVariadasSeeder: nenhuma turma com alunos e componentes encontrada.');
            }

            return;
        }

        $turmasPorId = $turmas->keyBy('id');

        /** @var array<int, array<int>> $componentesPorTurma */
        $componentesPorTurma = [];
        /** @var array<int, array<int>> $turmasPorComponente */
        $turmasPorComponente = [];

        foreach ($turmas as $turma) {
            $turmaId = (int) $turma->id;
            $componentesIds = $turma->componentes
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();

            $componentesPorTurma[$turmaId] = $componentesIds;

            foreach ($componentesIds as $componenteId) {
                $turmasPorComponente[$componenteId] = $turmasPorComponente[$componenteId] ?? [];
                $turmasPorComponente[$componenteId][] = $turmaId;
            }
        }

        if ($turmasPorComponente === []) {
            if ($this->command) {
                $this->command->warn('AvaliacoesVariadasSeeder: nao foi possivel mapear turmas por componente.');
            }

            return;
        }

        $componentesIds = array_keys($turmasPorComponente);
        sort($componentesIds);
        $nomesComponentes = ComponenteCurricular::query()->pluck('nome', 'id');

        $avaliacoesPlanejadas = $this->montarPlanoAvaliacoes(
            referencia: $referencia,
            periodos: $periodos,
            tiposDisponiveisIds: $tiposDisponiveisIds,
            componentesIds: $componentesIds,
            turmasPorComponente: $turmasPorComponente,
            nomesComponentes: $nomesComponentes
        );

        $criadas = 0;
        $atualizadas = 0;
        $respostasGeradas = 0;
        $informacoesComplementaresGeradas = 0;
        $overridesGerados = 0;

        foreach ($avaliacoesPlanejadas as $indice => $dados) {
            $turmasSelecionadas = collect($dados['turma_ids'] ?? [])
                ->map(fn (int $turmaId): ?Turma => $turmasPorId->get($turmaId))
                ->filter()
                ->values();

            if ($turmasSelecionadas->isEmpty()) {
                continue;
            }

            $turmasParaVincular = $turmasSelecionadas
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            $seriesIds = $turmasSelecionadas
                ->pluck('id_serie')
                ->filter()
                ->map(fn ($serieId): int => (int) $serieId)
                ->unique()
                ->values()
                ->all();

            $escolasIds = $turmasSelecionadas
                ->pluck('id_escola')
                ->filter()
                ->map(fn ($escolaId): int => (int) $escolaId)
                ->unique()
                ->values()
                ->all();

            $componentesDasTurmasSelecionadas = $turmasSelecionadas
                ->flatMap(fn (Turma $turma): array => $componentesPorTurma[(int) $turma->id] ?? [])
                ->map(fn ($componenteId): int => (int) $componenteId)
                ->unique()
                ->values()
                ->all();

            $componentesIdsPlanejados = collect($dados['componentes_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => in_array($id, $componentesDasTurmasSelecionadas, true))
                ->unique()
                ->values()
                ->all();

            if ($componentesIdsPlanejados === []) {
                $componentesIdsPlanejados = $componentesDasTurmasSelecionadas;
            }

            if ($componentesIdsPlanejados === []) {
                continue;
            }

            $tipoAvaliacaoId = (int) ($dados['tipo_avaliacao_id'] ?? 0);
            $periodoAvaliacaoId = (int) ($dados['periodo_avaliacao_id'] ?? 0);
            $alternativasTipoIds = $alternativasPorTipo->get($tipoAvaliacaoId, []);

            if ($alternativasTipoIds === []) {
                continue;
            }

            $quantidadePautas = max(3, (int) ($dados['quantidade_pautas'] ?? 4));
            $pautasParaVincular = $this->selecionarPautasParaContexto(
                tipoAvaliacaoId: $tipoAvaliacaoId,
                seriesIds: $seriesIds,
                componentesIds: $componentesIdsPlanejados,
                indiceBase: $indice,
                quantidade: $quantidadePautas,
                nomesComponentes: $nomesComponentes
            );

            if ($pautasParaVincular === []) {
                continue;
            }

            $avaliacao = Avaliacao::query()->updateOrCreate(
                ['nome' => (string) $dados['nome']],
                [
                    'tipo_avaliacao_id' => $tipoAvaliacaoId,
                    'periodo_avaliacao_id' => $periodoAvaliacaoId,
                    'data_inicio' => $dados['data_inicio']->toDateString(),
                    'data_fim' => $dados['data_fim']->toDateString(),
                    'status' => (string) $dados['status'],
                ]
            );

            $avaliacao->pautas()->sync($pautasParaVincular);
            $avaliacao->turmas()->sync($turmasParaVincular);
            $avaliacao->series()->sync($seriesIds);
            $avaliacao->componentes()->sync($componentesIdsPlanejados);
            $avaliacao->escolas()->sync($escolasIds);

            $this->sincronizarAlternativasLegadasDasPautas(
                pautaIds: $pautasParaVincular,
                alternativasPorTipo: $alternativasPorTipo,
                tipoAvaliacaoPadraoId: $tipoAvaliacaoId
            );

            $overridesGerados += $this->sincronizarOverridesDeAlternativas(
                avaliacaoId: (int) $avaliacao->id,
                pautaIds: $pautasParaVincular,
                alternativasIds: $alternativasTipoIds,
                indiceBase: $indice,
                agora: $agora
            );

            $resultadoPreenchimento = $this->preencherAvaliacoesComRespostas(
                avaliacao: $avaliacao,
                turmaIds: $turmasParaVincular,
                pautaIds: $pautasParaVincular,
                coberturaRespostas: (float) ($dados['cobertura_respostas'] ?? 0.75),
                indiceBase: $indice,
                agora: $agora
            );

            $respostasGeradas += $resultadoPreenchimento['respostas'];
            $informacoesComplementaresGeradas += $resultadoPreenchimento['informacoes_complementares'];

            if ($avaliacao->wasRecentlyCreated) {
                $criadas++;
            } else {
                $atualizadas++;
            }
        }

        if ($this->command) {
            $this->command->info('AvaliacoesVariadasSeeder executado com sucesso.');
            $this->command->line('Avaliacoes criadas: ' . $criadas . '.');
            $this->command->line('Avaliacoes atualizadas: ' . $atualizadas . '.');
            $this->command->line('Total processado: ' . ($criadas + $atualizadas) . '.');
            $this->command->line('Overrides de alternativas por pauta: ' . $overridesGerados . '.');
            $this->command->line('Respostas geradas/sincronizadas: ' . $respostasGeradas . '.');
            $this->command->line('Informacoes complementares geradas/sincronizadas: ' . $informacoesComplementaresGeradas . '.');
        }
    }

    private function garantirPeriodosBase(): Collection
    {
        $periodosPadrao = [
            '1o Semestre',
            '2o Semestre',
            '3o Trimestre',
            '4o Bimestre',
        ];

        foreach ($periodosPadrao as $nomePeriodo) {
            PeriodoAvaliacao::query()->updateOrCreate(
                ['nome' => $nomePeriodo],
                ['status' => true]
            );
        }

        return PeriodoAvaliacao::query()
            ->where('status', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);
    }

    /**
     * @param array<int> $tiposDisponiveisIds
     * @param array<int> $componentesIds
     * @param array<int, array<int>> $turmasPorComponente
     * @return array<int, array{
     *     nome: string,
     *     data_inicio: Carbon,
     *     data_fim: Carbon,
     *     status: string,
     *     tipo_avaliacao_id: int,
     *     periodo_avaliacao_id: int,
     *     turma_ids: array<int>,
     *     componentes_ids: array<int>,
     *     quantidade_pautas: int,
     *     cobertura_respostas: float
     * }>
     */
    private function montarPlanoAvaliacoes(
        Carbon $referencia,
        Collection $periodos,
        array $tiposDisponiveisIds,
        array $componentesIds,
        array $turmasPorComponente,
        Collection $nomesComponentes
    ): array {
        if ($componentesIds === [] || $tiposDisponiveisIds === [] || $periodos->isEmpty()) {
            return [];
        }

        $periodos = $periodos->values();
        $statusSequenciaExtras = [
            Avaliacao::STATUS_ATIVA,
            Avaliacao::STATUS_ATIVA,
            Avaliacao::STATUS_ENCERRADA,
            Avaliacao::STATUS_INATIVA,
            Avaliacao::STATUS_CANCELADA,
            Avaliacao::STATUS_ATIVA,
        ];

        $avaliacoes = [];

        foreach ($componentesIds as $indice => $componenteId) {
            $tipoAvaliacaoId = $tiposDisponiveisIds[$indice % count($tiposDisponiveisIds)];
            $periodoAvaliacaoId = (int) ($periodos->get($indice % $periodos->count())->id ?? 0);
            $nomeComponente = (string) ($nomesComponentes[$componenteId] ?? "Componente {$componenteId}");
            $janela = $this->gerarJanelaDatas($referencia, $indice, Avaliacao::STATUS_ATIVA);

            $avaliacoes[] = [
                'nome' => sprintf('[Seed Variadas] Cobertura Geral - %s', $nomeComponente),
                'data_inicio' => $janela['inicio'],
                'data_fim' => $janela['fim'],
                'status' => Avaliacao::STATUS_ATIVA,
                'tipo_avaliacao_id' => (int) $tipoAvaliacaoId,
                'periodo_avaliacao_id' => $periodoAvaliacaoId,
                'turma_ids' => collect($turmasPorComponente[$componenteId] ?? [])
                    ->map(fn ($turmaId): int => (int) $turmaId)
                    ->unique()
                    ->values()
                    ->all(),
                'componentes_ids' => [(int) $componenteId],
                'quantidade_pautas' => 4 + $this->hashIndice(3, "pauta-base|{$componenteId}"),
                'cobertura_respostas' => $this->coberturaParaSeed("cobertura-base|{$componenteId}", 68, 98),
            ];
        }

        $templatesExtras = [
            'Monitoramento quinzenal',
            'Formativa integrada',
            'Sondagem pedagogica',
            'Consolidacao mensal',
            'Acompanhamento por habilidade',
            'Trilha de recomposicao',
        ];

        $quantidadeExtras = max(10, min(24, count($componentesIds) * 3));

        for ($indice = 0; $indice < $quantidadeExtras; $indice++) {
            $quantidadeComponentes = min(
                count($componentesIds),
                max(1, 1 + $this->hashIndice(3, "qtd-comp-extra|{$indice}"))
            );

            $componentesSelecionados = $this->recorteCircularIds(
                $componentesIds,
                $indice + 2,
                $quantidadeComponentes
            );

            $turmasPool = collect($componentesSelecionados)
                ->flatMap(fn (int $componenteId): array => $turmasPorComponente[$componenteId] ?? [])
                ->map(fn ($turmaId): int => (int) $turmaId)
                ->unique()
                ->values()
                ->all();

            if ($turmasPool === []) {
                continue;
            }

            $quantidadeTurmas = min(
                count($turmasPool),
                max(2, 2 + $this->hashIndice(4, "qtd-turmas-extra|{$indice}"))
            );

            $status = $statusSequenciaExtras[$this->hashIndice(count($statusSequenciaExtras), "status-extra|{$indice}")];
            $janela = $this->gerarJanelaDatas($referencia, 100 + $indice, $status);
            $tipoAvaliacaoId = $tiposDisponiveisIds[($indice + 1) % count($tiposDisponiveisIds)];
            $periodoAvaliacaoId = (int) ($periodos->get(($indice + 1) % $periodos->count())->id ?? 0);
            $nomeTemplate = $templatesExtras[$indice % count($templatesExtras)];
            $resumoComponentes = $this->resumoComponentes($componentesSelecionados, $nomesComponentes);

            $avaliacoes[] = [
                'nome' => sprintf('[Seed Variadas] %s %02d - %s', $nomeTemplate, $indice + 1, $resumoComponentes),
                'data_inicio' => $janela['inicio'],
                'data_fim' => $janela['fim'],
                'status' => $status,
                'tipo_avaliacao_id' => (int) $tipoAvaliacaoId,
                'periodo_avaliacao_id' => $periodoAvaliacaoId,
                'turma_ids' => $this->recorteCircularIds(
                    $turmasPool,
                    $indice * 3,
                    max(1, $quantidadeTurmas)
                ),
                'componentes_ids' => $componentesSelecionados,
                'quantidade_pautas' => 3 + $this->hashIndice(5, "pauta-extra|{$indice}"),
                'cobertura_respostas' => $this->coberturaParaSeed("cobertura-extra|{$indice}", 35, 90),
            ];
        }

        return $avaliacoes;
    }

    /**
     * @return array{inicio: Carbon, fim: Carbon}
     */
    private function gerarJanelaDatas(Carbon $referencia, int $indice, string $status): array
    {
        $offset = $this->hashIndice(140, "janela|{$indice}|{$status}");

        return match ($status) {
            Avaliacao::STATUS_ENCERRADA => [
                'inicio' => $referencia->copy()->subDays(220 + $offset),
                'fim' => $referencia->copy()->subDays(140 + ($offset % 90)),
            ],
            Avaliacao::STATUS_INATIVA => [
                'inicio' => $referencia->copy()->addDays(7 + ($offset % 40)),
                'fim' => $referencia->copy()->addDays(30 + ($offset % 65)),
            ],
            Avaliacao::STATUS_CANCELADA => [
                'inicio' => $referencia->copy()->subDays(70 + ($offset % 80)),
                'fim' => $referencia->copy()->addDays(3 + ($offset % 20)),
            ],
            default => [
                'inicio' => $referencia->copy()->subDays(20 + ($offset % 35)),
                'fim' => $referencia->copy()->addDays(12 + ($offset % 40)),
            ],
        };
    }

    private function coberturaParaSeed(string $seed, int $minPercentual, int $maxPercentual): float
    {
        $minimo = max(0, min(100, $minPercentual));
        $maximo = max($minimo, min(100, $maxPercentual));
        $faixa = max(1, ($maximo - $minimo) + 1);
        $percentual = $minimo + $this->hashIndice($faixa, $seed);

        return round($percentual / 100, 2);
    }

    /**
     * @param array<int> $componentesIds
     */
    private function resumoComponentes(array $componentesIds, Collection $nomesComponentes): string
    {
        $nomes = collect($componentesIds)
            ->map(fn (int $componenteId): string => (string) ($nomesComponentes[$componenteId] ?? "Componente {$componenteId}"))
            ->values();

        if ($nomes->isEmpty()) {
            return 'Contexto geral';
        }

        if ($nomes->count() === 1) {
            return (string) $nomes->first();
        }

        if ($nomes->count() === 2) {
            return $nomes->implode(' e ');
        }

        return $nomes->take(2)->implode(', ') . ' e +' . ($nomes->count() - 2);
    }

    /**
     * @param array<int> $seriesIds
     * @param array<int> $componentesIds
     * @return array<int>
     */
    private function selecionarPautasParaContexto(
        int $tipoAvaliacaoId,
        array $seriesIds,
        array $componentesIds,
        int $indiceBase,
        int $quantidade,
        Collection $nomesComponentes
    ): array {
        $query = Pauta::query()
            ->where('status', true)
            ->where('tipo_avaliacao_id', $tipoAvaliacaoId);

        if ($seriesIds !== []) {
            $query->where(function ($subQuery) use ($seriesIds): void {
                $subQuery->whereIn('serie_id', $seriesIds)
                    ->orWhereNull('serie_id');
            });
        }

        if ($componentesIds !== []) {
            $query->where(function ($subQuery) use ($componentesIds): void {
                $subQuery->whereIn('componente_curricular_id', $componentesIds)
                    ->orWhereNull('componente_curricular_id');
            });
        }

        $pautasIds = $query
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $quantidadeMinimaEsperada = max(3, min($quantidade, max(1, count($componentesIds))));

        if (count($pautasIds) < $quantidadeMinimaEsperada) {
            if ($componentesIds === []) {
                $serieId = $seriesIds[0] ?? null;
                $pauta = Pauta::query()->updateOrCreate(
                    [
                        'texto' => '[Seed Variadas] Participacao geral e protagonismo do aluno',
                        'tipo_avaliacao_id' => $tipoAvaliacaoId,
                        'componente_curricular_id' => null,
                        'serie_id' => $serieId,
                    ],
                    ['status' => true]
                );

                $pautasIds[] = (int) $pauta->id;
            }

            foreach ($componentesIds as $offset => $componenteId) {
                $serieId = $seriesIds[$offset % max(count($seriesIds), 1)] ?? null;
                $nomeComponente = (string) ($nomesComponentes[$componenteId] ?? "Componente {$componenteId}");

                for ($i = 0; $i < 2; $i++) {
                    $pauta = Pauta::query()->updateOrCreate(
                        [
                            'texto' => sprintf(
                                '[Seed Variadas] %s - Indicador %d',
                                $nomeComponente,
                                1 + (($indiceBase + $offset + $i) % 9)
                            ),
                            'tipo_avaliacao_id' => $tipoAvaliacaoId,
                            'componente_curricular_id' => (int) $componenteId,
                            'serie_id' => $serieId,
                        ],
                        ['status' => true]
                    );

                    $pautasIds[] = (int) $pauta->id;
                }
            }

            if ($seriesIds !== []) {
                $pautaGeral = Pauta::query()->updateOrCreate(
                    [
                        'texto' => '[Seed Variadas] Desenvolvimento socioemocional e autonomia',
                        'tipo_avaliacao_id' => $tipoAvaliacaoId,
                        'componente_curricular_id' => null,
                        'serie_id' => (int) $seriesIds[0],
                    ],
                    ['status' => true]
                );

                $pautasIds[] = (int) $pautaGeral->id;
            }
        }

        $pautasIds = array_values(array_unique(array_map('intval', $pautasIds)));

        if ($pautasIds === []) {
            return [];
        }

        $pautas = Pauta::query()
            ->whereIn('id', $pautasIds)
            ->get(['id', 'componente_curricular_id']);

        $selecionadas = [];

        foreach ($componentesIds as $componenteId) {
            $pautaComponente = $pautas->firstWhere('componente_curricular_id', $componenteId);

            if ($pautaComponente) {
                $selecionadas[] = (int) $pautaComponente->id;
            }
        }

        $selecionadas = array_values(array_unique($selecionadas));
        $quantidadeFinal = min(max(1, $quantidade), count($pautasIds));
        $inicio = $indiceBase % count($pautasIds);

        for ($i = 0; $i < count($pautasIds) && count($selecionadas) < $quantidadeFinal; $i++) {
            $pautaId = (int) $pautasIds[($inicio + $i) % count($pautasIds)];

            if (! in_array($pautaId, $selecionadas, true)) {
                $selecionadas[] = $pautaId;
            }
        }

        return array_slice($selecionadas, 0, $quantidadeFinal);
    }

    private function sincronizarAlternativasLegadasDasPautas(
        array $pautaIds,
        Collection $alternativasPorTipo,
        int $tipoAvaliacaoPadraoId
    ): void {
        if ($pautaIds === []) {
            return;
        }

        Pauta::query()
            ->whereIn('id', $pautaIds)
            ->get(['id', 'tipo_avaliacao_id'])
            ->each(function (Pauta $pauta) use ($alternativasPorTipo, $tipoAvaliacaoPadraoId): void {
                $tipoAvaliacaoId = (int) ($pauta->tipo_avaliacao_id ?: $tipoAvaliacaoPadraoId);
                $alternativasIds = $alternativasPorTipo->get(
                    $tipoAvaliacaoId,
                    $alternativasPorTipo->get($tipoAvaliacaoPadraoId, [])
                );

                if ($alternativasIds === []) {
                    return;
                }

                $pauta->alternativas()->syncWithoutDetaching($alternativasIds);
            });
    }

    private function sincronizarOverridesDeAlternativas(
        int $avaliacaoId,
        array $pautaIds,
        array $alternativasIds,
        int $indiceBase,
        Carbon $agora
    ): int {
        DB::table('avaliacao_pauta_alternativa')
            ->where('avaliacao_id', $avaliacaoId)
            ->delete();

        if ($pautaIds === [] || count($alternativasIds) < 2 || ($indiceBase % 2) !== 0) {
            return 0;
        }

        $quantidadePautasComOverride = min(max(1, (int) floor(count($pautaIds) / 3)), 3);
        $pautasComOverride = $this->recorteCircularIds($pautaIds, $indiceBase, $quantidadePautasComOverride);

        $linhas = [];

        foreach ($pautasComOverride as $offset => $pautaId) {
            $quantidadeAlternativas = min(
                count($alternativasIds),
                2 + (($indiceBase + $offset) % 2)
            );

            $alternativasOverride = $this->recorteCircularIds(
                $alternativasIds,
                $indiceBase + $offset,
                $quantidadeAlternativas
            );

            foreach ($alternativasOverride as $alternativaId) {
                $linhas[] = [
                    'avaliacao_id' => $avaliacaoId,
                    'pauta_id' => (int) $pautaId,
                    'alternativa_id' => (int) $alternativaId,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
            }
        }

        if ($linhas === []) {
            return 0;
        }

        DB::table('avaliacao_pauta_alternativa')->insert($linhas);

        return count($linhas);
    }

    private function preencherAvaliacoesComRespostas(
        Avaliacao $avaliacao,
        array $turmaIds,
        array $pautaIds,
        float $coberturaRespostas,
        int $indiceBase,
        Carbon $agora
    ): array {
        AvaliacaoResposta::query()
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->delete();

        AvaliacaoInformacaoComplementar::query()
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->delete();

        if ($turmaIds === [] || $pautaIds === []) {
            return [
                'respostas' => 0,
                'informacoes_complementares' => 0,
            ];
        }

        $turmas = Turma::query()
            ->with([
                'alunos' => fn ($query) => $query->orderBy('id')->select('id', 'id_turma', 'nome'),
                'componentes' => fn ($query) => $query->select('componentes_curriculares.id'),
            ])
            ->whereIn('id', $turmaIds)
            ->get(['id']);

        $pautas = Pauta::query()
            ->whereIn('id', $pautaIds)
            ->get(['id', 'texto', 'componente_curricular_id', 'tipo_avaliacao_id']);

        if ($turmas->isEmpty() || $pautas->isEmpty()) {
            return [
                'respostas' => 0,
                'informacoes_complementares' => 0,
            ];
        }

        $alternativasPorPauta = $this->mapearAlternativasPorPauta($avaliacao, $pautas);

        $vinculosProfessores = TurmaComponenteProfessor::query()
            ->whereIn('turma_id', $turmas->pluck('id')->all())
            ->get(['turma_id', 'componente_curricular_id', 'professor_id']);

        $professorPorTurmaComponente = [];
        $professorPadraoPorTurma = [];

        foreach ($vinculosProfessores as $vinculo) {
            $turmaId = (int) $vinculo->turma_id;
            $componenteId = (int) $vinculo->componente_curricular_id;
            $professorId = $vinculo->professor_id ? (int) $vinculo->professor_id : null;

            if (! is_null($professorId)) {
                $professorPorTurmaComponente[$turmaId][$componenteId] = $professorId;
                $professorPadraoPorTurma[$turmaId] = $professorPadraoPorTurma[$turmaId] ?? $professorId;
            }
        }

        $respostasPayload = [];
        $informacoesPayload = [];
        $coberturaPercentual = (int) round(max(0.0, min(1.0, $coberturaRespostas)) * 100);

        foreach ($turmas as $turma) {
            $turmaId = (int) $turma->id;
            $alunos = $turma->alunos;

            if ($alunos->isEmpty()) {
                continue;
            }

            $componentesDaTurma = $turma->componentes
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $alunosComResposta = [];

            foreach ($pautas as $pauta) {
                $pautaId = (int) $pauta->id;
                $componenteDaPauta = $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null;

                if (! is_null($componenteDaPauta) && ! in_array($componenteDaPauta, $componentesDaTurma, true)) {
                    continue;
                }

                $alternativas = $alternativasPorPauta[$pautaId] ?? collect();

                if ($alternativas->isEmpty()) {
                    continue;
                }

                $professorId = $professorPadraoPorTurma[$turmaId] ?? null;

                if (! is_null($componenteDaPauta)) {
                    $professorId = $professorPorTurmaComponente[$turmaId][$componenteDaPauta] ?? $professorId;
                }

                foreach ($alunos as $aluno) {
                    $alunoId = (int) $aluno->id;
                    $marcadorResposta = $this->hashPercentual(
                        "resp|{$avaliacao->id}|{$turmaId}|{$pautaId}|{$alunoId}|{$indiceBase}"
                    );

                    if ($marcadorResposta >= $coberturaPercentual) {
                        continue;
                    }

                    $indiceAlternativa = $this->hashIndice(
                        $alternativas->count(),
                        "alt|{$avaliacao->id}|{$turmaId}|{$pautaId}|{$alunoId}|{$indiceBase}"
                    );

                    /** @var Alternativa|null $alternativa */
                    $alternativa = $alternativas->values()->get($indiceAlternativa);

                    if (! $alternativa) {
                        continue;
                    }

                    $observacao = null;

                    if ((bool) $alternativa->tem_observacao) {
                        $observacao = sprintf(
                            'Registro automatico seed: acompanhamento individual na pauta "%s".',
                            mb_substr((string) $pauta->texto, 0, 70)
                        );
                    }

                    $respostasPayload[] = [
                        'avaliacao_id' => (int) $avaliacao->id,
                        'pauta_id' => $pautaId,
                        'turma_id' => $turmaId,
                        'aluno_id' => $alunoId,
                        'professor_id' => $professorId,
                        'alternativa_id' => (int) $alternativa->id,
                        'observacao' => $observacao,
                        'respondido_em' => $agora
                            ->copy()
                            ->subDays($this->hashIndice(60, "dia|{$avaliacao->id}|{$turmaId}|{$pautaId}|{$alunoId}"))
                            ->subMinutes($this->hashIndice(600, "min|{$avaliacao->id}|{$turmaId}|{$pautaId}|{$alunoId}")),
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ];

                    $alunosComResposta[$alunoId] = true;
                }
            }

            foreach ($alunos as $aluno) {
                $alunoId = (int) $aluno->id;

                if (! isset($alunosComResposta[$alunoId])) {
                    continue;
                }

                $marcadorInfo = $this->hashPercentual(
                    "info|{$avaliacao->id}|{$turmaId}|{$alunoId}|{$indiceBase}"
                );

                if ($marcadorInfo > 35) {
                    continue;
                }

                $informacoesPayload[] = [
                    'avaliacao_id' => (int) $avaliacao->id,
                    'turma_id' => $turmaId,
                    'aluno_id' => $alunoId,
                    'professor_id' => $professorPadraoPorTurma[$turmaId] ?? null,
                    'informacoes_complementares' => 'Observacao complementar gerada para testes de dashboard e filtros analiticos.',
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
            }
        }

        if ($respostasPayload !== []) {
            AvaliacaoResposta::query()->upsert(
                $respostasPayload,
                ['avaliacao_id', 'pauta_id', 'turma_id', 'aluno_id'],
                ['professor_id', 'alternativa_id', 'observacao', 'respondido_em', 'updated_at']
            );
        }

        if ($informacoesPayload !== []) {
            AvaliacaoInformacaoComplementar::query()->upsert(
                $informacoesPayload,
                ['avaliacao_id', 'turma_id', 'aluno_id'],
                ['professor_id', 'informacoes_complementares', 'updated_at']
            );
        }

        return [
            'respostas' => count($respostasPayload),
            'informacoes_complementares' => count($informacoesPayload),
        ];
    }

    /**
     * @return array<int, Collection<int, Alternativa>>
     */
    private function mapearAlternativasPorPauta(Avaliacao $avaliacao, Collection $pautas): array
    {
        $pautaIds = $pautas
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($pautaIds === []) {
            return [];
        }

        $overridesPorPauta = DB::table('avaliacao_pauta_alternativa')
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->whereIn('pauta_id', $pautaIds)
            ->get(['pauta_id', 'alternativa_id'])
            ->groupBy('pauta_id')
            ->map(fn (Collection $linhas): array => $linhas
                ->pluck('alternativa_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all());

        $legadasPorPauta = DB::table('alternativa_pauta')
            ->whereIn('pauta_id', $pautaIds)
            ->get(['pauta_id', 'alternativa_id'])
            ->groupBy('pauta_id')
            ->map(fn (Collection $linhas): array => $linhas
                ->pluck('alternativa_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all());

        $tipoIds = $pautas
            ->pluck('tipo_avaliacao_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->push((int) $avaliacao->tipo_avaliacao_id)
            ->unique()
            ->values()
            ->all();

        $alternativasAtivas = Alternativa::query()
            ->where('status', true)
            ->whereIn('tipo_avaliacao_id', $tipoIds)
            ->orderBy('nome')
            ->get(['id', 'tipo_avaliacao_id', 'nome', 'tem_observacao']);

        $alternativasPorTipo = $alternativasAtivas
            ->groupBy('tipo_avaliacao_id')
            ->map(fn (Collection $itens): Collection => $itens->values());

        $alternativasPorId = $alternativasAtivas->keyBy('id');
        $resultado = [];

        foreach ($pautas as $pauta) {
            $pautaId = (int) $pauta->id;
            $alternativas = collect();
            $overrideIds = $overridesPorPauta->get($pautaId, []);

            if ($overrideIds !== []) {
                $alternativas = collect($overrideIds)
                    ->map(fn (int $alternativaId) => $alternativasPorId->get($alternativaId))
                    ->filter()
                    ->values();
            }

            if ($alternativas->isEmpty()) {
                $alternativas = $alternativasPorTipo->get((int) $pauta->tipo_avaliacao_id, collect());
            }

            if ($alternativas->isEmpty()) {
                $alternativas = $alternativasPorTipo->get((int) $avaliacao->tipo_avaliacao_id, collect());
            }

            if ($alternativas->isEmpty()) {
                $alternativas = collect($legadasPorPauta->get($pautaId, []))
                    ->map(fn (int $alternativaId) => $alternativasPorId->get($alternativaId))
                    ->filter()
                    ->values();
            }

            $resultado[$pautaId] = $alternativas->values();
        }

        return $resultado;
    }

    private function hashPercentual(string $seed): int
    {
        return $this->hashInteiro($seed) % 100;
    }

    private function hashIndice(int $limite, string $seed): int
    {
        if ($limite <= 0) {
            return 0;
        }

        return $this->hashInteiro($seed) % $limite;
    }

    private function hashInteiro(string $seed): int
    {
        return (int) sprintf('%u', crc32($seed));
    }

    /**
     * @param array<int> $ids
     * @return array<int>
     */
    private function recorteCircularIds(array $ids, int $indiceBase, int $tamanho): array
    {
        if ($ids === [] || $tamanho <= 0) {
            return [];
        }

        $total = count($ids);

        if ($tamanho >= $total) {
            return array_values(array_unique(array_map('intval', $ids)));
        }

        $inicio = $indiceBase % $total;
        $selecionados = [];

        for ($i = 0; $i < $tamanho; $i++) {
            $selecionados[] = (int) $ids[($inicio + $i) % $total];
        }

        return array_values(array_unique($selecionados));
    }
}

