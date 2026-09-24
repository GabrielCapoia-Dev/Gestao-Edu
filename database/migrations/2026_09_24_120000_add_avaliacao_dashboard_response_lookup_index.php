<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'avaliacao_respostas_operacionais';

    private const INDEX = 'idx_av_resp_dashboard_lookup';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || Schema::hasIndex(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index(
                ['avaliacao_id', 'turma_avaliativa_id', 'componente_curricular_id', 'pauta_id', 'aluno_id'],
                self::INDEX,
            );
        });
    }

    public function down(): void
    {
        if (Schema::hasTable(self::TABLE) && Schema::hasIndex(self::TABLE, self::INDEX)) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropIndex(self::INDEX));
        }
    }
};
