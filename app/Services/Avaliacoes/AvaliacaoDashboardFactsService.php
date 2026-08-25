<?php

namespace App\Services\Avaliacoes;

/**
 * Fachada de compatibilidade para integrações e jobs serializados antigos.
 *
 * A consolidação persistida do dashboard foi desativada. Os indicadores são
 * calculados sob demanda a partir das tabelas canônicas e nenhum método desta
 * classe pode enfileirar, recalcular ou gravar fatos.
 *
 * @deprecated Mantida temporariamente para consumir chamadas legadas sem efeito.
 */
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
    ): void {}

    public function requestSyncPauta(
        int $avaliacaoId,
        int $pautaId,
        string $motivo = 'pauta_alterada',
    ): void {}

    public function requestSyncTurma(
        int $avaliacaoId,
        int $turmaId,
        string $motivo = 'turma_alterada',
    ): void {}

    public function requestSyncEstruturaAvaliacao(
        int $avaliacaoId,
        string $motivo = 'avaliacao_estrutura_alterada',
    ): void {}

    public function requestRebuild(int $avaliacaoId, string $motivo = 'rebuild_explicito'): bool
    {
        return false;
    }

    public function dispatchPending(?int $avaliacaoId = null, int $limit = 200, bool $force = false): int
    {
        return 0;
    }

    public function dispatchPendingScopes(?int $avaliacaoId = null, int $limit = 100, bool $force = false): int
    {
        return 0;
    }

    public function reconcileFinishedIncrementalStatuses(): int
    {
        return 0;
    }

    public function status(int $avaliacaoId): ?object
    {
        return null;
    }

    public function processPendingDocumento(int $avaliacaoId, int $alunoId, int $tentativa = 1): void {}

    /** @return array{registros_inseridos: int, registros_atualizados: int, registros_removidos: int} */
    public function syncDocumento(int $avaliacaoId, int $alunoId): array
    {
        return $this->emptyResult();
    }

    /** @return array{registros_inseridos: int, registros_atualizados: int, registros_removidos: int} */
    public function syncPauta(int $avaliacaoId, int $pautaId): array
    {
        return $this->emptyResult();
    }

    /** @return array{registros_inseridos: int, registros_atualizados: int, registros_removidos: int} */
    public function syncTurma(int $avaliacaoId, int $turmaId): array
    {
        return $this->emptyResult();
    }

    /** @return array{registros_inseridos: int, registros_atualizados: int, registros_removidos: int} */
    public function syncAvaliacaoCompleta(int $avaliacaoId): array
    {
        return $this->emptyResult();
    }

    public function rebuild(int $avaliacaoId, string $motivo = 'rebuild_explicito', int $tentativa = 1): bool
    {
        return false;
    }

    public function processPendingScope(
        int $avaliacaoId,
        string $scope,
        int $scopeId,
        int $tentativa = 1,
    ): void {}

    public function processScope(
        int $avaliacaoId,
        string $scope,
        ?int $scopeId,
        string $motivo,
        int $tentativa = 1,
        ?int $pendenciaId = null,
        ?int $pendenciaGeracao = null,
    ): void {}

    public function markFailed(int $avaliacaoId, ?string $erro, ?string $expectedStatus = null): void {}

    /** @return array{registros_inseridos: int, registros_atualizados: int, registros_removidos: int} */
    private function emptyResult(): array
    {
        return [
            'registros_inseridos' => 0,
            'registros_atualizados' => 0,
            'registros_removidos' => 0,
        ];
    }
}
