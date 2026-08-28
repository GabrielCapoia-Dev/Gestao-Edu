<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avaliacao_turma_ciclos', function (Blueprint $table): void {
            $table->timestamp('operacional_inicializado_em')->nullable()->after('snapshot_evento_atual_id');
            $table->unsignedInteger('legado_documentos_migrados')->default(0)->after('operacional_inicializado_em');
            $table->char('legado_migracao_hash', 64)->nullable()->after('legado_documentos_migrados');

            $table->index('operacional_inicializado_em', 'idx_av_ciclo_operacional_inicializado');
        });
    }

    public function down(): void
    {
        Schema::table('avaliacao_turma_ciclos', function (Blueprint $table): void {
            $table->dropIndex('idx_av_ciclo_operacional_inicializado');
            $table->dropColumn([
                'operacional_inicializado_em',
                'legado_documentos_migrados',
                'legado_migracao_hash',
            ]);
        });
    }
};
