<?php

namespace Database\Seeders;

use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoInformacaoComplementar;
use App\Models\AvaliacaoResposta;
use App\Models\ComponenteCurricular;
use App\Models\PeriodoAvaliacao;
use App\Models\Pauta;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AvaliacoesVariadasSeeder extends Seeder
{
    public function run(): void
    {
        $referencia = now()->startOfDay();
        $agora = now();

        $tipoParecer = TipoAvaliacao::query()->firstOrCreate(
            ['nome' => 'Parecer Descritivo'],
            ['status' => true]
        );
        $tipoRubrica = TipoAvaliacao::query()->firstOrCreate(
            ['nome' => 'Rubrica Objetiva'],
            ['status' => true]
        );

        $periodoPrimeiroSemestre = PeriodoAvaliacao::query()->firstOrCreate(
            ['nome' => '1o Semestre'],
            ['status' => true]
        );
        $periodoSegundoSemestre = PeriodoAvaliacao::query()->firstOrCreate(
            ['nome' => '2o Semestre'],
            ['status' => true]
        );

        $alternativasPorTipo = [
            (int) $tipoParecer->id => $this->garantirAlternativasDoTipo((int) $tipoParecer->id, [
                ['nome' => 'Excelente', 'tem_observacao' => false, 'observacao' => null],
                ['nome' => 'Bom', 'tem_observacao' => false, 'observacao' => null],
                ['nome' => 'Parcial', 'tem_observacao' => true, 'observacao' => 'Descreva os pontos que ainda precisam evoluir.'],
                ['nome' => 'Nao', 'tem_observacao' => true, 'observacao' => 'Informe os pontos de intervencao sugeridos.'],
            ]),
            (int) $tipoRubrica->id => $this->garantirAlternativasDoTipo((int) $tipoRubrica->id, [
                ['nome' => 'Atingiu', 'tem_observacao' => false, 'observacao' => null],
                ['nome' => 'Em desenvolvimento', 'tem_observacao' => true, 'observacao' => 'Registre evidencias da evolucao observada.'],
                ['nome' => 'Nao atingiu', 'tem_observacao' => true, 'observacao' => 'Registre plano de recuperacao sugerido.'],
            ]),
        ];

        $avaliacoes = [
            [
                'nome' => '[Seed Avaliacoes] Diagnostica Bimestre 1',
                'data_inicio' => $referencia->copy()->subDays(20),
                'data_fim' => $referencia->copy()->addDays(12),
                'status' => Avaliacao::STATUS_ATIVA,
                'tipo_avaliacao_id' => (int) $tipoParecer->id,
                'periodo_avaliacao_id' => (int) $periodoPrimeiroSemestre->id,
                'quantidade_turmas' => 3,
                'quantidade_pautas' => 6,
                'cobertura_respostas' => 0.88,
            ],
            [
                'nome' => '[Seed Avaliacoes] Formativa Intermediaria',
                'data_inicio' => $referencia->copy()->subDays(8),
                'data_fim' => $referencia->copy()->addDays(20),
                'status' => Avaliacao::STATUS_ATIVA,
                'tipo_avaliacao_id' => (int) $tipoParecer->id,
                'periodo_avaliacao_id' => (int) $periodoPrimeiroSemestre->id,
                'quantidade_turmas' => 2,
                'quantidade_pautas' => 5,
                'cobertura_respostas' => 0.76,
            ],
            [
                'nome' => '[Seed Avaliacoes] Sondagem de Entrada',
                'data_inicio' => $referencia->copy()->subDays(45),
                'data_fim' => $referencia->copy()->subDays(30),
                'status' => Avaliacao::STATUS_ENCERRADA,
                'tipo_avaliacao_id' => (int) $tipoRubrica->id,
                'periodo_avaliacao_id' => (int) $periodoPrimeiroSemestre->id,
                'quantidade_turmas' => 2,
                'quantidade_pautas' => 4,
                'cobertura_respostas' => 0.97,
            ],
            [
                'nome' => '[Seed Avaliacoes] Consolidacao Trimestral',
                'data_inicio' => $referencia->copy()->subDays(90),
                'data_fim' => $referencia->copy()->subDays(60),
                'status' => Avaliacao::STATUS_ENCERRADA,
                'tipo_avaliacao_id' => (int) $tipoParecer->id,
                'periodo_avaliacao_id' => (int) $periodoPrimeiroSemestre->id,
                'quantidade_turmas' => 3,
                'quantidade_pautas' => 6,
                'cobertura_respostas' => 0.99,
            ],
            [
                'nome' => '[Seed Avaliacoes] Recuperacao Parcial',
                'data_inicio' => $referencia->copy()->addDays(10),
                'data_fim' => $referencia->copy()->addDays(25),
                'status' => Avaliacao::STATUS_INATIVA,
                'tipo_avaliacao_id' => (int) $tipoRubrica->id,
                'periodo_avaliacao_id' => (int) $periodoPrimeiroSemestre->id,
                'quantidade_turmas' => 2,
                'quantidade_pautas' => 4,
                'cobertura_respostas' => 0.39,
            ],
            [
                'nome' => '[Seed Avaliacoes] Simulado de Competencias',
                'data_inicio' => $referencia->copy()->addDays(35),
                'data_fim' => $referencia->copy()->addDays(50),
                'status' => Avaliacao::STATUS_INATIVA,
                'tipo_avaliacao_id' => (int) $tipoParecer->id,
                'periodo_avaliacao_id' => (int) $periodoSegundoSemestre->id,
                'quantidade_turmas' => 3,
                'quantidade_pautas' => 5,
                'cobertura_respostas' => 0.33,
            ],
            [
                'nome' => '[Seed Avaliacoes] Avaliacao Extraordinaria',
                'data_inicio' => $referencia->copy()->subDays(15),
                'data_fim' => $referencia->copy()->addDays(5),
                'status' => Avaliacao::STATUS_CANCELADA,
                'tipo_avaliacao_id' => (int) $tipoRubrica->id,
                'periodo_avaliacao_id' => (int) $periodoPrimeiroSemestre->id,
                'quantidade_turmas' => 2,
                'quantidade_pautas' => 4,
                'cobertura_respostas' => 0.22,
            ],
            [
                'nome' => '[Seed Avaliacoes] Projeto Integrador',
                'data_inicio' => $referencia->copy()->addDays(55),
                'data_fim' => $referencia->copy()->addDays(75),
                'status' => Avaliacao::STATUS_CANCELADA,
                'tipo_avaliacao_id' => (int) $tipoParecer->id,
                'periodo_avaliacao_id' => (int) $periodoSegundoSemestre->id,
                'quantidade_turmas' => 2,
                'quantidade_pautas' => 5,
                'cobertura_respostas' => 0.11,
            ],
            [
                'nome' => '[Seed Avaliacoes] Diagnostica Bimestre 2',
                'data_inicio' => $referencia->copy()->addDays(3),
                'data_fim' => $referencia->copy()->addDays(30),
                'status' => Avaliacao::STATUS_ATIVA,
                'tipo_avaliacao_id' => (int) $tipoParecer->id,
                'periodo_avaliacao_id' => (int) $periodoSegundoSemestre->id,
                'quantidade_turmas' => 3,
                'quantidade_pautas' => 6,
                'cobertura_respostas' => 0.69,
            ],
            [
                'nome' => '[Seed Avaliacoes] Fechamento Semestral',
                'data_inicio' => $referencia->copy()->subDays(180),
                'data_fim' => $referencia->copy()->subDays(150),
                'status' => Avaliacao::STATUS_ENCERRADA,
                'tipo_avaliacao_id' => (int) $tipoRubrica->id,
                'periodo_avaliacao_id' => (int) $periodoPrimeiroSemestre->id,
                'quantidade_turmas' => 3,
                'quantidade_pautas' => 5,
                'cobertura_respostas' => 0.94,
            ],
            [
                'nome' => '[Seed Avaliacoes] Monitoramento Quinzenal',
                'data_inicio' => $referencia->copy()->subDays(2),
                'data_fim' => $referencia->copy()->addDays(13),
                'status' => Avaliacao::STATUS_ATIVA,
                'tipo_avaliacao_id' => (int) $tipoRubrica->id,
                'periodo_avaliacao_id' => (int) $periodoPrimeiroSemestre->id,
                'quantidade_turmas' => 2,
                'quantidade_pautas' => 4,
                'cobertura_respostas' => 0.81,
            ],
            [
                'nome' => '[Seed Avaliacoes] Fechamento Anual Parcial',
                'data_inicio' => $referencia->copy()->subDays(220),
                'data_fim' => $referencia->copy()->subDays(190),
                'status' => Avaliacao::STATUS_ENCERRADA,
                'tipo_avaliacao_id' => (int) $tipoParecer->id,
                'periodo_avaliacao_id' => (int) $periodoSegundoSemestre->id,
                'quantidade_turmas' => 3,
                'quantidade_pautas' => 6,
                'cobertura_respostas' => 1.00,
            ],
        ];

        $turmas = Turma::query()
            ->with([
                'componentes:id',
                'alunos:id,id_turma',
            ])
            ->whereHas('alunos')
            ->orderBy('id')
            ->get(['id', 'id_serie', 'id_escola']);

        $turmasIds = $turmas
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($turmasIds === []) {
            if ($this->command) {
                $this->command->warn('AvaliacoesVariadasSeeder: nenhuma turma com alunos encontrada.');
            }

            return;
        }

        $turmasPorId = $turmas->keyBy('id');
        $nomesComponentes = ComponenteCurricular::query()->pluck('nome', 'id');

        $criadas = 0;
        $atualizadas = 0;
        $respostasGeradas = 0;
        $informacoesComplementaresGeradas = 0;
        $overridesGerados = 0;

        foreach ($avaliacoes as $indice => $dados) {
            $quantidadeTurmas = min(
                max(1, (int) ($dados['quantidade_turmas'] ?? 2)),
                count($turmasIds)
            );

            $turmasParaVincular = $this->recorteCircularIds($turmasIds, $indice, $quantidadeTurmas);
            $turmasSelecionadas = collect($turmasParaVincular)
                ->map(fn (int $turmaId) => $turmasPorId->get($turmaId))
                ->filter();

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

            $componentesIds = $turmasSelecionadas
                ->flatMap(fn (Turma $turma): Collection => $turma->componentes->pluck('id'))
                ->filter()
                ->map(fn ($componenteId): int => (int) $componenteId)
                ->unique()
                ->values()
                ->all();

            $quantidadePautas = max(1, (int) ($dados['quantidade_pautas'] ?? 4));
            $pautasParaVincular = $this->selecionarPautasParaContexto(
                tipoAvaliacaoId: (int) $dados['tipo_avaliacao_id'],
                seriesIds: $seriesIds,
                componentesIds: $componentesIds,
                indiceBase: $indice,
                quantidade: $quantidadePautas,
                nomesComponentes: $nomesComponentes
            );

            $avaliacao = Avaliacao::query()->updateOrCreate(
                ['nome' => $dados['nome']],
                [
                    'tipo_avaliacao_id' => (int) $dados['tipo_avaliacao_id'],
                    'periodo_avaliacao_id' => (int) $dados['periodo_avaliacao_id'],
                    'data_inicio' => $dados['data_inicio']->toDateString(),
                    'data_fim' => $dados['data_fim']->toDateString(),
                    'status' => $dados['status'],
                ]
            );

            if ($pautasParaVincular !== []) {
                $avaliacao->pautas()->sync($pautasParaVincular);
            }

            if ($turmasParaVincular !== []) {
                $avaliacao->turmas()->sync($turmasParaVincular);
            }

            $avaliacao->series()->sync($seriesIds);
            $avaliacao->componentes()->sync($componentesIds);
            $avaliacao->escolas()->sync($escolasIds);

            $alternativasTipoIds = $alternativasPorTipo[(int) $dados['tipo_avaliacao_id']] ?? [];
            $this->sincronizarAlternativasLegadasDasPautas($pautasParaVincular, $alternativasTipoIds);

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

    private function garantirAlternativasDoTipo(int $tipoAvaliacaoId, array $alternativas): array
    {
        $ids = [];

        foreach ($alternativas as $alternativaDados) {
            $alternativa = Alternativa::query()->updateOrCreate(
                [
                    'tipo_avaliacao_id' => $tipoAvaliacaoId,
                    'nome' => (string) $alternativaDados['nome'],
                ],
                [
                    'status' => true,
                    'tem_observacao' => (bool) ($alternativaDados['tem_observacao'] ?? false),
                    'observacao' => $alternativaDados['observacao'] ?? null,
                ]
            );

            $ids[] = (int) $alternativa->id;
        }

        return array_values(array_unique($ids));
    }

    private function selecionarPautasParaContexto(
        int $tipoAvaliacaoId,
        array $seriesIds,
        array $componentesIds,
        int $indiceBase,
        int $quantidade,
        Collection $nomesComponentes
    ): array {
        $pautasIds = Pauta::query()
            ->where('status', true)
            ->where('tipo_avaliacao_id', $tipoAvaliacaoId)
            ->where(function ($query) use ($seriesIds): void {
                if ($seriesIds !== []) {
                    $query->whereIn('serie_id', $seriesIds)
                        ->orWhereNull('serie_id');

                    return;
                }

                $query->whereNull('serie_id');
            })
            ->where(function ($query) use ($componentesIds): void {
                if ($componentesIds !== []) {
                    $query->whereIn('componente_curricular_id', $componentesIds)
                        ->orWhereNull('componente_curricular_id');

                    return;
                }

                $query->whereNull('componente_curricular_id');
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($pautasIds === [] && $componentesIds !== []) {
            $componentesParaCriar = $this->recorteCircularIds(
                $componentesIds,
                $indiceBase,
                min(3, count($componentesIds))
            );

            foreach ($componentesParaCriar as $offset => $componenteId) {
                $serieId = $seriesIds[$offset % max(count($seriesIds), 1)] ?? null;
                $nomeComponente = (string) ($nomesComponentes[(int) $componenteId] ?? "Componente {$componenteId}");
                $texto = sprintf(
                    '[Seed Avaliacoes] %s | Indicador %d',
                    $nomeComponente,
                    $offset + 1
                );

                $pauta = Pauta::query()->updateOrCreate(
                    [
                        'texto' => $texto,
                        'tipo_avaliacao_id' => $tipoAvaliacaoId,
                        'componente_curricular_id' => (int) $componenteId,
                        'serie_id' => $serieId,
                    ],
                    ['status' => true]
                );

                $pautasIds[] = (int) $pauta->id;
            }

            if ($seriesIds !== []) {
                $pautaGeral = Pauta::query()->updateOrCreate(
                    [
                        'texto' => '[Seed Avaliacoes] Participacao e protagonismo do aluno',
                        'tipo_avaliacao_id' => $tipoAvaliacaoId,
                        'componente_curricular_id' => null,
                        'serie_id' => (int) $seriesIds[0],
                    ],
                    ['status' => true]
                );

                $pautasIds[] = (int) $pautaGeral->id;
            }
        }

        if ($pautasIds === []) {
            return [];
        }

        $quantidade = min(max(1, $quantidade), count($pautasIds));

        return $this->recorteCircularIds($pautasIds, $indiceBase, $quantidade);
    }

    private function sincronizarAlternativasLegadasDasPautas(array $pautaIds, array $alternativasIds): void
    {
        if ($pautaIds === [] || $alternativasIds === []) {
            return;
        }

        Pauta::query()
            ->whereIn('id', $pautaIds)
            ->get(['id'])
            ->each(function (Pauta $pauta) use ($alternativasIds): void {
                $pauta->alternativas()->syncWithoutDetaching($alternativasIds);
            });
    }

    private function sincronizarOverridesDeAlternativas(
        int $avaliacaoId,
        array $pautaIds,
        array $alternativasIds,
        int $indiceBase,
        \Illuminate\Support\Carbon $agora
    ): int {
        DB::table('avaliacao_pauta_alternativa')
            ->where('avaliacao_id', $avaliacaoId)
            ->delete();

        if ($pautaIds === [] || count($alternativasIds) < 2 || ($indiceBase % 3) !== 0) {
            return 0;
        }

        $quantidadePautasComOverride = min(2, count($pautaIds));
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
        \Illuminate\Support\Carbon $agora
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
                            ->subDays($this->hashIndice(45, "dia|{$avaliacao->id}|{$turmaId}|{$pautaId}|{$alunoId}"))
                            ->subMinutes($this->hashIndice(480, "min|{$avaliacao->id}|{$turmaId}|{$pautaId}|{$alunoId}")),
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

                if ($marcadorInfo > 28) {
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

    private function recorteCircularIds(array $ids, int $indiceBase, int $tamanho): array
    {
        if ($ids === [] || $tamanho <= 0) {
            return [];
        }

        $total = count($ids);

        if ($tamanho >= $total) {
            return $ids;
        }

        $inicio = $indiceBase % $total;
        $selecionados = [];

        for ($i = 0; $i < $tamanho; $i++) {
            $selecionados[] = $ids[($inicio + $i) % $total];
        }

        return array_values(array_unique($selecionados));
    }
}
