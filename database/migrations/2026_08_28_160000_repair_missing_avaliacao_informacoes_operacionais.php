<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('avaliacao_informacoes_operacionais')) {
            return;
        }

        Schema::create('avaliacao_informacoes_operacionais', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('token_escrita_id')
                ->constrained('avaliacao_turma_tokens_escrita')
                ->restrictOnDelete();
            $table->foreignId('ciclo_id')
                ->constrained('avaliacao_turma_ciclos')
                ->cascadeOnDelete();
            $table->foreignId('avaliacao_id')
                ->constrained('avaliacoes')
                ->cascadeOnDelete();
            $table->foreignId('turma_avaliativa_id')
                ->constrained('turmas')
                ->restrictOnDelete();
            $table->foreignId('turma_origem_id')
                ->constrained('turmas')
                ->restrictOnDelete();
            $table->foreignId('aluno_id')
                ->constrained('alunos')
                ->restrictOnDelete();
            $table->foreignId('componente_curricular_id')
                ->nullable()
                ->constrained('componentes_curriculares')
                ->nullOnDelete();
            $table->unsignedBigInteger('componente_chave')->default(0);
            $table->foreignId('professor_id')
                ->nullable()
                ->constrained('professores')
                ->nullOnDelete();
            $table->text('texto')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(
                ['ciclo_id', 'aluno_id', 'componente_chave'],
                'uniq_av_info_ciclo_aluno_comp',
            );
            $table->index(
                ['avaliacao_id', 'aluno_id'],
                'idx_av_info_av_aluno',
            );
        });
    }

    public function down(): void
    {
        // Migration de reparo de schema.
        // Não remove a tabela no rollback porque, em instalações saudáveis,
        // ela pertence à migration original 2026_08_28_090000.
    }
};
