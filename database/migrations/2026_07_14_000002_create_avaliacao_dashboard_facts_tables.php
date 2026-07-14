<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avaliacao_dashboard_fatos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('serie_id')->nullable()->constrained('series')->nullOnDelete();
            $table->foreignId('pauta_id')->constrained('pautas')->cascadeOnDelete();
            $table->foreignId('componente_curricular_id')->nullable()->constrained('componentes_curriculares')->nullOnDelete();
            $table->foreignId('professor_id')->nullable()->constrained('professores')->nullOnDelete();
            $table->foreignId('alternativa_id')->nullable()->constrained('alternativas')->nullOnDelete();
            $table->boolean('respondida')->default(false);
            $table->boolean('observacao_pendente')->default(false);
            $table->string('status_resposta', 24)->default('pendente');
            $table->timestamp('respondida_em')->nullable();
            $table->unsignedInteger('origem_version')->default(1);
            $table->timestamps();

            $table->unique(['avaliacao_id', 'aluno_id', 'pauta_id'], 'uniq_avaliacao_dashboard_fato');
            $table->index(['avaliacao_id', 'escola_id', 'status_resposta'], 'idx_av_dashboard_av_escola_status');
            $table->index(['avaliacao_id', 'turma_id', 'componente_curricular_id'], 'idx_av_dashboard_av_turma_componente');
            $table->index(['avaliacao_id', 'serie_id', 'pauta_id'], 'idx_av_dashboard_av_serie_pauta');
            $table->index(['avaliacao_id', 'professor_id', 'alternativa_id'], 'idx_av_dashboard_av_prof_alt');
        });

        Schema::create('avaliacao_dashboard_consolidacoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avaliacao_id')->unique()->constrained('avaliacoes')->cascadeOnDelete();
            $table->string('status', 24)->default('pendente');
            $table->timestamp('solicitada_em')->nullable();
            $table->timestamp('iniciada_em')->nullable();
            $table->timestamp('consolidada_em')->nullable();
            $table->text('erro')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacao_dashboard_consolidacoes');
        Schema::dropIfExists('avaliacao_dashboard_fatos');
    }
};
