<?php

use App\Support\Migrations\AvaliacaoDocumentosMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Converte avaliacao_respostas + infos complementares → documentos + fatos.
 * Idempotente. Seguro para deploy por FTP + migrate no entrypoint.
 *
 * Produção (última versão ~06/07/2026) ainda tem as tabelas legadas:
 * esta migration roda no próximo up e materializa o modelo novo sem dropar o legado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable(AvaliacaoDocumentosMigrator::DOCUMENTOS)) {
            return;
        }

        $migrator = new AvaliacaoDocumentosMigrator();
        $migrator->migrateFromLegacy();
    }

    public function down(): void
    {
        // Não recria o legado automaticamente (perda irreversível de formato).
        // Apenas limpa o modelo novo se o deploy for revertido com as migrations.
        if (Schema::hasTable(AvaliacaoDocumentosMigrator::FATOS)) {
            DB::table(AvaliacaoDocumentosMigrator::FATOS)->delete();
        }

        if (Schema::hasTable(AvaliacaoDocumentosMigrator::HISTORICO)) {
            DB::table(AvaliacaoDocumentosMigrator::HISTORICO)->delete();
        }

        if (Schema::hasTable(AvaliacaoDocumentosMigrator::DOCUMENTOS)) {
            DB::table(AvaliacaoDocumentosMigrator::DOCUMENTOS)->delete();
        }
    }
};
