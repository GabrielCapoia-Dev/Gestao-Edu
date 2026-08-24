<?php

namespace App\Services\Avaliacoes;

use App\Jobs\RebuildAvaliacaoDashboardFactsJob;
use App\Jobs\SyncAvaliacaoDashboardAlunoJob;
use App\Jobs\SyncAvaliacaoDashboardScopeJob;
use App\Models\Aluno;
use App\Models\AvaliacaoAlunoDocumento;
use App\Models\Pauta;
use App\Models\Turma;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AvaliacaoDashboardFactsService
{
    public const STATUS_INCREMENTAL_PENDING = 'incremental_pendente';

    public const STATUS_INCREMENTAL_PROCESSING = 'incremental_processando';

    public const STATUS_REBUILD_PENDING = 'rebuild_pendente';

    public const STATUS_REBUILD_PROCESSING = 'rebuild_processando';

    public const STATUS_REBUILD_FAILED = 'rebuild_erro';

    public const STATUS_READY = 'consolidado';

    public const STATUS_FAILED = 'erro';

    public function requestSyncDocumento(
        int $avaliacaoId,
        int $alunoId,
        string $motivo = 'documento_alterado',
        ?int $documentoVersion = null,
    ): void {
        if ($avaliacaoId <= 0 || $alunoId <= 0) {
            return;
        }

        $documentoVersion ??= (int) (DB::table('avaliacao_aluno_documentos')
            ->where('avaliacao_id', $avaliacaoId)
            ->where('aluno_id', $alunoId)
            ->value('version') ?? 0);

        $agora = now();
        $inserida = DB::table('avaliacao_dashboard_pendencias')->insertOrIgnore([
            'avaliacao_id' => $avaliacaoId,
            'aluno_id' => $alunoId,
            'documento_version' => max(0, $documentoVersion),
            'geracao' => 1,
            'tentativas' => 0,
            'motivo' => mb_substr($motivo, 0, 64),
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        if ($inserida === 0) {
            DB::table('avaliacao_dashboard_pendencias')
                ->where('avaliacao_id', $avaliacaoId)
                ->where('aluno_id', $alunoId)
                ->update([
                    'documento_version' => DB::raw(
                        'CASE WHEN documento_version < '.max(0, $documentoVersion)
                        .' THEN '.max(0, $documentoVersion).' ELSE documento_version END'
                    ),
                    'geracao' => DB::raw('geracao + 1'),
                    'tentativas' => 0,
                    'motivo' => mb_substr($motivo, 0, 64),
                    'updated_at' => $agora,
                ]);
        }

        $this->markIncrementalPending($avaliacaoId);

        // A linha persistente preserva a geração mais recente. Uma pendência
        // já existente será consumida pelo job atual ou pelo recovery.
        if ($inserida === 1) {
            SyncAvaliacaoDashboardAlunoJob::dispatch($avaliacaoId, $alunoId)->afterCommit();
        }
    }

    public function requestSyncPauta(
        int $avaliacaoId,
        int $pautaId,
        string $motivo = 'pauta_alterada',
    ): void {
        if ($avaliacaoId <= 0 || $pautaId <= 0) {
            return;
        }

        $this->requestSyncScope(
            $avaliacaoId,
            SyncAvaliacaoDashboardScopeJob::SCOPE_PAUTA,
            $pautaId,
            $motivo,
        );
    }

    public function requestSyncTurma(
        int $avaliacaoId,
        int $turmaId,
        string $motivo = 'turma_alterada',
    ): void {
        if ($avaliacaoId <= 0 || $turmaId <= 0) {
            return;
        }

        $this->requestSyncScope(
            $avaliacaoId,
            SyncAvaliacaoDashboardScopeJob::SCOPE_TURMA,
            $turmaId,
            $motivo,
        );
    }

    private function requestSyncScope(
        int $avaliacaoId,
        string $escopo,
        int $escopoId,
        string $motivo,
    ): void {
        $agora = now();
        $inserida = DB::table('avaliacao_dashboard_escopo_pendencias')->insertOrIgnore([
            'avaliacao_id' => $avaliacaoId,
            'escopo' => $escopo,
            'escopo_id' => $escopoId,
            'geracao' => 1,
            'tentativas' => 0,
            'motivo' => mb_substr($motivo, 0, 64),
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        if ($inserida === 0) {
            DB::table('avaliacao_dashboard_escopo_pendencias')
                ->where('avaliacao_id', $avaliacaoId)
                ->where('escopo', $escopo)
                ->where('escopo_id', $escopoId)
                ->update([
                    'geracao' => DB::raw('geracao + 1'),
                    'tentativas' => 0,
                    'motivo' => mb_substr($motivo, 0, 64),
                    'updated_at' => $agora,
                ]);
        }

        $this->markIncrementalPending($avaliacaoId);

        if ($inserida === 1) {
            SyncAvaliacaoDashboardScopeJob::dispatch(
                $avaliacaoId,
                $escopo,
                $escopoId,
                $motivo,
            )->afterCommit();
        }
    }

    /**
     * Sincroniza uma alteração ampla de cadastro por turmas direcionadas.
     * Não utiliza nem agenda o rebuild integral de reparo.
     */
    public function requestSyncEstruturaAvaliacao(
        int $avaliacaoId,
        string $motivo = 'avaliacao_estrutura_alterada',
    ): void {
        if ($avaliacaoId <= 0) {
            return;
        }

        $turmaIds = DB::table('avaliacao_turma')
            ->where('avaliacao_id', $avaliacaoId)
            ->pluck('turma_id')
            ->merge(DB::table('avaliacao_dashboard_fatos')
                ->where('avaliacao_id', $avaliacaoId)
                ->distinct()
                ->pluck('turma_id'))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        foreach ($turmaIds as $turmaId) {
            $this->requestSyncTurma($avaliacaoId, $turmaId, $motivo);
        }

        app(AvaliacaoDashboardMetricsService::class)->forgetForAvaliacao($avaliacaoId);
    }

    /**
     * Reconstrução completa é reservada exclusivamente ao comando manual de reparo.
     */
    public function requestRebuild(int $avaliacaoId, string $motivo = 'rebuild_explicito'): bool
    {
        if ($avaliacaoId <= 0) {
            return false;
        }

        if (! $this->markRebuildPending($avaliacaoId)) {
            return false;
        }

        RebuildAvaliacaoDashboardFactsJob::dispatch($avaliacaoId, $motivo)->afterCommit();

        return true;
    }

    public function dispatchPending(?int $avaliacaoId = null, int $limit = 200, bool $force = false): int
    {
        $recoveryCutoff = now()->subSeconds(
            max(60, (int) config('avaliacoes_dashboard.incremental.recovery_after', 180)),
        );
        $query = DB::table('avaliacao_dashboard_pendencias')
            ->where('tentativas', '<', max(1, (int) config('avaliacoes_dashboard.incremental.max_attempts', 9)))
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(max(1, min($limit, 1000)));

        if (! $force) {
            $query->where('updated_at', '<=', $recoveryCutoff);
        }

        if ($avaliacaoId !== null && $avaliacaoId > 0) {
            $query->where('avaliacao_id', $avaliacaoId);
        }

        $pendencias = $query->get(['id', 'avaliacao_id', 'aluno_id']);

        foreach ($pendencias as $pendencia) {
            SyncAvaliacaoDashboardAlunoJob::dispatch(
                (int) $pendencia->avaliacao_id,
                (int) $pendencia->aluno_id,
            );
        }

        if ($pendencias->isNotEmpty()) {
            DB::table('avaliacao_dashboard_pendencias')
                ->whereIn('id', $pendencias->pluck('id')->all())
                ->update(['updated_at' => now()]);
        }

        return $pendencias->count();
    }

    public function dispatchPendingScopes(?int $avaliacaoId = null, int $limit = 100, bool $force = false): int
    {
        $recoveryCutoff = now()->subSeconds(
            max(60, (int) config('avaliacoes_dashboard.incremental.recovery_after', 180)),
        );
        $query = DB::table('avaliacao_dashboard_escopo_pendencias')
            ->where('tentativas', '<', max(1, (int) config('avaliacoes_dashboard.scope.max_attempts', 9)))
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(max(1, min($limit, 500)));

        if (! $force) {
            $query->where('updated_at', '<=', $recoveryCutoff);
        }

        if ($avaliacaoId !== null && $avaliacaoId > 0) {
            $query->where('avaliacao_id', $avaliacaoId);
        }

        $pendencias = $query->get(['id', 'avaliacao_id', 'escopo', 'escopo_id', 'motivo']);

        foreach ($pendencias as $pendencia) {
            SyncAvaliacaoDashboardScopeJob::dispatch(
                (int) $pendencia->avaliacao_id,
                (string) $pendencia->escopo,
                (int) $pendencia->escopo_id,
                (string) ($pendencia->motivo ?: 'recovery_escopo_pendente'),
            );
        }

        if ($pendencias->isNotEmpty()) {
            DB::table('avaliacao_dashboard_escopo_pendencias')
                ->whereIn('id', $pendencias->pluck('id')->all())
                ->update(['updated_at' => now()]);
        }

        return $pendencias->count();
    }

    /** Corrige somente estados incrementais cujo último job já removeu a pendência. */
    public function reconcileFinishedIncrementalStatuses(): int
    {
        $avaliacaoIds = DB::table('avaliacao_dashboard_consolidacoes')
            ->whereIn('status', [
                self::STATUS_INCREMENTAL_PENDING,
                self::STATUS_INCREMENTAL_PROCESSING,
                self::STATUS_FAILED,
            ])
            ->pluck('avaliacao_id');
        $reconciliadas = 0;

        foreach ($avaliacaoIds as $avaliacaoId) {
            $reconciliadas += DB::transaction(function () use ($avaliacaoId): int {
                $consolidacao = DB::table('avaliacao_dashboard_consolidacoes')
                    ->where('avaliacao_id', (int) $avaliacaoId)
                    ->lockForUpdate()
                    ->first(['status']);

                if (! $consolidacao
                    || ! in_array((string) $consolidacao->status, [
                        self::STATUS_INCREMENTAL_PENDING,
                        self::STATUS_INCREMENTAL_PROCESSING,
                        self::STATUS_FAILED,
                    ], true)
                    || $this->pendingCount((int) $avaliacaoId) > 0
                ) {
                    return 0;
                }

                $agora = now();

                return DB::table('avaliacao_dashboard_consolidacoes')
                    ->where('avaliacao_id', (int) $avaliacaoId)
                    ->update([
                        'status' => self::STATUS_READY,
                        'pendencias_count' => 0,
                        'consolidada_em' => $agora,
                        'ultima_atualizacao_em' => $agora,
                        'erro' => null,
                        'updated_at' => $agora,
                    ]);
            }, 3);
        }

        return $reconciliadas;
    }

    public function status(int $avaliacaoId): ?object
    {
        return DB::table('avaliacao_dashboard_consolidacoes')
            ->where('avaliacao_id', $avaliacaoId)
            ->first();
    }

    public function processPendingDocumento(int $avaliacaoId, int $alunoId, int $tentativa = 1): void
    {
        $pendencia = DB::table('avaliacao_dashboard_pendencias')
            ->where('avaliacao_id', $avaliacaoId)
            ->where('aluno_id', $alunoId)
            ->first();

        if (! $pendencia) {
            return;
        }

        $inicio = now();
        $iniciouEm = microtime(true);
        $this->markIncrementalProcessing($avaliacaoId);

        DB::table('avaliacao_dashboard_pendencias')
            ->where('id', (int) $pendencia->id)
            ->update([
                'tentativas' => DB::raw('tentativas + 1'),
                'updated_at' => now(),
            ]);

        try {
            $resultado = $this->syncDocumento($avaliacaoId, $alunoId);

            $removida = DB::table('avaliacao_dashboard_pendencias')
                ->where('id', (int) $pendencia->id)
                ->where('geracao', (int) $pendencia->geracao)
                ->delete();

            $this->finishProcessing($avaliacaoId, preserveRebuild: true);
            app(AvaliacaoDashboardMetricsService::class)->forgetForAvaliacao($avaliacaoId);

            Log::info('Sincronização incremental do dashboard de avaliações concluída.', [
                'avaliacao_id' => $avaliacaoId,
                'aluno_id' => $alunoId,
                'tipo_sincronizacao' => 'documento',
                'motivo' => (string) ($pendencia->motivo ?? 'documento_alterado'),
                'tentativa' => $tentativa,
                'iniciada_em' => $inicio->toIso8601String(),
                'finalizada_em' => now()->toIso8601String(),
                'duracao_ms' => (int) round((microtime(true) - $iniciouEm) * 1000),
                ...$resultado,
                'status_final' => $removida === 1 ? 'processado' : 'nova_geracao_pendente',
            ]);

            if ($removida === 0) {
                SyncAvaliacaoDashboardAlunoJob::dispatch($avaliacaoId, $alunoId);
            }
        } catch (Throwable $exception) {
            $this->markFailed($avaliacaoId, $exception->getMessage(), self::STATUS_INCREMENTAL_PROCESSING);

            Log::error('Falha na sincronização incremental do dashboard de avaliações.', [
                'avaliacao_id' => $avaliacaoId,
                'aluno_id' => $alunoId,
                'tipo_sincronizacao' => 'documento',
                'motivo' => (string) ($pendencia->motivo ?? 'documento_alterado'),
                'tentativa' => $tentativa,
                'iniciada_em' => $inicio->toIso8601String(),
                'finalizada_em' => now()->toIso8601String(),
                'duracao_ms' => (int) round((microtime(true) - $iniciouEm) * 1000),
                'status_final' => self::STATUS_FAILED,
                'erro' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /** @return array{registros_inseridos: int, registros_atualizados: int, registros_removidos: int} */
    public function syncDocumento(int $avaliacaoId, int $alunoId): array
    {
        return $this->syncDocumentoComContexto(
            $avaliacaoId,
            $alunoId,
            $this->contextoAvaliacao($avaliacaoId),
        );
    }

    /** @return array{registros_inseridos: int, registros_atualizados: int, registros_removidos: int} */
    public function syncPauta(int $avaliacaoId, int $pautaId): array
    {
        $contexto = $this->contextoAvaliacao($avaliacaoId);
        $pauta = $contexto['pautas']->get($pautaId);

        if ($pauta instanceof Pauta) {
            $turmas = $contexto['turmas']->filter(function (Turma $turma) use ($contexto, $pauta): bool {
                $escopo = $contexto['escopos'][(int) $turma->id] ?? null;

                return is_array($escopo) && $this->pautaAplicaNaTurma(
                    $pauta,
                    $turma,
                    (int) $escopo['turma_origem_id'] !== (int) $turma->id,
                );
            });
            $contexto['turmas'] = $turmas;
            $contexto['escopos'] = collect($contexto['escopos'])
                ->only($turmas->pluck('id')->map(fn ($id): int => (int) $id)->all())
                ->all();
        } else {
            $contexto['turmas'] = collect();
            $contexto['escopos'] = [];
        }

        $alunoIds = $this->alunoIdsDoContexto($contexto)
            ->merge(DB::table('avaliacao_dashboard_fatos')
                ->where('avaliacao_id', $avaliacaoId)
                ->where('pauta_id', $pautaId)
                ->pluck('aluno_id'))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        return $this->syncAlunos($avaliacaoId, $alunoIds, $contexto, [$pautaId]);
    }

    /** @return array{registros_inseridos: int, registros_atualizados: int, registros_removidos: int} */
    public function syncTurma(int $avaliacaoId, int $turmaId): array
    {
        $contexto = $this->contextoAvaliacao($avaliacaoId);
        $alunoIds = $this->alunoIdsDoContexto($contexto, $turmaId)
            ->merge(DB::table('avaliacao_dashboard_fatos')
                ->where('avaliacao_id', $avaliacaoId)
                ->where('turma_id', $turmaId)
                ->pluck('aluno_id'))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        return $this->syncAlunos($avaliacaoId, $alunoIds, $contexto);
    }

    /**
     * Reprocessa a avaliação de modo idempotente e em transações curtas por aluno.
     * Não há DELETE global, INSERT massivo nem expansão JSON global.
     *
     * @return array{registros_inseridos: int, registros_atualizados: int, registros_removidos: int}
     */
    public function syncAvaliacaoCompleta(int $avaliacaoId): array
    {
        $contexto = $this->contextoAvaliacao($avaliacaoId);
        $alunoIds = $this->alunoIdsDoContexto($contexto)
            ->merge(DB::table('avaliacao_dashboard_fatos')
                ->where('avaliacao_id', $avaliacaoId)
                ->distinct()
                ->pluck('aluno_id'))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return $this->syncAlunos($avaliacaoId, $alunoIds, $contexto);
    }

    /** Alias mantido para comando e integrações existentes. */
    public function rebuild(int $avaliacaoId, string $motivo = 'rebuild_explicito', int $tentativa = 1): bool
    {
        $lock = Cache::lock(
            'avaliacao-dashboard-rebuild:'.$avaliacaoId,
            (int) config('avaliacoes_dashboard.full.lock_ttl', 720),
        );

        if (! $lock->get()) {
            return false;
        }

        try {
            $this->processScope($avaliacaoId, 'avaliacao', null, $motivo, $tentativa);

            return true;
        } finally {
            $lock->release();
        }
    }

    public function processPendingScope(
        int $avaliacaoId,
        string $scope,
        int $scopeId,
        int $tentativa = 1,
    ): void {
        $pendencia = DB::table('avaliacao_dashboard_escopo_pendencias')
            ->where('avaliacao_id', $avaliacaoId)
            ->where('escopo', $scope)
            ->where('escopo_id', $scopeId)
            ->first();

        if (! $pendencia) {
            return;
        }

        DB::table('avaliacao_dashboard_escopo_pendencias')
            ->where('id', (int) $pendencia->id)
            ->update([
                'tentativas' => DB::raw('tentativas + 1'),
                'updated_at' => now(),
            ]);

        $this->processScope(
            $avaliacaoId,
            $scope,
            $scopeId,
            (string) ($pendencia->motivo ?: 'escopo_pendente'),
            $tentativa,
            (int) $pendencia->id,
            (int) $pendencia->geracao,
        );
    }

    public function processScope(
        int $avaliacaoId,
        string $scope,
        ?int $scopeId,
        string $motivo,
        int $tentativa = 1,
        ?int $pendenciaId = null,
        ?int $pendenciaGeracao = null,
    ): void {
        $inicio = now();
        $iniciouEm = microtime(true);
        $rebuild = $scope === 'avaliacao';

        if ($rebuild) {
            $this->markRebuildProcessing($avaliacaoId);
        } else {
            $this->markIncrementalProcessing($avaliacaoId);
        }

        try {
            $resultado = match ($scope) {
                'pauta' => $this->syncPauta($avaliacaoId, (int) $scopeId),
                'turma' => $this->syncTurma($avaliacaoId, (int) $scopeId),
                'avaliacao' => $this->syncAvaliacaoCompleta($avaliacaoId),
                default => throw new RuntimeException("Escopo de sincronização inválido: {$scope}."),
            };

            if ($rebuild) {
                DB::table('avaliacao_dashboard_pendencias')
                    ->where('avaliacao_id', $avaliacaoId)
                    ->update(['tentativas' => 0, 'updated_at' => now()]);
                DB::table('avaliacao_dashboard_escopo_pendencias')
                    ->where('avaliacao_id', $avaliacaoId)
                    ->update(['tentativas' => 0, 'updated_at' => now()]);
            }

            $pendenciaRemovida = null;
            if ($pendenciaId !== null && $pendenciaGeracao !== null) {
                $pendenciaRemovida = DB::table('avaliacao_dashboard_escopo_pendencias')
                    ->where('id', $pendenciaId)
                    ->where('geracao', $pendenciaGeracao)
                    ->delete();
            }

            $this->finishProcessing($avaliacaoId, preserveRebuild: ! $rebuild);
            app(AvaliacaoDashboardMetricsService::class)->forgetForAvaliacao($avaliacaoId);

            if ($rebuild && $this->pendingCount($avaliacaoId) > 0) {
                $this->dispatchPending($avaliacaoId, 1000, force: true);
                $this->dispatchPendingScopes($avaliacaoId, 500, force: true);
            }

            Log::info('Sincronização estrutural do dashboard de avaliações concluída.', [
                'avaliacao_id' => $avaliacaoId,
                'aluno_id' => null,
                'tipo_sincronizacao' => $scope,
                'escopo_id' => $scopeId,
                'motivo' => $motivo,
                'tentativa' => $tentativa,
                'iniciada_em' => $inicio->toIso8601String(),
                'finalizada_em' => now()->toIso8601String(),
                'duracao_ms' => (int) round((microtime(true) - $iniciouEm) * 1000),
                ...$resultado,
                'status_final' => (string) ($this->status($avaliacaoId)?->status ?? self::STATUS_READY),
            ]);

            if ($pendenciaRemovida === 0) {
                SyncAvaliacaoDashboardScopeJob::dispatch(
                    $avaliacaoId,
                    $scope,
                    (int) $scopeId,
                    'nova_geracao_escopo_pendente',
                );
            }
        } catch (Throwable $exception) {
            $this->markFailed(
                $avaliacaoId,
                $exception->getMessage(),
                $rebuild ? self::STATUS_REBUILD_PROCESSING : self::STATUS_INCREMENTAL_PROCESSING,
            );

            Log::error('Falha na sincronização estrutural do dashboard de avaliações.', [
                'avaliacao_id' => $avaliacaoId,
                'aluno_id' => null,
                'tipo_sincronizacao' => $scope,
                'escopo_id' => $scopeId,
                'motivo' => $motivo,
                'tentativa' => $tentativa,
                'iniciada_em' => $inicio->toIso8601String(),
                'finalizada_em' => now()->toIso8601String(),
                'duracao_ms' => (int) round((microtime(true) - $iniciouEm) * 1000),
                'status_final' => $rebuild ? self::STATUS_REBUILD_FAILED : self::STATUS_FAILED,
                'erro' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function markFailed(int $avaliacaoId, ?string $erro, ?string $expectedStatus = null): void
    {
        $failureStatus = $expectedStatus === self::STATUS_REBUILD_PROCESSING
            ? self::STATUS_REBUILD_FAILED
            : self::STATUS_FAILED;
        $query = DB::table('avaliacao_dashboard_consolidacoes')->where('avaliacao_id', $avaliacaoId);

        if ($expectedStatus !== null) {
            $query->where('status', $expectedStatus);
        }

        $atualizada = $query->update([
            'status' => $failureStatus,
            'erro' => $erro,
            'updated_at' => now(),
        ]);

        if ($atualizada === 0 && $expectedStatus === null) {
            DB::table('avaliacao_dashboard_consolidacoes')
                ->where('avaliacao_id', $avaliacaoId)
                ->update(['erro' => $erro, 'updated_at' => now()]);
        }
    }

    /**
     * @param  array{turmas: Collection<int, Turma>, escopos: array<int, array{turma_origem_id: int, tipo_vinculo: string}>, pautas: Collection<int, Pauta>}  $contexto
     * @param  Collection<int, int>  $alunoIds
     * @param  list<int>|null  $pautaIds
     * @return array{registros_inseridos: int, registros_atualizados: int, registros_removidos: int}
     */
    private function syncAlunos(int $avaliacaoId, Collection $alunoIds, array $contexto, ?array $pautaIds = null): array
    {
        $total = $this->emptyResult();

        foreach ($alunoIds->chunk(max(1, (int) config('avaliacoes_dashboard.full.chunk_size', 100))) as $chunk) {
            foreach ($chunk as $alunoId) {
                $total = $this->mergeResult(
                    $total,
                    $this->syncDocumentoComContexto($avaliacaoId, (int) $alunoId, $contexto, $pautaIds),
                );
            }
        }

        return $total;
    }

    /**
     * @param  array{turmas: Collection<int, Turma>, escopos: array<int, array{turma_origem_id: int, tipo_vinculo: string}>, pautas: Collection<int, Pauta>}  $contexto
     * @param  list<int>|null  $pautaIds
     * @return array{registros_inseridos: int, registros_atualizados: int, registros_removidos: int}
     */
    private function syncDocumentoComContexto(int $avaliacaoId, int $alunoId, array $contexto, ?array $pautaIds = null): array
    {
        return DB::transaction(function () use ($avaliacaoId, $alunoId, $contexto, $pautaIds): array {
            $documento = AvaliacaoAlunoDocumento::query()
                ->where('avaliacao_id', $avaliacaoId)
                ->where('aluno_id', $alunoId)
                ->lockForUpdate()
                ->first();
            // O documento é a raiz de escrita do autosave. Não bloqueia a
            // linha do aluno para evitar a ordem inversa aluno -> documento,
            // que concorreria com o autosave documento -> FK do aluno.
            $aluno = Aluno::query()->whereKey($alunoId)->first();

            $existentesQuery = DB::table('avaliacao_dashboard_fatos')
                ->where('avaliacao_id', $avaliacaoId)
                ->where('aluno_id', $alunoId);

            if ($pautaIds !== null) {
                $existentesQuery->whereIn('pauta_id', $pautaIds);
            }

            $existentes = $existentesQuery->pluck('pauta_id')->map(fn ($id): int => (int) $id);
            $linhas = $aluno
                ? $this->linhasEsperadas($avaliacaoId, $aluno, $documento, $contexto, $pautaIds)
                : [];
            $pautasEsperadas = collect($linhas)->pluck('pauta_id')->map(fn ($id): int => (int) $id);

            if ($linhas !== []) {
                DB::table('avaliacao_dashboard_fatos')->upsert(
                    array_values($linhas),
                    ['avaliacao_id', 'aluno_id', 'pauta_id'],
                    [
                        'turma_id', 'escola_id', 'serie_id', 'componente_curricular_id',
                        'professor_id', 'alternativa_id', 'respondida', 'observacao_pendente',
                        'status_resposta', 'respondida_em', 'origem_version', 'updated_at',
                    ],
                );
            }

            $removerQuery = DB::table('avaliacao_dashboard_fatos')
                ->where('avaliacao_id', $avaliacaoId)
                ->where('aluno_id', $alunoId);

            if ($pautaIds !== null) {
                $removerQuery->whereIn('pauta_id', $pautaIds);
            }

            if ($pautasEsperadas->isNotEmpty()) {
                $removerQuery->whereNotIn('pauta_id', $pautasEsperadas->all());
            }

            $removidos = $removerQuery->delete();

            return [
                'registros_inseridos' => $pautasEsperadas->diff($existentes)->count(),
                'registros_atualizados' => $pautasEsperadas->intersect($existentes)->count(),
                'registros_removidos' => $removidos,
            ];
        }, 3);
    }

    /**
     * @param  array{turmas: Collection<int, Turma>, escopos: array<int, array{turma_origem_id: int, tipo_vinculo: string}>, pautas: Collection<int, Pauta>}  $contexto
     * @param  list<int>|null  $pautaIds
     * @return array<int, array<string, mixed>>
     */
    private function linhasEsperadas(
        int $avaliacaoId,
        Aluno $aluno,
        ?AvaliacaoAlunoDocumento $documento,
        array $contexto,
        ?array $pautaIds,
    ): array {
        $turmas = $contexto['turmas']->filter(function (Turma $turma) use ($aluno, $contexto): bool {
            $escopo = $contexto['escopos'][(int) $turma->id] ?? null;

            return is_array($escopo)
                && (int) $escopo['turma_origem_id'] === (int) $aluno->id_turma
                && $this->alunoElegivel($aluno, (string) $escopo['tipo_vinculo']);
        });

        if ($turmas->isEmpty()) {
            return [];
        }

        $pautas = $contexto['pautas'];
        if ($pautaIds !== null) {
            $pautas = $pautas->only($pautaIds);
        }

        $payload = is_array($documento?->payload) ? $documento->payload : [];
        $respostas = is_array($payload['pautas'] ?? null) ? $payload['pautas'] : [];

        if ($pautaIds !== null) {
            $respostas = array_intersect_key(
                $respostas,
                array_fill_keys(array_map(fn (int $id): string => (string) $id, $pautaIds), true),
            );
        }

        $alternativaIds = collect($respostas)->pluck('alternativa_id')
            ->filter(fn ($id): bool => (int) $id > 0)->map(fn ($id): int => (int) $id)
            ->unique()->values()->all();
        $alternativas = $alternativaIds === []
            ? collect()
            : DB::table('alternativas')->whereIn('id', $alternativaIds)->pluck('tem_observacao', 'id');
        $professorIds = collect($respostas)->pluck('professor_id')
            ->filter(fn ($id): bool => (int) $id > 0)->map(fn ($id): int => (int) $id)
            ->unique()->values()->all();
        $professoresExistentes = $professorIds === []
            ? collect()
            : DB::table('professores')->whereIn('id', $professorIds)->pluck('id')
                ->mapWithKeys(fn ($id): array => [(int) $id => true]);
        $componenteIds = collect($respostas)->pluck('componente_curricular_id')
            ->filter(fn ($id): bool => (int) $id > 0)->map(fn ($id): int => (int) $id)
            ->unique()->values()->all();
        $componentesExistentes = $componenteIds === []
            ? collect()
            : DB::table('componentes_curriculares')->whereIn('id', $componenteIds)->pluck('id')
                ->mapWithKeys(fn ($id): array => [(int) $id => true]);
        $agora = now();
        $linhas = [];

        foreach ($turmas as $turma) {
            $escopo = $contexto['escopos'][(int) $turma->id];
            $origemDiferente = (int) $escopo['turma_origem_id'] !== (int) $turma->id;

            foreach ($pautas as $pauta) {
                $pautaId = (int) $pauta->id;

                if (isset($linhas[$pautaId]) || ! $this->pautaAplicaNaTurma($pauta, $turma, $origemDiferente)) {
                    continue;
                }

                $resposta = is_array($respostas[(string) $pautaId] ?? null)
                    ? $respostas[(string) $pautaId]
                    : null;
                $alternativaId = (int) ($resposta['alternativa_id'] ?? 0);
                $respondida = $alternativaId > 0 && $alternativas->has($alternativaId);
                $observacao = trim((string) ($resposta['observacao'] ?? ''));
                $observacaoPendente = $respondida
                    && (bool) $alternativas->get($alternativaId, false)
                    && $observacao === '';
                $professorId = (int) ($resposta['professor_id'] ?? 0);
                if ($professorId <= 0 || ! $professoresExistentes->has($professorId)) {
                    $professorId = 0;
                }
                $componenteId = (int) ($resposta['componente_curricular_id'] ?? 0);
                if ($componenteId <= 0 || ! $componentesExistentes->has($componenteId)) {
                    $componenteId = (int) ($pauta->componente_curricular_id ?? 0);
                }

                $linhas[$pautaId] = [
                    'avaliacao_id' => $avaliacaoId,
                    'aluno_id' => (int) $aluno->id,
                    'turma_id' => (int) $turma->id,
                    'escola_id' => (int) $turma->id_escola,
                    'serie_id' => $turma->id_serie ? (int) $turma->id_serie : null,
                    'pauta_id' => $pautaId,
                    'componente_curricular_id' => $componenteId > 0 ? $componenteId : null,
                    'professor_id' => $professorId > 0 ? $professorId : null,
                    'alternativa_id' => $respondida ? $alternativaId : null,
                    'respondida' => $respondida,
                    'observacao_pendente' => $observacaoPendente,
                    'status_resposta' => ! $respondida
                        ? 'pendente'
                        : ($observacaoPendente ? 'pendente_observacao' : 'respondida'),
                    'respondida_em' => $respondida ? $this->dataResposta($resposta['respondido_em'] ?? null) : null,
                    'origem_version' => max(1, (int) ($documento?->version ?? 1)),
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
            }
        }

        return $linhas;
    }

    /**
     * @return array{turmas: Collection<int, Turma>, escopos: array<int, array{turma_origem_id: int, tipo_vinculo: string}>, pautas: Collection<int, Pauta>}
     */
    private function contextoAvaliacao(int $avaliacaoId): array
    {
        $turmas = Turma::query()
            ->whereHas('avaliacoes', fn ($query) => $query->whereKey($avaliacaoId))
            ->with('serie:id,codigo,nome')
            ->orderBy('id')
            ->get(['id', 'nome', 'turno', 'id_serie', 'id_escola']);

        $pautas = Pauta::query()
            ->whereHas('avaliacoes', fn ($query) => $query->whereKey($avaliacaoId))
            ->where('status', true)
            ->orderBy('id')
            ->get(['id', 'serie_id', 'componente_curricular_id', 'status'])
            ->keyBy(fn (Pauta $pauta): int => (int) $pauta->id);

        return [
            'turmas' => $turmas,
            'escopos' => app(TurmaAvaliacaoAlunoScopeService::class)->escoposPorTurma($turmas),
            'pautas' => $pautas,
        ];
    }

    /**
     * @param  array{turmas: Collection<int, Turma>, escopos: array<int, array{turma_origem_id: int, tipo_vinculo: string}>, pautas: Collection<int, Pauta>}  $contexto
     * @return Collection<int, int>
     */
    private function alunoIdsDoContexto(array $contexto, ?int $turmaAlvoId = null): Collection
    {
        $grupos = $contexto['turmas']
            ->filter(fn (Turma $turma): bool => $turmaAlvoId === null || (int) $turma->id === $turmaAlvoId)
            ->map(function (Turma $turma) use ($contexto): ?array {
                $escopo = $contexto['escopos'][(int) $turma->id] ?? null;

                return is_array($escopo) ? [
                    'turma_id' => (int) $escopo['turma_origem_id'],
                    'tipo_vinculo' => (string) $escopo['tipo_vinculo'],
                ] : null;
            })
            ->filter()
            ->unique(fn (array $grupo): string => $grupo['turma_id'].'|'.$grupo['tipo_vinculo']);
        $ids = collect();

        foreach ($grupos as $grupo) {
            $query = Aluno::query()->where('id_turma', $grupo['turma_id']);

            if ($grupo['tipo_vinculo'] === Aluno::TIPO_VINCULO_CONTRA_TURNO) {
                $query->where('tipo_vinculo', Aluno::TIPO_VINCULO_CONTRA_TURNO)
                    ->where('status', Aluno::STATUS_MATRICULADO);
            } else {
                $query->where('tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
                    ->where('status', '!=', Aluno::STATUS_PENDENTE);
            }

            $ids = $ids->merge($query->pluck('id'));
        }

        return $ids->map(fn ($id): int => (int) $id)->unique()->values();
    }

    private function alunoElegivel(Aluno $aluno, string $tipoVinculo): bool
    {
        if ($tipoVinculo === Aluno::TIPO_VINCULO_CONTRA_TURNO) {
            return $aluno->tipo_vinculo === Aluno::TIPO_VINCULO_CONTRA_TURNO
                && $aluno->status === Aluno::STATUS_MATRICULADO;
        }

        return $aluno->tipo_vinculo === Aluno::TIPO_VINCULO_PRINCIPAL
            && $aluno->status !== Aluno::STATUS_PENDENTE;
    }

    private function pautaAplicaNaTurma(Pauta $pauta, Turma $turma, bool $origemDiferente): bool
    {
        if ($pauta->serie_id !== null) {
            return (int) $pauta->serie_id === (int) $turma->id_serie;
        }

        return ! $origemDiferente;
    }

    private function dataResposta(mixed $valor): ?string
    {
        if (! filled($valor)) {
            return null;
        }

        try {
            return Carbon::parse($valor)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function markIncrementalPending(int $avaliacaoId): void
    {
        $agora = now();
        $pendencias = $this->pendingCount($avaliacaoId);

        DB::table('avaliacao_dashboard_consolidacoes')->insertOrIgnore([
            'avaliacao_id' => $avaliacaoId,
            'status' => self::STATUS_INCREMENTAL_PENDING,
            'pendencias_count' => $pendencias,
            'solicitada_em' => $agora,
            'erro' => null,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        DB::table('avaliacao_dashboard_consolidacoes')
            ->where('avaliacao_id', $avaliacaoId)
            ->whereNotIn('status', [
                self::STATUS_REBUILD_PENDING,
                self::STATUS_REBUILD_PROCESSING,
                self::STATUS_REBUILD_FAILED,
                self::STATUS_FAILED,
            ])
            ->update([
                'status' => self::STATUS_INCREMENTAL_PENDING,
                'pendencias_count' => $pendencias,
                'solicitada_em' => $agora,
                'erro' => null,
                'updated_at' => $agora,
            ]);

        DB::table('avaliacao_dashboard_consolidacoes')
            ->where('avaliacao_id', $avaliacaoId)
            ->whereIn('status', [
                self::STATUS_REBUILD_PENDING,
                self::STATUS_REBUILD_PROCESSING,
                self::STATUS_REBUILD_FAILED,
                self::STATUS_FAILED,
            ])
            ->update([
                'pendencias_count' => $pendencias,
                'solicitada_em' => $agora,
                'updated_at' => $agora,
            ]);
    }

    private function markIncrementalProcessing(int $avaliacaoId): void
    {
        DB::table('avaliacao_dashboard_consolidacoes')
            ->where('avaliacao_id', $avaliacaoId)
            ->whereNotIn('status', [
                self::STATUS_REBUILD_PENDING,
                self::STATUS_REBUILD_PROCESSING,
                self::STATUS_REBUILD_FAILED,
                self::STATUS_FAILED,
            ])
            ->update([
                'status' => self::STATUS_INCREMENTAL_PROCESSING,
                'iniciada_em' => now(),
                'erro' => null,
                'updated_at' => now(),
            ]);
    }

    private function markRebuildPending(int $avaliacaoId): bool
    {
        $agora = now();
        $values = [
            'avaliacao_id' => $avaliacaoId,
            'status' => self::STATUS_REBUILD_PENDING,
            'pendencias_count' => $this->pendingCount($avaliacaoId),
            'solicitada_em' => $agora,
            'erro' => null,
            'created_at' => $agora,
            'updated_at' => $agora,
        ];

        if (DB::table('avaliacao_dashboard_consolidacoes')->insertOrIgnore($values) === 1) {
            return true;
        }

        return DB::table('avaliacao_dashboard_consolidacoes')
            ->where('avaliacao_id', $avaliacaoId)
            ->where('status', '!=', self::STATUS_REBUILD_PROCESSING)
            ->update([
                'status' => self::STATUS_REBUILD_PENDING,
                'pendencias_count' => $values['pendencias_count'],
                'solicitada_em' => $agora,
                'erro' => null,
                'updated_at' => $agora,
            ]) === 1;
    }

    private function markRebuildProcessing(int $avaliacaoId): void
    {
        $agora = now();

        DB::table('avaliacao_dashboard_consolidacoes')->upsert([[
            'avaliacao_id' => $avaliacaoId,
            'status' => self::STATUS_REBUILD_PROCESSING,
            'pendencias_count' => $this->pendingCount($avaliacaoId),
            'iniciada_em' => $agora,
            'erro' => null,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]], ['avaliacao_id'], ['status', 'pendencias_count', 'iniciada_em', 'erro', 'updated_at']);
    }

    private function finishProcessing(int $avaliacaoId, bool $preserveRebuild = false): void
    {
        DB::transaction(function () use ($avaliacaoId, $preserveRebuild): void {
            $consolidacao = DB::table('avaliacao_dashboard_consolidacoes')
                ->where('avaliacao_id', $avaliacaoId)
                ->lockForUpdate()
                ->first(['status']);

            if (! $consolidacao) {
                return;
            }

            $agora = now();
            $pendencias = $this->pendingCount($avaliacaoId);
            $status = (string) $consolidacao->status;
            $rebuildPreservado = in_array(
                $status,
                [self::STATUS_REBUILD_PENDING, self::STATUS_REBUILD_PROCESSING, self::STATUS_REBUILD_FAILED],
                true,
            );
            $falhaIncrementalPreservada = $status === self::STATUS_FAILED && $pendencias > 0;

            if (($preserveRebuild && $rebuildPreservado)
                || $falhaIncrementalPreservada
                || (! $preserveRebuild && $status !== self::STATUS_REBUILD_PROCESSING)
            ) {
                DB::table('avaliacao_dashboard_consolidacoes')
                    ->where('avaliacao_id', $avaliacaoId)
                    ->update([
                        'pendencias_count' => $pendencias,
                        'ultima_atualizacao_em' => $agora,
                        'updated_at' => $agora,
                    ]);

                return;
            }

            DB::table('avaliacao_dashboard_consolidacoes')
                ->where('avaliacao_id', $avaliacaoId)
                ->update([
                    'status' => $pendencias > 0 ? self::STATUS_INCREMENTAL_PENDING : self::STATUS_READY,
                    'pendencias_count' => $pendencias,
                    'consolidada_em' => $pendencias === 0 ? $agora : DB::raw('consolidada_em'),
                    'ultima_atualizacao_em' => $agora,
                    'erro' => null,
                    'updated_at' => $agora,
                ]);
        }, 3);
    }

    private function pendingCount(int $avaliacaoId): int
    {
        return DB::table('avaliacao_dashboard_pendencias')
            ->where('avaliacao_id', $avaliacaoId)
            ->count()
            + DB::table('avaliacao_dashboard_escopo_pendencias')
                ->where('avaliacao_id', $avaliacaoId)
                ->count();
    }

    /** @return array{registros_inseridos: int, registros_atualizados: int, registros_removidos: int} */
    private function emptyResult(): array
    {
        return ['registros_inseridos' => 0, 'registros_atualizados' => 0, 'registros_removidos' => 0];
    }

    /**
     * @param  array{registros_inseridos: int, registros_atualizados: int, registros_removidos: int}  $left
     * @param  array{registros_inseridos: int, registros_atualizados: int, registros_removidos: int}  $right
     * @return array{registros_inseridos: int, registros_atualizados: int, registros_removidos: int}
     */
    private function mergeResult(array $left, array $right): array
    {
        return [
            'registros_inseridos' => $left['registros_inseridos'] + $right['registros_inseridos'],
            'registros_atualizados' => $left['registros_atualizados'] + $right['registros_atualizados'],
            'registros_removidos' => $left['registros_removidos'] + $right['registros_removidos'],
        ];
    }
}
