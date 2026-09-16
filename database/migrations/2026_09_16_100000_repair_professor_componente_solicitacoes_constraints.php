<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'professor_componente_solicitacoes';

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $foreignKeys = collect(Schema::getForeignKeys($tableName))->pluck('name')->all();

        Schema::table($tableName, function (Blueprint $table) use ($foreignKeys): void {
            if (! in_array('prof_comp_sol_vinculo_fk', $foreignKeys, true)) {
                $table->foreign('turma_componente_professor_id', 'prof_comp_sol_vinculo_fk')
                    ->references('id')->on('turma_componente_professor')->cascadeOnDelete();
            }

            if (! in_array('prof_comp_sol_professor_fk', $foreignKeys, true)) {
                $table->foreign('professor_id', 'prof_comp_sol_professor_fk')
                    ->references('id')->on('professores')->cascadeOnDelete();
            }

            if (! in_array('prof_comp_sol_solicitante_fk', $foreignKeys, true)) {
                $table->foreign('solicitado_por_id', 'prof_comp_sol_solicitante_fk')
                    ->references('id')->on('users')->cascadeOnDelete();
            }

            if (! in_array('prof_comp_sol_analista_fk', $foreignKeys, true)) {
                $table->foreign('analisado_por_id', 'prof_comp_sol_analista_fk')
                    ->references('id')->on('users')->nullOnDelete();
            }
        });

        if (! Schema::hasIndex($tableName, 'professor_componente_solicitacoes_status_index')) {
            Schema::table($tableName, fn (Blueprint $table) => $table->index('status'));
        }

        if (! Schema::hasIndex($tableName, 'prof_comp_solicitacao_unica')) {
            Schema::table($tableName, fn (Blueprint $table) => $table->unique(
                ['turma_componente_professor_id', 'professor_id'],
                'prof_comp_solicitacao_unica',
            ));
        }
    }

    public function down(): void
    {
        // Correção não destrutiva: vínculos e solicitações devem permanecer intactos.
    }
};
