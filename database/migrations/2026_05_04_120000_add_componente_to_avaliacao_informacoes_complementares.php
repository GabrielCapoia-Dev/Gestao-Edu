<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avaliacao_informacoes_complementares', function (Blueprint $table): void {
            $table->foreignId('componente_curricular_id')
                ->nullable()
                ->after('aluno_id');

            $table->foreign('componente_curricular_id', 'aic_comp_curricular_fk')
                ->references('id')
                ->on('componentes_curriculares')
                ->nullOnDelete();

            $table->dropUnique('uniq_avaliacao_turma_aluno_complemento');
            $table->unique(
                ['avaliacao_id', 'turma_id', 'aluno_id', 'componente_curricular_id'],
                'uniq_avaliacao_turma_aluno_componente_complemento'
            );
        });
    }

    public function down(): void
    {
        Schema::table('avaliacao_informacoes_complementares', function (Blueprint $table): void {
            $table->dropUnique('uniq_avaliacao_turma_aluno_componente_complemento');
            $table->dropForeign('aic_comp_curricular_fk');
            $table->dropColumn('componente_curricular_id');
            $table->unique(['avaliacao_id', 'turma_id', 'aluno_id'], 'uniq_avaliacao_turma_aluno_complemento');
        });
    }
};
