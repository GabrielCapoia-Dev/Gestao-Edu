<?php

use App\Support\Migrations\AvaliacaoDocumentosMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Converte avaliacao_respostas + infos complementares → documentos (payload).
 * Idempotente e em lote. Seguro para deploy por FTP + migrate no entrypoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable(AvaliacaoDocumentosMigrator::DOCUMENTOS)) {
            return;
        }

        $started = microtime(true);
        $migrator = new AvaliacaoDocumentosMigrator();
        $stats = $migrator->migrateFromLegacy();

        Log::info('avaliacao_documentos.migration_000002', [
            ...$stats,
            'wall_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
    }

    public function down(): void
    {
        // Não recria o legado automaticamente.
        if (Schema::hasTable(AvaliacaoDocumentosMigrator::HISTORICO)) {
            // no-op: dados de histórico preservados no rollback de código
        }
    }
};
