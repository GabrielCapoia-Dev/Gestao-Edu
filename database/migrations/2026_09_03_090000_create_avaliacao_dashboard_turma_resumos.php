<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avaliacao_dashboard_turma_resumos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('avaliacao_id');
            $table->unsignedBigInteger('turma_id');
            $table->unsignedBigInteger('componente_curricular_id')->nullable();
            $table->unsignedBigInteger('componente_chave')->default(0);
            $table->unsignedInteger('preenchimentos_esperados')->default(0);
            $table->unsignedInteger('preenchimentos_respondidos')->default(0);
            $table->unsignedInteger('alunos_total')->default(0);
            $table->unsignedInteger('alunos_pendentes')->default(0);
            $table->unsignedInteger('pautas_total')->default(0);
            $table->timestamp('ultima_resposta_em')->nullable();
            $table->timestamp('calculado_em')->nullable();
            $table->timestamps();

            $table->unique(['avaliacao_id', 'turma_id', 'componente_chave'], 'uniq_av_dashboard_turma_componente');
            $table->index(['avaliacao_id', 'turma_id'], 'idx_av_dashboard_turma_av_turma');
            $table->index(['avaliacao_id', 'componente_chave'], 'idx_av_dashboard_turma_av_comp');
            $table->foreign('avaliacao_id', 'fk_av_dash_res_av')->references('id')->on('avaliacoes')->cascadeOnDelete();
            $table->foreign('turma_id', 'fk_av_dash_res_turma')->references('id')->on('turmas')->cascadeOnDelete();
            $table->foreign('componente_curricular_id', 'fk_av_dash_res_comp')->references('id')->on('componentes_curriculares')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacao_dashboard_turma_resumos');
    }
};
