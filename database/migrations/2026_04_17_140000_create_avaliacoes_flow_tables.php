<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avaliacoes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->string('status')->default('ativa');
            $table->timestamps();

            $table->index(['status', 'data_inicio', 'data_fim'], 'idx_avaliacoes_status_periodo');
        });

        Schema::create('pautas', function (Blueprint $table) {
            $table->id();
            $table->text('texto');
            $table->foreignId('componente_curricular_id')
                ->nullable()
                ->constrained('componentes_curriculares')
                ->nullOnDelete();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('alternativas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('observacao')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('alternativa_pauta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pauta_id')->constrained('pautas')->cascadeOnDelete();
            $table->foreignId('alternativa_id')->constrained('alternativas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['pauta_id', 'alternativa_id'], 'uniq_alternativa_pauta');
        });

        Schema::create('avaliacao_pauta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('pauta_id')->constrained('pautas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['avaliacao_id', 'pauta_id'], 'uniq_avaliacao_pauta');
        });

        Schema::create('avaliacao_turma', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['avaliacao_id', 'turma_id'], 'uniq_avaliacao_turma');
        });

        Schema::create('avaliacao_respostas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('pauta_id')->constrained('pautas')->cascadeOnDelete();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->foreignId('professor_id')
                ->nullable()
                ->constrained('professores')
                ->nullOnDelete();
            $table->foreignId('alternativa_id')
                ->nullable()
                ->constrained('alternativas')
                ->nullOnDelete();
            $table->text('observacao')->nullable();
            $table->timestamp('respondido_em')->nullable();
            $table->timestamps();

            $table->unique(
                ['avaliacao_id', 'pauta_id', 'turma_id', 'aluno_id'],
                'uniq_resposta_avaliacao_pauta_turma_aluno'
            );
            $table->index(['turma_id', 'pauta_id'], 'idx_respostas_turma_pauta');
            $table->index(['professor_id', 'avaliacao_id'], 'idx_respostas_professor_avaliacao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacao_respostas');
        Schema::dropIfExists('avaliacao_turma');
        Schema::dropIfExists('avaliacao_pauta');
        Schema::dropIfExists('alternativa_pauta');
        Schema::dropIfExists('alternativas');
        Schema::dropIfExists('pautas');
        Schema::dropIfExists('avaliacoes');
    }
};
