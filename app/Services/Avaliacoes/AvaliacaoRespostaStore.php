<?php

namespace App\Services\Avaliacoes;

use App\Exceptions\AvaliacaoRespostaConcorrenteException;
use App\Jobs\AtualizarAvaliacaoDashboardTurmaResumoJob;
use App\Jobs\ProjetarAvaliacaoDocumentoCompatibilidadeJob;
use App\Models\Aluno;
use App\Models\AvaliacaoInformacaoOperacional;
use App\Models\AvaliacaoRespostaOperacional;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\AvaliacaoAlunoSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\Avaliacoes\AvaliacaoPerformanceContext;

class AvaliacaoRespostaStore
{
    private static ?bool $dashboardResumoDisponivel = null;

    public function __construct(
        private readonly AvaliacaoPersistencia $persistencia,
        private readonly AvaliacaoTurmaCicloService $ciclos,
        private readonly AvaliacaoAlunoDocumentoService $documentos,
    ) {
    }

    /**
     * @param array{alternativa_id?: int|null, observacao?: string|null, professor_id?: int|null, componente_curricular_id?: int|null, respondido_em?: mixed} $dados
     */
    public function salvarPauta(
        int $avaliacaoId,
        int $turmaAvaliativaId,
        Aluno $aluno,
        int $pautaId,
        array $dados,
        ?int $expectedVersion = null,
        array $expectedValues = [],
    ): int {
        $version = DB::transaction(function () use ($avaliacaoId, $turmaAvaliativaId, $aluno, $pautaId, $dados, $expectedVersion, $expectedValues): int {
            $version = 0;

            if ($this->persistencia->gravaRelacional()) {
                $version = $this->salvarPautaRelacional(
                    $avaliacaoId,
                    $turmaAvaliativaId,
                    $aluno,
                    $pautaId,
                    $dados,
                    $expectedVersion,
                    $expectedValues,
                );
            }

            if ($this->persistencia->gravaDocumentoLegado()) {
                $documento = $this->documentos->obterOuCriar($avaliacaoId, $aluno, false);
                $this->documentos->salvarPauta($documento, $pautaId, $dados);
            }

            $this->projetarCompatibilidade($avaliacaoId, (int) $aluno->id);

            return $version;
        }, 3);

        $this->agendarResumoDashboard($avaliacaoId, $turmaAvaliativaId);

        return $version;
    }

    public function removerPauta(
        int $avaliacaoId,
        int $turmaAvaliativaId,
        Aluno $aluno,
        int $pautaId,
        ?int $expectedVersion = null,
    ): void {
        DB::transaction(function () use ($avaliacaoId, $turmaAvaliativaId, $aluno, $pautaId, $expectedVersion): void {
            if ($this->persistencia->gravaRelacional()) {
                $ciclo = $this->cicloBloqueadoParaEscrita($avaliacaoId, $turmaAvaliativaId, (int) $aluno->id_turma);
                $resposta = AvaliacaoRespostaOperacional::query()
                    ->where('ciclo_id', (int) $ciclo->id)
                    ->where('aluno_id', (int) $aluno->id)
                    ->where('pauta_id', $pautaId)
                    ->first();

                if ($resposta && $expectedVersion !== null && (int) $resposta->version !== $expectedVersion) {
                    throw new AvaliacaoRespostaConcorrenteException('A resposta foi alterada por outro usuário.');
                }

                $resposta?->delete();
            }

            if ($this->persistencia->gravaDocumentoLegado()) {
                $documento = $this->documentos->obter($avaliacaoId, (int) $aluno->id);
                if ($documento) {
                    $this->documentos->removerPauta($documento, $pautaId);
                }
            }

            $this->projetarCompatibilidade($avaliacaoId, (int) $aluno->id);
        }, 3);

        $this->agendarResumoDashboard($avaliacaoId, $turmaAvaliativaId);
    }

    public function salvarInformacao(
        int $avaliacaoId,
        int $turmaAvaliativaId,
        Aluno $aluno,
        int $componenteId,
        ?string $texto,
        ?int $professorId,
        ?int $expectedVersion = null,
    ): int {
        $version = DB::transaction(function () use ($avaliacaoId, $turmaAvaliativaId, $aluno, $componenteId, $texto, $professorId, $expectedVersion): int {
            $version = 0;

            if ($this->persistencia->gravaRelacional()) {
                $ciclo = $this->cicloBloqueadoParaEscrita($avaliacaoId, $turmaAvaliativaId, (int) $aluno->id_turma);
                $existente = AvaliacaoInformacaoOperacional::query()
                    ->where('ciclo_id', (int) $ciclo->id)
                    ->where('aluno_id', (int) $aluno->id)
                    ->where('componente_chave', $componenteId)
                    ->first();

                if ($existente && $expectedVersion !== null && (int) $existente->version !== $expectedVersion) {
                    throw new AvaliacaoRespostaConcorrenteException('A informação complementar foi alterada por outro usuário.');
                }

                $texto = $this->normalizarTexto($texto);
                if ($texto === null) {
                    $existente?->delete();
                } elseif ($existente) {
                    $existente->forceFill([
                        'texto' => $texto,
                        'professor_id' => $professorId,
                        'version' => (int) $existente->version + 1,
                    ])->save();
                    $version = (int) $existente->version;
                } else {
                    $existente = AvaliacaoInformacaoOperacional::query()->create([
                        ...$this->contexto($ciclo, $avaliacaoId, $turmaAvaliativaId, $aluno),
                        'aluno_id' => (int) $aluno->id,
                        'componente_curricular_id' => $componenteId > 0 ? $componenteId : null,
                        'componente_chave' => max(0, $componenteId),
                        'professor_id' => $professorId,
                        'texto' => $texto,
                        'version' => 1,
                    ]);
                    $version = (int) $existente->version;
                }
            }

            if ($this->persistencia->gravaDocumentoLegado()) {
                $documento = $this->documentos->obterOuCriar($avaliacaoId, $aluno, false);
                $this->documentos->salvarInfoComplementar($documento, $componenteId, $texto, $professorId);
            }

            $this->projetarCompatibilidade($avaliacaoId, (int) $aluno->id);

            return $version;
        }, 3);

        $this->agendarResumoDashboard($avaliacaoId, $turmaAvaliativaId);

        return $version;
    }

    /**
     * Persiste alterações de várias respostas e informações em uma única
     * transação, reduzindo as leituras repetidas do estado operacional.
     *
     * @param array<int, array{chave: string, avaliacao_id: int, turma_avaliativa_id: int, aluno: Aluno, pauta_id: int, dados: array<string, mixed>, expected_version: int|null, expected_values: array<string, mixed>}> $respostas
     * @param array<int, array{chave: string, avaliacao_id: int, turma_avaliativa_id: int, aluno: Aluno, componente_id: int, texto: string|null, professor_id: int|null, expected_version: int|null}> $informacoes
     * @return array{respostas: array<string, int>, informacoes: array<string, int>}
     */
    public function salvarAlteracoesEmMassa(array $respostas, array $informacoes): array
    {
        $resultado = DB::transaction(function () use ($respostas, $informacoes): array {
            $resultado = ['respostas' => [], 'informacoes' => []];
            $alunosParaProjetar = [];

            if ($this->persistencia->gravaRelacional()) {
                $grupos = collect($respostas)
                    ->groupBy('turma_avaliativa_id')
                    ->merge(collect($informacoes)->groupBy('turma_avaliativa_id'))
                    ->keys()
                    ->map(fn ($id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->unique()
                    ->sort()
                    ->values();

                foreach ($grupos as $turmaAvaliativaId) {
                    $registrosRespostas = collect($respostas)
                        ->filter(fn (array $registro): bool => (int) $registro['turma_avaliativa_id'] === $turmaAvaliativaId)
                        ->values();
                    $registrosInformacoes = collect($informacoes)
                        ->filter(fn (array $registro): bool => (int) $registro['turma_avaliativa_id'] === $turmaAvaliativaId)
                        ->values();
                    $primeiroRegistro = $registrosRespostas->first() ?? $registrosInformacoes->first();

                    if (! $primeiroRegistro) {
                        continue;
                    }

                    $ciclo = $this->cicloBloqueadoParaEscrita(
                        (int) $primeiroRegistro['avaliacao_id'],
                        $turmaAvaliativaId,
                        (int) $primeiroRegistro['aluno']->id_turma,
                    );
                    $alunoIds = $registrosRespostas
                        ->pluck('aluno.id')
                        ->merge($registrosInformacoes->pluck('aluno.id'))
                        ->map(fn ($id): int => (int) $id)
                        ->unique()
                        ->values()
                        ->all();

                    $respostasExistentes = $registrosRespostas->isEmpty()
                        ? collect()
                        : AvaliacaoRespostaOperacional::query()
                            ->where('ciclo_id', (int) $ciclo->id)
                            ->whereIn('aluno_id', $alunoIds)
                            ->whereIn('pauta_id', $registrosRespostas->pluck('pauta_id')->map(fn ($id): int => (int) $id)->unique()->all())
                            ->lockForUpdate()
                            ->get()
                            ->keyBy(fn (AvaliacaoRespostaOperacional $linha): string => $linha->aluno_id.':'.$linha->pauta_id);
                    $informacoesExistentes = $registrosInformacoes->isEmpty()
                        ? collect()
                        : AvaliacaoInformacaoOperacional::query()
                            ->where('ciclo_id', (int) $ciclo->id)
                            ->whereIn('aluno_id', $alunoIds)
                            ->whereIn('componente_chave', $registrosInformacoes->pluck('componente_id')->map(fn ($id): int => max(0, (int) $id))->unique()->all())
                            ->lockForUpdate()
                            ->get()
                            ->keyBy(fn (AvaliacaoInformacaoOperacional $linha): string => $linha->aluno_id.':'.$linha->componente_chave);

                    foreach ($registrosRespostas as $registro) {
                        $aluno = $registro['aluno'];
                        $pautaId = (int) $registro['pauta_id'];
                        $chaveExistente = (int) $aluno->id.':'.$pautaId;
                        $existente = $respostasExistentes->get($chaveExistente);
                        $expectedVersion = $registro['expected_version'];
                        $dados = $registro['dados'];

                        if ($existente && $expectedVersion !== null && (int) $existente->version !== (int) $expectedVersion) {
                            foreach (['alternativa_id', 'observacao'] as $campo) {
                                if (! array_key_exists($campo, $dados)) {
                                    continue;
                                }

                                $atual = $campo === 'alternativa_id'
                                    ? ((int) ($existente->{$campo} ?? 0) ?: null)
                                    : $this->normalizarTexto($existente->{$campo});
                                $esperado = $campo === 'alternativa_id'
                                    ? ((int) ($registro['expected_values'][$campo] ?? 0) ?: null)
                                    : $this->normalizarTexto($registro['expected_values'][$campo] ?? null);

                                if (! array_key_exists($campo, $registro['expected_values']) || $atual !== $esperado) {
                                    throw new AvaliacaoRespostaConcorrenteException('A resposta foi alterada por outro usuário.');
                                }
                            }
                        }

                        $alternativaId = array_key_exists('alternativa_id', $dados)
                            ? ((int) ($dados['alternativa_id'] ?? 0) ?: null)
                            : $existente?->alternativa_id;

                        if ($alternativaId === null) {
                            $existente?->delete();
                            $resultado['respostas'][$registro['chave']] = 0;
                        } else {
                            $atributos = [
                                'alternativa_id' => $alternativaId,
                                'observacao' => array_key_exists('observacao', $dados)
                                    ? $this->normalizarTexto($dados['observacao'])
                                    : $existente?->observacao,
                                'professor_id' => $dados['professor_id'] ?? $existente?->professor_id,
                                'componente_curricular_id' => $dados['componente_curricular_id'] ?? $existente?->componente_curricular_id,
                                'respondido_em' => $dados['respondido_em'] ?? now(),
                            ];

                            if ($existente) {
                                $existente->forceFill([...$atributos, 'version' => (int) $existente->version + 1])->save();
                                $resultado['respostas'][$registro['chave']] = (int) $existente->version;
                            } else {
                                $nova = AvaliacaoRespostaOperacional::query()->create([
                                    ...$this->contexto($ciclo, (int) $registro['avaliacao_id'], $turmaAvaliativaId, $aluno),
                                    'aluno_id' => (int) $aluno->id,
                                    'pauta_id' => $pautaId,
                                    ...$atributos,
                                    'version' => 1,
                                ]);
                                $resultado['respostas'][$registro['chave']] = (int) $nova->version;
                            }
                        }

                        $alunosParaProjetar[(int) $aluno->id] = true;
                    }

                    foreach ($registrosInformacoes as $registro) {
                        $aluno = $registro['aluno'];
                        $componenteId = (int) $registro['componente_id'];
                        $chaveExistente = (int) $aluno->id.':'.max(0, $componenteId);
                        $existente = $informacoesExistentes->get($chaveExistente);
                        $expectedVersion = $registro['expected_version'];

                        if ($existente && $expectedVersion !== null && (int) $existente->version !== (int) $expectedVersion) {
                            throw new AvaliacaoRespostaConcorrenteException('A informação complementar foi alterada por outro usuário.');
                        }

                        $texto = $this->normalizarTexto($registro['texto']);
                        if ($texto === null) {
                            $existente?->delete();
                            $resultado['informacoes'][$registro['chave']] = 0;
                        } elseif ($existente) {
                            $existente->forceFill([
                                'texto' => $texto,
                                'professor_id' => $registro['professor_id'],
                                'version' => (int) $existente->version + 1,
                            ])->save();
                            $resultado['informacoes'][$registro['chave']] = (int) $existente->version;
                        } else {
                            $nova = AvaliacaoInformacaoOperacional::query()->create([
                                ...$this->contexto($ciclo, (int) $registro['avaliacao_id'], $turmaAvaliativaId, $aluno),
                                'aluno_id' => (int) $aluno->id,
                                'componente_curricular_id' => $componenteId > 0 ? $componenteId : null,
                                'componente_chave' => max(0, $componenteId),
                                'professor_id' => $registro['professor_id'],
                                'texto' => $texto,
                                'version' => 1,
                            ]);
                            $resultado['informacoes'][$registro['chave']] = (int) $nova->version;
                        }

                        $alunosParaProjetar[(int) $aluno->id] = true;
                    }
                }
            }

            if ($this->persistencia->gravaDocumentoLegado()) {
                foreach ($respostas as $registro) {
                    $documento = $this->documentos->obterOuCriar((int) $registro['avaliacao_id'], $registro['aluno'], false);
                    $this->documentos->salvarPauta(
                        $documento,
                        (int) $registro['pauta_id'],
                        $registro['dados'],
                        $registro['expected_version'],
                    );
                    $resultado['respostas'][$registro['chave']] = 0;
                    $alunosParaProjetar[(int) $registro['aluno']->id] = true;
                }

                foreach ($informacoes as $registro) {
                    $documento = $this->documentos->obterOuCriar((int) $registro['avaliacao_id'], $registro['aluno'], false);
                    $this->documentos->salvarInfoComplementar(
                        $documento,
                        (int) $registro['componente_id'],
                        $registro['texto'],
                        $registro['professor_id'],
                        $registro['expected_version'],
                    );
                    $resultado['informacoes'][$registro['chave']] = 0;
                    $alunosParaProjetar[(int) $registro['aluno']->id] = true;
                }
            }

            $avaliacaoId = (int) ($respostas[0]['avaliacao_id'] ?? $informacoes[0]['avaliacao_id'] ?? 0);
            foreach (array_keys($alunosParaProjetar) as $alunoId) {
                $this->projetarCompatibilidade($avaliacaoId, (int) $alunoId);
            }

            return $resultado;
        }, 3);

        collect($respostas)
            ->merge($informacoes)
            ->pluck('turma_avaliativa_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->each(fn (int $turmaId) => $this->agendarResumoDashboard(
                (int) ($respostas[0]['avaliacao_id'] ?? $informacoes[0]['avaliacao_id'] ?? 0),
                $turmaId,
            ));

        return $resultado;
    }

    /**
     * @param Collection<int, Aluno> $alunos
     * @param array<int, array<int, array<string, mixed>>> $respostasPorAluno
     * @param array<int, int> $turmaAvaliativaPorAluno
     */
    public function salvarPautasEmMassaParaAlunos(
        int $avaliacaoId,
        Collection $alunos,
        array $respostasPorAluno,
        array $turmaAvaliativaPorAluno,
    ): int {
        $alterados = DB::transaction(function () use ($avaliacaoId, $alunos, $respostasPorAluno, $turmaAvaliativaPorAluno): int {
            $alterados = 0;
            $alunosPorId = $alunos->keyBy(fn (Aluno $aluno): int => (int) $aluno->id);

            if ($this->persistencia->gravaRelacional()) {
                $grupos = collect(array_keys($respostasPorAluno))->groupBy(
                    fn ($alunoId): int => (int) ($turmaAvaliativaPorAluno[(int) $alunoId] ?? 0)
                );

                foreach ($grupos->sortKeys() as $turmaAvaliativaId => $alunosIds) {
                    if ((int) $turmaAvaliativaId <= 0) {
                        continue;
                    }

                    $primeiroAluno = $alunosPorId->get((int) $alunosIds->first());
                    if (! $primeiroAluno) {
                        continue;
                    }

                    $ciclo = $this->cicloBloqueadoParaEscrita(
                        $avaliacaoId,
                        (int) $turmaAvaliativaId,
                        (int) $primeiroAluno->id_turma,
                    );
                    $agora = now();
                    $linhas = [];

                    foreach ($alunosIds as $alunoId) {
                        $aluno = $alunosPorId->get((int) $alunoId);
                        if (! $aluno) {
                            continue;
                        }

                        foreach ($respostasPorAluno[(int) $alunoId] ?? [] as $pautaId => $dados) {
                            $alternativaId = isset($dados['alternativa_id']) ? (int) $dados['alternativa_id'] : 0;
                            if ($alternativaId <= 0) {
                                continue;
                            }

                            $linhas[] = [
                                ...$this->contexto($ciclo, $avaliacaoId, (int) $turmaAvaliativaId, $aluno),
                                'aluno_id' => (int) $aluno->id,
                                'pauta_id' => (int) $pautaId,
                                'componente_curricular_id' => $dados['componente_curricular_id'] ?? null,
                                'professor_id' => $dados['professor_id'] ?? null,
                                'alternativa_id' => $alternativaId,
                                'observacao' => $this->normalizarTexto($dados['observacao'] ?? null),
                                'respondido_em' => $dados['respondido_em'] ?? $agora,
                                'version' => 1,
                                'created_at' => $agora,
                                'updated_at' => $agora,
                            ];
                        }
                    }

                    if ($linhas !== []) {
                        // Aplicação em massa apenas preenche lacunas. Uma resposta criada
                        // por autosave concorrente nunca pode ser sobrescrita pelo lote.
                        $alterados += DB::table('avaliacao_respostas_operacionais')->insertOrIgnore($linhas);
                    }
                }
            }

            if ($this->persistencia->gravaDocumentoLegado()) {
                $alterados = max(
                    $alterados,
                    $this->documentos->salvarPautasEmMassaParaAlunos($avaliacaoId, $alunos, $respostasPorAluno),
                );
            }

            foreach (array_keys($respostasPorAluno) as $alunoId) {
                $this->projetarCompatibilidade($avaliacaoId, (int) $alunoId);
            }

            return $alterados;
        }, 3);

        collect($turmaAvaliativaPorAluno)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->each(fn (int $turmaId) => $this->agendarResumoDashboard($avaliacaoId, $turmaId));

        return $alterados;
    }

    /** @return Collection<int, Collection<int, array<string, mixed>>> */
    public function respostasDaAvaliacaoParaAlunos(int $avaliacaoId, array $alunoIds, ?array $turmaAvaliativaIds = null): Collection
    {
        $turmaAvaliativaIds = $turmaAvaliativaIds === null
            ? null
            : collect($turmaAvaliativaIds)
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();

        $resultado = AvaliacaoRespostaOperacional::query()
            ->where('avaliacao_id', $avaliacaoId)
            ->whereIn('aluno_id', $alunoIds)
            ->when(
                $turmaAvaliativaIds !== null,
                fn ($query) => $query->whereIn('turma_avaliativa_id', $turmaAvaliativaIds),
            )
            ->get()
            ->groupBy('aluno_id')
            ->map(fn (Collection $linhas): Collection => $linhas->keyBy('pauta_id')->map(fn (AvaliacaoRespostaOperacional $linha): array => [
                'alternativa_id' => $linha->alternativa_id ? (int) $linha->alternativa_id : null,
                'observacao' => $linha->observacao,
                'professor_id' => $linha->professor_id ? (int) $linha->professor_id : null,
                'componente_curricular_id' => $linha->componente_curricular_id ? (int) $linha->componente_curricular_id : null,
                'respondido_em' => $linha->respondido_em?->toIso8601String(),
                'version' => (int) $linha->version,
            ]));

        foreach ($this->snapshotsFinaisAtuais($avaliacaoId, $alunoIds, $turmaAvaliativaIds) as $snapshot) {
            $alunoId = (int) $snapshot->aluno_id;
            if ($resultado->has($alunoId)) {
                continue;
            }
            $resultado->put($alunoId, collect((array) ($snapshot->payload['pautas'] ?? []))->mapWithKeys(
                fn (array $item, string|int $pautaId): array => [(int) $pautaId => [...$item, 'version' => 0]],
            ));
        }

        return $resultado;
    }

    /** @return Collection<int, Collection<int, array<string, mixed>>> */
    public function informacoesDaAvaliacaoParaAlunos(int $avaliacaoId, array $alunoIds, ?array $turmaAvaliativaIds = null): Collection
    {
        $turmaAvaliativaIds = $turmaAvaliativaIds === null
            ? null
            : collect($turmaAvaliativaIds)
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();

        $resultado = AvaliacaoInformacaoOperacional::query()
            ->where('avaliacao_id', $avaliacaoId)
            ->whereIn('aluno_id', $alunoIds)
            ->when(
                $turmaAvaliativaIds !== null,
                fn ($query) => $query->whereIn('turma_avaliativa_id', $turmaAvaliativaIds),
            )
            ->get()
            ->groupBy('aluno_id')
            ->map(fn (Collection $linhas): Collection => $linhas->keyBy('componente_chave')->map(fn (AvaliacaoInformacaoOperacional $linha): array => [
                'texto' => $linha->texto,
                'professor_id' => $linha->professor_id ? (int) $linha->professor_id : null,
                'version' => (int) $linha->version,
            ]));

        foreach ($this->snapshotsFinaisAtuais($avaliacaoId, $alunoIds, $turmaAvaliativaIds) as $snapshot) {
            $alunoId = (int) $snapshot->aluno_id;
            if ($resultado->has($alunoId)) {
                continue;
            }
            $resultado->put($alunoId, collect((array) ($snapshot->payload['informacoes_complementares'] ?? []))->mapWithKeys(
                fn (array $item, string|int $componenteId): array => [(int) $componenteId => [...$item, 'version' => 0]],
            ));
        }

        return $resultado;
    }

    private function snapshotsFinaisAtuais(int $avaliacaoId, array $alunoIds, ?array $turmaAvaliativaIds = null): Collection
    {
        return AvaliacaoAlunoSnapshot::query()
            ->select('avaliacao_aluno_snapshots.*')
            ->join('avaliacao_turma_ciclos as ciclo_snapshot', function ($join): void {
                $join->on('ciclo_snapshot.id', '=', 'avaliacao_aluno_snapshots.ciclo_id')
                    ->on('ciclo_snapshot.snapshot_evento_atual_id', '=', 'avaliacao_aluno_snapshots.evento_id');
            })
            ->where('avaliacao_aluno_snapshots.avaliacao_id', $avaliacaoId)
            ->whereIn('avaliacao_aluno_snapshots.aluno_id', $alunoIds)
            ->when(
                $turmaAvaliativaIds !== null,
                fn ($query) => $query->whereIn('avaliacao_aluno_snapshots.turma_avaliativa_id', $turmaAvaliativaIds),
            )
            ->where('ciclo_snapshot.status', AvaliacaoTurmaCiclo::STATUS_CONCLUIDA)
            ->get();
    }

    private function salvarPautaRelacional(
        int $avaliacaoId,
        int $turmaAvaliativaId,
        Aluno $aluno,
        int $pautaId,
        array $dados,
        ?int $expectedVersion,
        array $expectedValues,
    ): int {
        $ciclo = $this->cicloBloqueadoParaEscrita($avaliacaoId, $turmaAvaliativaId, (int) $aluno->id_turma);
        app(AvaliacaoPerformanceContext::class)->add([
            'acao' => 'autosave_resposta',
            'ciclo_id' => (int) $ciclo->id,
            'avaliacao_id' => $avaliacaoId,
            'turma_id' => $turmaAvaliativaId,
            'aluno_id' => (int) $aluno->id,
            'pauta_id' => $pautaId,
        ]);
        $existente = AvaliacaoRespostaOperacional::query()
            ->where('ciclo_id', (int) $ciclo->id)
            ->where('aluno_id', (int) $aluno->id)
            ->where('pauta_id', $pautaId)
            ->first();

        if ($existente && $expectedVersion !== null && (int) $existente->version !== $expectedVersion) {
            foreach (['alternativa_id', 'observacao'] as $campo) {
                if (! array_key_exists($campo, $dados)) {
                    continue;
                }

                $atual = $campo === 'alternativa_id'
                    ? ((int) ($existente->{$campo} ?? 0) ?: null)
                    : $this->normalizarTexto($existente->{$campo});
                $esperado = $campo === 'alternativa_id'
                    ? ((int) ($expectedValues[$campo] ?? 0) ?: null)
                    : $this->normalizarTexto($expectedValues[$campo] ?? null);

                if (! array_key_exists($campo, $expectedValues) || $atual !== $esperado) {
                    throw new AvaliacaoRespostaConcorrenteException('A resposta foi alterada por outro usuário.');
                }
            }
        }

        $alternativaId = array_key_exists('alternativa_id', $dados)
            ? ((int) ($dados['alternativa_id'] ?? 0) ?: null)
            : $existente?->alternativa_id;

        if ($alternativaId === null) {
            $existente?->delete();

            return 0;
        }

        $atributos = [
            'alternativa_id' => $alternativaId,
            'observacao' => array_key_exists('observacao', $dados)
                ? $this->normalizarTexto($dados['observacao'])
                : $existente?->observacao,
            'professor_id' => $dados['professor_id'] ?? $existente?->professor_id,
            'componente_curricular_id' => $dados['componente_curricular_id'] ?? $existente?->componente_curricular_id,
            'respondido_em' => $dados['respondido_em'] ?? now(),
        ];

        if ($existente) {
            $existente->forceFill([...$atributos, 'version' => (int) $existente->version + 1])->save();

            return (int) $existente->version;
        }

        $nova = AvaliacaoRespostaOperacional::query()->create([
            ...$this->contexto($ciclo, $avaliacaoId, $turmaAvaliativaId, $aluno),
            'aluno_id' => (int) $aluno->id,
            'pauta_id' => $pautaId,
            ...$atributos,
            'version' => 1,
        ]);

        return (int) $nova->version;
    }

    private function cicloBloqueadoParaEscrita(
        int $avaliacaoId,
        int $turmaAvaliativaId,
        int $turmaOrigemId,
    ): AvaliacaoTurmaCiclo {
        $ciclo = $this->ciclos->obterParaEscritaCompartilhada($avaliacaoId, $turmaAvaliativaId);

        if (! $ciclo) {
            $this->ciclos->obterOuCriar($avaliacaoId, $turmaAvaliativaId, $turmaOrigemId);
            $ciclo = $this->ciclos->obterParaEscritaCompartilhada($avaliacaoId, $turmaAvaliativaId);
        }

        if (! $ciclo) {
            throw new \RuntimeException('Não foi possível abrir o ciclo de escrita da avaliação.');
        }

        return $ciclo;
    }

    /** @return array<string, int> */
    private function contexto(AvaliacaoTurmaCiclo $ciclo, int $avaliacaoId, int $turmaAvaliativaId, Aluno $aluno): array
    {
        return [
            'token_escrita_id' => (int) $ciclo->getAttribute('token_escrita_bloqueado_id'),
            'ciclo_id' => (int) $ciclo->id,
            'avaliacao_id' => $avaliacaoId,
            'turma_avaliativa_id' => $turmaAvaliativaId,
            'turma_origem_id' => (int) $aluno->id_turma,
        ];
    }

    private function normalizarTexto(mixed $texto): ?string
    {
        $texto = trim((string) ($texto ?? ''));

        return $texto !== '' ? $texto : null;
    }

    private function projetarCompatibilidade(int $avaliacaoId, int $alunoId): void
    {
        if (! $this->persistencia->leRelacional()
            || ! (bool) config('avaliacoes_persistencia.projetar_json_legado_assincrono', false)) {
            return;
        }

        ProjetarAvaliacaoDocumentoCompatibilidadeJob::dispatch($avaliacaoId, $alunoId)->afterCommit();
    }

    protected function agendarResumoDashboard(int $avaliacaoId, int $turmaId): void
    {
        if ($avaliacaoId <= 0 || $turmaId <= 0 || ! (self::$dashboardResumoDisponivel ??= Schema::hasTable('avaliacao_dashboard_turma_resumos'))) {
            return;
        }

        AtualizarAvaliacaoDashboardTurmaResumoJob::dispatch($avaliacaoId, $turmaId);
    }
}
