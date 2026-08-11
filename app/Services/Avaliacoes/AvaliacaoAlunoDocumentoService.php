<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoAlunoDocumento;
use App\Models\AvaliacaoAlunoDocumentoHistorico;
use App\Models\Pauta;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AvaliacaoAlunoDocumentoService
{
    public function __construct(
        private readonly AvaliacaoDashboardMetricsService $dashboardMetricsService,
    ) {}

    public function obter(int $avaliacaoId, int $alunoId): ?AvaliacaoAlunoDocumento
    {
        return AvaliacaoAlunoDocumento::query()
            ->where('avaliacao_id', $avaliacaoId)
            ->where('aluno_id', $alunoId)
            ->first();
    }

    public function obterOuCriar(Avaliacao|int $avaliacao, Aluno $aluno, bool $somentePrincipal = true): AvaliacaoAlunoDocumento
    {
        $avaliacaoId = $avaliacao instanceof Avaliacao ? (int) $avaliacao->id : (int) $avaliacao;

        if ($somentePrincipal && ! $aluno->isPrincipal()) {
            throw new RuntimeException('Documentos avaliativos so podem ser vinculados ao vinculo principal do aluno.');
        }

        $aluno->loadMissing('turma');

        $existente = $this->obter($avaliacaoId, (int) $aluno->id);

        if ($existente) {
            return $this->sincronizarContextoSeNecessario($existente, $aluno);
        }

        $contexto = $this->contextoDoAluno($aluno);

        $documento = AvaliacaoAlunoDocumento::query()->create([
            'avaliacao_id' => $avaliacaoId,
            'aluno_id' => (int) $aluno->id,
            'cgm' => (string) $aluno->cgm,
            'turma_id' => $contexto['turma_id'],
            'escola_id' => $contexto['escola_id'],
            'serie_id' => $contexto['serie_id'],
            'payload' => AvaliacaoAlunoDocumento::payloadVazio(),
            'alternativa_ids' => [],
            'professor_ids' => [],
            'pauta_ids_respondidas' => [],
            'total_pautas_esperadas' => $this->contarPautasEsperadas(
                $avaliacaoId,
                $contexto['serie_id'],
                $contexto['turma_id'],
            ),
            'total_pautas_respondidas' => 0,
            'total_infos_complementares' => 0,
            'status_preenchimento' => AvaliacaoAlunoDocumento::STATUS_VAZIO,
            'observacoes_obrigatorias_pendentes' => 0,
            'version' => 1,
        ]);

        $this->dashboardMetricsService->forgetForAvaliacao($avaliacaoId);
        app(AvaliacaoDashboardFactsService::class)->requestRebuild($avaliacaoId);

        return $documento;
    }

    /**
     * @param  array{alternativa_id?: int|null, observacao?: string|null, professor_id?: int|null, componente_curricular_id?: int|null, respondido_em?: mixed}  $dados
     */
    public function salvarPauta(
        AvaliacaoAlunoDocumento $documento,
        int $pautaId,
        array $dados,
        ?int $expectedVersion = null,
    ): AvaliacaoAlunoDocumento {
        return DB::transaction(function () use ($documento, $pautaId, $dados, $expectedVersion): AvaliacaoAlunoDocumento {
            $documento = AvaliacaoAlunoDocumento::query()->lockForUpdate()->findOrFail($documento->id);
            $this->assertVersion($documento, $expectedVersion);

            $payload = $this->normalizarPayload($documento->payload);
            $chave = (string) $pautaId;
            $alternativaId = array_key_exists('alternativa_id', $dados)
                ? ($dados['alternativa_id'] !== null ? (int) $dados['alternativa_id'] : null)
                : (isset($payload['pautas'][$chave]['alternativa_id'])
                    ? (int) $payload['pautas'][$chave]['alternativa_id']
                    : null);

            if ($alternativaId === null || $alternativaId <= 0) {
                unset($payload['pautas'][$chave]);
            } else {
                $observacao = array_key_exists('observacao', $dados)
                    ? $this->normalizarTexto($dados['observacao'] ?? null)
                    : ($payload['pautas'][$chave]['observacao'] ?? null);

                $componenteId = array_key_exists('componente_curricular_id', $dados)
                    ? ($dados['componente_curricular_id'] !== null ? (int) $dados['componente_curricular_id'] : null)
                    : ($payload['pautas'][$chave]['componente_curricular_id'] ?? null);

                if ($componenteId === null) {
                    $componenteId = Pauta::query()->whereKey($pautaId)->value('componente_curricular_id');
                    $componenteId = $componenteId ? (int) $componenteId : null;
                }

                $professorId = array_key_exists('professor_id', $dados)
                    ? ($dados['professor_id'] !== null ? (int) $dados['professor_id'] : null)
                    : ($payload['pautas'][$chave]['professor_id'] ?? null);

                $respondidoEm = $dados['respondido_em'] ?? now()->toIso8601String();
                if ($respondidoEm instanceof \DateTimeInterface) {
                    $respondidoEm = $respondidoEm->format(DATE_ATOM);
                }

                $payload['pautas'][$chave] = array_filter([
                    'pauta_id' => (int) $pautaId,
                    'alternativa_id' => $alternativaId,
                    'observacao' => $observacao,
                    'professor_id' => $professorId,
                    'componente_curricular_id' => $componenteId,
                    'respondido_em' => (string) $respondidoEm,
                ], fn ($value) => $value !== null && $value !== '');
            }

            return $this->persistirPayload($documento, $payload);
        });
    }

    /**
     * @param  array<int, array{alternativa_id?: int|null, observacao?: string|null, professor_id?: int|null, componente_curricular_id?: int|null, respondido_em?: mixed}>  $respostasPorPauta
     */
    public function salvarPautasEmMassa(
        AvaliacaoAlunoDocumento $documento,
        array $respostasPorPauta,
        ?int $expectedVersion = null,
    ): AvaliacaoAlunoDocumento {
        return DB::transaction(function () use ($documento, $respostasPorPauta, $expectedVersion): AvaliacaoAlunoDocumento {
            $documento = AvaliacaoAlunoDocumento::query()->lockForUpdate()->findOrFail($documento->id);
            $this->assertVersion($documento, $expectedVersion);

            $payload = $this->normalizarPayload($documento->payload);
            $pautaIds = array_map('intval', array_keys($respostasPorPauta));
            $componentes = Pauta::query()
                ->whereIn('id', $pautaIds)
                ->pluck('componente_curricular_id', 'id');

            foreach ($respostasPorPauta as $pautaId => $dados) {
                $chave = (string) (int) $pautaId;
                $alternativaId = array_key_exists('alternativa_id', $dados)
                    ? ($dados['alternativa_id'] !== null ? (int) $dados['alternativa_id'] : null)
                    : null;

                if ($alternativaId === null || $alternativaId <= 0) {
                    unset($payload['pautas'][$chave]);

                    continue;
                }

                $componenteId = array_key_exists('componente_curricular_id', $dados)
                    ? ($dados['componente_curricular_id'] !== null ? (int) $dados['componente_curricular_id'] : null)
                    : (isset($componentes[(int) $pautaId]) ? (int) $componentes[(int) $pautaId] : null);

                $respondidoEm = $dados['respondido_em'] ?? now()->toIso8601String();
                if ($respondidoEm instanceof \DateTimeInterface) {
                    $respondidoEm = $respondidoEm->format(DATE_ATOM);
                }

                $payload['pautas'][$chave] = array_filter([
                    'pauta_id' => (int) $pautaId,
                    'alternativa_id' => $alternativaId,
                    'observacao' => array_key_exists('observacao', $dados)
                        ? $this->normalizarTexto($dados['observacao'] ?? null)
                        : null,
                    'professor_id' => array_key_exists('professor_id', $dados)
                        ? ($dados['professor_id'] !== null ? (int) $dados['professor_id'] : null)
                        : null,
                    'componente_curricular_id' => $componenteId,
                    'respondido_em' => (string) $respondidoEm,
                ], fn ($value) => $value !== null && $value !== '');
            }

            return $this->persistirPayload($documento, $payload);
        });
    }

    public function removerPauta(
        AvaliacaoAlunoDocumento $documento,
        int $pautaId,
        ?int $expectedVersion = null,
    ): AvaliacaoAlunoDocumento {
        return $this->salvarPauta($documento, $pautaId, ['alternativa_id' => null], $expectedVersion);
    }

    public function salvarInfoComplementar(
        AvaliacaoAlunoDocumento $documento,
        int $componenteId,
        ?string $texto,
        ?int $professorId = null,
        ?int $expectedVersion = null,
    ): AvaliacaoAlunoDocumento {
        return DB::transaction(function () use ($documento, $componenteId, $texto, $professorId, $expectedVersion): AvaliacaoAlunoDocumento {
            $documento = AvaliacaoAlunoDocumento::query()->lockForUpdate()->findOrFail($documento->id);
            $this->assertVersion($documento, $expectedVersion);

            $payload = $this->normalizarPayload($documento->payload);
            $chave = (string) $componenteId;
            $textoNormalizado = $this->normalizarTexto($texto);

            if ($textoNormalizado === null) {
                unset($payload['informacoes_complementares'][$chave]);
            } else {
                $payload['informacoes_complementares'][$chave] = array_filter([
                    'texto' => $textoNormalizado,
                    'professor_id' => $professorId,
                    'atualizado_em' => now()->toIso8601String(),
                ], fn ($value) => $value !== null && $value !== '');
            }

            return $this->persistirPayload($documento, $payload);
        });
    }

    /**
     * Move todos os documentos do aluno origem para o destino, com snapshot histórico.
     */
    public function moverDocumentosDoAluno(
        Aluno $origem,
        Aluno $destino,
        string $movimentacaoTipo,
        ?User $usuario = null,
    ): int {
        if (! in_array($movimentacaoTipo, [
            AvaliacaoAlunoDocumentoHistorico::MOVIMENTACAO_REMANEJAMENTO,
            AvaliacaoAlunoDocumentoHistorico::MOVIMENTACAO_TRANSFERENCIA,
        ], true)) {
            throw new RuntimeException('Tipo de movimentacao invalido para historico avaliativo.');
        }

        $destino->loadMissing('turma');
        $contextoDestino = $this->contextoDoAluno($destino);
        $movidos = 0;

        return (int) DB::transaction(function () use ($origem, $destino, $movimentacaoTipo, $usuario, $contextoDestino, &$movidos): int {
            $documentos = AvaliacaoAlunoDocumento::query()
                ->where('aluno_id', (int) $origem->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($documentos as $documento) {
                $this->registrarHistorico($documento, $origem, $destino, $movimentacaoTipo, $usuario);

                $conflito = AvaliacaoAlunoDocumento::query()
                    ->where('avaliacao_id', (int) $documento->avaliacao_id)
                    ->where('aluno_id', (int) $destino->id)
                    ->whereKeyNot((int) $documento->id)
                    ->lockForUpdate()
                    ->first();

                if ($conflito) {
                    $this->registrarHistorico(
                        $conflito,
                        $destino,
                        $destino,
                        $movimentacaoTipo,
                        $usuario,
                    );

                    // Mantém o payload mais completo e descarta o vazio/conflitante.
                    $documento = $this->resolverConflitoDocumentos($conflito, $documento);
                }

                $documento->forceFill([
                    'aluno_id' => (int) $destino->id,
                    'cgm' => (string) $destino->cgm,
                    'turma_id' => $contextoDestino['turma_id'],
                    'escola_id' => $contextoDestino['escola_id'],
                    'serie_id' => $contextoDestino['serie_id'],
                    'total_pautas_esperadas' => $this->contarPautasEsperadas(
                        (int) $documento->avaliacao_id,
                        $contextoDestino['serie_id'],
                        $contextoDestino['turma_id'],
                    ),
                    'version' => (int) $documento->version + 1,
                ])->save();

                $this->recalcularMetricas($documento->fresh());
                $this->dashboardMetricsService->forgetForAvaliacao((int) $documento->avaliacao_id);
                $this->requestDashboardFacts((int) $documento->avaliacao_id);
                $movidos++;
            }

            return $movidos;
        });
    }

    private function requestDashboardFacts(int $avaliacaoId): void
    {
        app(AvaliacaoDashboardFactsService::class)->requestRebuild($avaliacaoId);
    }

    /**
     * Recalcula métricas denormalizadas no próprio documento.
     * Mantido como alias por compatibilidade com chamadas existentes.
     */
    public function recalcularMetricasEFatos(AvaliacaoAlunoDocumento $documento): AvaliacaoAlunoDocumento
    {
        return $this->recalcularMetricas($documento);
    }

    public function recalcularMetricas(AvaliacaoAlunoDocumento $documento): AvaliacaoAlunoDocumento
    {
        $payload = $this->normalizarPayload($documento->payload);
        $pautas = $payload['pautas'];
        $infos = $payload['informacoes_complementares'];

        $alternativaIds = [];
        $professorIds = [];
        $pautaIdsRespondidas = [];
        $datas = [];
        $observacoesPendentes = 0;
        $pautasNormalizadas = [];

        $alternativaTemObservacao = [];
        $idsAlternativas = collect($pautas)
            ->pluck('alternativa_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($idsAlternativas !== []) {
            $alternativaTemObservacao = Alternativa::query()
                ->whereIn('id', $idsAlternativas)
                ->pluck('tem_observacao', 'id')
                ->map(fn ($v) => (bool) $v)
                ->all();
        }

        foreach ($pautas as $pautaId => $resposta) {
            if (! is_array($resposta)) {
                continue;
            }

            $pautaIdInt = (int) $pautaId;
            $alternativaId = isset($resposta['alternativa_id']) ? (int) $resposta['alternativa_id'] : null;
            if (! $alternativaId) {
                continue;
            }

            $pautaIdsRespondidas[] = $pautaIdInt;
            $alternativaIds[] = $alternativaId;

            $professorId = isset($resposta['professor_id']) ? (int) $resposta['professor_id'] : null;
            if ($professorId) {
                $professorIds[] = $professorId;
            }

            $observacao = $this->normalizarTexto($resposta['observacao'] ?? null);
            $temObservacao = $observacao !== null;

            if (($alternativaTemObservacao[$alternativaId] ?? false) && ! $temObservacao) {
                $observacoesPendentes++;
            }

            $respondidoEm = $resposta['respondido_em'] ?? null;
            if ($respondidoEm) {
                try {
                    $datas[] = \Carbon\Carbon::parse($respondidoEm);
                } catch (\Throwable) {
                    // ignora data inválida
                }
            }

            // Garante pauta_id dentro do item para consultas JSON (JSON_TABLE) e leitura.
            $pautasNormalizadas[(string) $pautaIdInt] = array_filter([
                'pauta_id' => $pautaIdInt,
                'alternativa_id' => $alternativaId,
                'observacao' => $observacao,
                'professor_id' => $professorId,
                'componente_curricular_id' => isset($resposta['componente_curricular_id'])
                    ? (int) $resposta['componente_curricular_id']
                    : null,
                'respondido_em' => $respondidoEm ? (string) $respondidoEm : null,
            ], fn ($value) => $value !== null && $value !== '');
        }

        $payload['pautas'] = $pautasNormalizadas;

        foreach ($infos as $info) {
            if (! is_array($info)) {
                continue;
            }

            $professorId = isset($info['professor_id']) ? (int) $info['professor_id'] : null;
            if ($professorId) {
                $professorIds[] = $professorId;
            }
        }

        $alternativaIds = array_values(array_unique($alternativaIds));
        $professorIds = array_values(array_unique($professorIds));
        $pautaIdsRespondidas = array_values(array_unique($pautaIdsRespondidas));

        $totalRespondidas = count($pautaIdsRespondidas);
        $totalEsperadas = max(
            (int) $documento->total_pautas_esperadas,
            $this->contarPautasEsperadas(
                (int) $documento->avaliacao_id,
                $documento->serie_id ? (int) $documento->serie_id : null,
                $documento->turma_id ? (int) $documento->turma_id : null,
            )
        );
        $totalInfos = count(array_filter($infos, fn ($item) => is_array($item) && filled($item['texto'] ?? null)));

        $status = AvaliacaoAlunoDocumento::STATUS_VAZIO;
        if ($totalRespondidas > 0 && $totalEsperadas > 0 && $totalRespondidas >= $totalEsperadas && $observacoesPendentes === 0) {
            $status = AvaliacaoAlunoDocumento::STATUS_COMPLETO;
        } elseif ($totalRespondidas > 0 || $totalInfos > 0) {
            $status = AvaliacaoAlunoDocumento::STATUS_PARCIAL;
        }

        $primeira = null;
        $ultima = null;
        if ($datas !== []) {
            $primeira = collect($datas)->min();
            $ultima = collect($datas)->max();
        }

        $documento->forceFill([
            'payload' => $payload,
            'alternativa_ids' => $alternativaIds,
            'professor_ids' => $professorIds,
            'pauta_ids_respondidas' => $pautaIdsRespondidas,
            'total_pautas_esperadas' => $totalEsperadas,
            'total_pautas_respondidas' => $totalRespondidas,
            'total_infos_complementares' => $totalInfos,
            'status_preenchimento' => $status,
            'observacoes_obrigatorias_pendentes' => $observacoesPendentes,
            'primeira_resposta_em' => $primeira,
            'ultima_resposta_em' => $ultima,
        ])->save();

        return $documento->fresh();
    }

    /**
     * @return array{turma_id: int, escola_id: int, serie_id: int|null}
     */
    public function contextoDoAluno(Aluno $aluno): array
    {
        $aluno->loadMissing('turma');

        $turma = $aluno->turma;
        if (! $turma) {
            $turma = Turma::query()->find((int) $aluno->id_turma);
        }

        if (! $turma) {
            throw new RuntimeException('Turma do aluno nao encontrada para documento avaliativo.');
        }

        return [
            'turma_id' => (int) $turma->id,
            'escola_id' => (int) $turma->id_escola,
            'serie_id' => $turma->id_serie ? (int) $turma->id_serie : null,
        ];
    }

    public function contarPautasEsperadas(int $avaliacaoId, ?int $serieId, ?int $turmaId = null): int
    {
        $seriesIds = collect([$serieId])->filter()->map(fn ($id): int => (int) $id);

        if ($turmaId) {
            $turmasAvaliativas = Turma::query()
                ->whereHas('avaliacoes', fn ($query) => $query->whereKey($avaliacaoId))
                ->with('serie:id,nome')
                ->get(['id', 'nome', 'turno', 'id_serie', 'id_escola']);
            $origens = app(TurmaAvaliacaoAlunoScopeService::class)->origensPorTurma($turmasAvaliativas);

            $seriesIds = $seriesIds->merge(
                $turmasAvaliativas
                    ->filter(fn (Turma $turma): bool => ($origens[(int) $turma->id] ?? (int) $turma->id) === $turmaId)
                    ->pluck('id_serie')
            );
        }

        $seriesIds = $seriesIds->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all();

        return (int) DB::table('avaliacao_pauta as ap')
            ->join('pautas as p', 'p.id', '=', 'ap.pauta_id')
            ->where('ap.avaliacao_id', $avaliacaoId)
            ->where('p.status', true)
            ->where(function ($query) use ($seriesIds): void {
                $query->whereNull('p.serie_id');
                if ($seriesIds !== []) {
                    $query->orWhereIn('p.serie_id', $seriesIds);
                }
            })
            ->count();
    }

    /**
     * @param  Collection<int, Aluno>|array<int, Aluno|int>  $alunos
     * @return Collection<int, AvaliacaoAlunoDocumento> keyBy aluno_id
     */
    public function documentosDaAvaliacaoParaAlunos(int $avaliacaoId, Collection|array $alunos): Collection
    {
        $alunoIds = collect($alunos)
            ->map(fn ($aluno) => $aluno instanceof Aluno ? (int) $aluno->id : (int) $aluno)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($alunoIds === []) {
            return collect();
        }

        return AvaliacaoAlunoDocumento::query()
            ->where('avaliacao_id', $avaliacaoId)
            ->whereIn('aluno_id', $alunoIds)
            ->get()
            ->keyBy(fn (AvaliacaoAlunoDocumento $doc) => (int) $doc->aluno_id);
    }

    private function persistirPayload(AvaliacaoAlunoDocumento $documento, array $payload): AvaliacaoAlunoDocumento
    {
        $documento->forceFill([
            'payload' => $payload,
            'version' => (int) $documento->version + 1,
        ])->save();

        $documento = $this->recalcularMetricas($documento->fresh());
        $this->dashboardMetricsService->forgetForAvaliacao((int) $documento->avaliacao_id);
        app(AvaliacaoDashboardFactsService::class)->requestRebuild((int) $documento->avaliacao_id);

        return $documento;
    }

    private function registrarHistorico(
        AvaliacaoAlunoDocumento $documento,
        Aluno $origem,
        Aluno $destino,
        string $movimentacaoTipo,
        ?User $usuario,
    ): void {
        AvaliacaoAlunoDocumentoHistorico::query()->create([
            'avaliacao_id' => (int) $documento->avaliacao_id,
            'documento_id' => (int) $documento->id,
            'aluno_origem_id' => (int) $origem->id,
            'aluno_destino_id' => (int) $destino->id,
            'cgm' => (string) ($documento->cgm ?: $origem->cgm),
            'turma_id' => (int) $documento->turma_id,
            'escola_id' => (int) $documento->escola_id,
            'serie_id' => $documento->serie_id ? (int) $documento->serie_id : null,
            'movimentacao_tipo' => $movimentacaoTipo,
            'payload' => $this->normalizarPayload($documento->payload),
            'responsaveis_snapshot' => $documento->responsaveis_snapshot,
            'responsaveis_snapshot_em' => $documento->responsaveis_snapshot_em,
            'alternativa_ids' => $documento->alternativa_ids ?? [],
            'total_pautas_esperadas' => (int) $documento->total_pautas_esperadas,
            'total_pautas_respondidas' => (int) $documento->total_pautas_respondidas,
            'total_infos_complementares' => (int) $documento->total_infos_complementares,
            'status_preenchimento' => (string) $documento->status_preenchimento,
            'movimentado_em' => now(),
            'movimentado_por' => $usuario?->id,
        ]);
    }

    private function resolverConflitoDocumentos(
        AvaliacaoAlunoDocumento $existenteDestino,
        AvaliacaoAlunoDocumento $vindoDaOrigem,
    ): AvaliacaoAlunoDocumento {
        $scoreExistente = (int) $existenteDestino->total_pautas_respondidas + (int) $existenteDestino->total_infos_complementares;
        $scoreOrigem = (int) $vindoDaOrigem->total_pautas_respondidas + (int) $vindoDaOrigem->total_infos_complementares;

        if ($scoreOrigem >= $scoreExistente) {
            if (empty($vindoDaOrigem->responsaveis_snapshot) && ! empty($existenteDestino->responsaveis_snapshot)) {
                $vindoDaOrigem->forceFill([
                    'responsaveis_snapshot' => $existenteDestino->responsaveis_snapshot,
                    'responsaveis_snapshot_em' => $existenteDestino->responsaveis_snapshot_em,
                ])->save();
            }

            $existenteDestino->delete();

            return $vindoDaOrigem;
        }

        if (empty($existenteDestino->responsaveis_snapshot) && ! empty($vindoDaOrigem->responsaveis_snapshot)) {
            $existenteDestino->forceFill([
                'responsaveis_snapshot' => $vindoDaOrigem->responsaveis_snapshot,
                'responsaveis_snapshot_em' => $vindoDaOrigem->responsaveis_snapshot_em,
            ])->save();
        }

        $vindoDaOrigem->delete();

        return $existenteDestino;
    }

    private function sincronizarContextoSeNecessario(AvaliacaoAlunoDocumento $documento, Aluno $aluno): AvaliacaoAlunoDocumento
    {
        $contexto = $this->contextoDoAluno($aluno);
        $dirty = false;

        if ((int) $documento->turma_id !== $contexto['turma_id']
            || (int) $documento->escola_id !== $contexto['escola_id']
            || (int) ($documento->serie_id ?? 0) !== (int) ($contexto['serie_id'] ?? 0)
            || (string) $documento->cgm !== (string) $aluno->cgm
        ) {
            $documento->forceFill([
                'turma_id' => $contexto['turma_id'],
                'escola_id' => $contexto['escola_id'],
                'serie_id' => $contexto['serie_id'],
                'cgm' => (string) $aluno->cgm,
            ]);
            $dirty = true;
        }

        if ($dirty) {
            $documento->save();
            $this->recalcularMetricas($documento->fresh());
        }

        return $documento->fresh() ?? $documento;
    }

    private function assertVersion(AvaliacaoAlunoDocumento $documento, ?int $expectedVersion): void
    {
        if ($expectedVersion === null) {
            return;
        }

        if ((int) $documento->version !== (int) $expectedVersion) {
            throw new RuntimeException('O documento avaliativo foi alterado por outro usuario. Recarregue e tente novamente.');
        }
    }

    private function normalizarPayload(mixed $payload): array
    {
        $base = AvaliacaoAlunoDocumento::payloadVazio();

        if (! is_array($payload)) {
            return $base;
        }

        $base['v'] = (int) ($payload['v'] ?? 1);
        $base['pautas'] = is_array($payload['pautas'] ?? null) ? $payload['pautas'] : [];
        $base['informacoes_complementares'] = is_array($payload['informacoes_complementares'] ?? null)
            ? $payload['informacoes_complementares']
            : [];

        return $base;
    }

    private function normalizarTexto(mixed $texto): ?string
    {
        if ($texto === null) {
            return null;
        }

        $texto = trim((string) $texto);

        return $texto === '' ? null : $texto;
    }
}
