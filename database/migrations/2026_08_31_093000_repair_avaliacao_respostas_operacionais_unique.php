<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'avaliacao_respostas_operacionais';

    private const UNIQUE = 'uniq_av_resp_ciclo_aluno_pauta';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || Schema::hasIndex(self::TABLE, self::UNIQUE, 'unique')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
DELETE antiga
FROM avaliacao_respostas_operacionais AS antiga
INNER JOIN avaliacao_respostas_operacionais AS atual
    ON atual.ciclo_id = antiga.ciclo_id
    AND atual.aluno_id = antiga.aluno_id
    AND atual.pauta_id = antiga.pauta_id
    AND (
        atual.version > antiga.version
        OR (atual.version = antiga.version AND COALESCE(atual.updated_at, '1000-01-01') > COALESCE(antiga.updated_at, '1000-01-01'))
        OR (atual.version = antiga.version AND COALESCE(atual.updated_at, '1000-01-01') = COALESCE(antiga.updated_at, '1000-01-01') AND atual.id > antiga.id)
    )
SQL);
        } else {
            DB::statement(<<<'SQL'
DELETE FROM avaliacao_respostas_operacionais
WHERE id IN (
    SELECT id
    FROM (
        SELECT
            id,
            ROW_NUMBER() OVER (
                PARTITION BY ciclo_id, aluno_id, pauta_id
                ORDER BY version DESC, updated_at DESC, id DESC
            ) AS ordem
        FROM avaliacao_respostas_operacionais
    ) AS duplicadas
    WHERE ordem > 1
)
SQL);
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->unique(['ciclo_id', 'aluno_id', 'pauta_id'], self::UNIQUE);
        });
    }

    public function down(): void
    {
        // Reparo permanente de integridade; não recria duplicidades no rollback.
    }
};
