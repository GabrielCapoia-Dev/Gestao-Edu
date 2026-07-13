<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('avaliacao_aluno_documentos')) {
            Schema::create('avaliacao_aluno_documentos', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
                $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
                $table->string('cgm')->nullable();
                $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
                $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
                $table->foreignId('serie_id')->nullable()->constrained('series')->nullOnDelete();
                $table->json('payload');
                $table->json('alternativa_ids')->nullable();
                $table->json('professor_ids')->nullable();
                $table->json('pauta_ids_respondidas')->nullable();
                $table->unsignedInteger('total_pautas_esperadas')->default(0);
                $table->unsignedInteger('total_pautas_respondidas')->default(0);
                $table->unsignedInteger('total_infos_complementares')->default(0);
                $table->string('status_preenchimento', 16)->default('vazio');
                $table->unsignedInteger('observacoes_obrigatorias_pendentes')->default(0);
                $table->timestamp('primeira_resposta_em')->nullable();
                $table->timestamp('ultima_resposta_em')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();

                $table->unique(['avaliacao_id', 'aluno_id'], 'uniq_av_aluno_doc_avaliacao_aluno');
                $table->index(['avaliacao_id', 'turma_id'], 'idx_av_aluno_doc_avaliacao_turma');
                $table->index(['avaliacao_id', 'escola_id'], 'idx_av_aluno_doc_avaliacao_escola');
                $table->index(['avaliacao_id', 'status_preenchimento'], 'idx_av_aluno_doc_status');
                $table->index('cgm', 'idx_av_aluno_doc_cgm');
            });
        }

        if (! Schema::hasTable('avaliacao_aluno_documentos_historico')) {
            Schema::create('avaliacao_aluno_documentos_historico', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
                $table->unsignedBigInteger('documento_id')->nullable();
                $table->foreignId('aluno_origem_id')->constrained('alunos')->cascadeOnDelete();
                $table->unsignedBigInteger('aluno_destino_id')->nullable();
                $table->string('cgm')->nullable();
                $table->unsignedBigInteger('turma_id')->nullable();
                $table->unsignedBigInteger('escola_id')->nullable();
                $table->unsignedBigInteger('serie_id')->nullable();
                $table->string('movimentacao_tipo', 32);
                $table->json('payload');
                $table->json('alternativa_ids')->nullable();
                $table->unsignedInteger('total_pautas_esperadas')->default(0);
                $table->unsignedInteger('total_pautas_respondidas')->default(0);
                $table->unsignedInteger('total_infos_complementares')->default(0);
                $table->string('status_preenchimento', 16)->default('vazio');
                $table->timestamp('movimentado_em');
                $table->foreignId('movimentado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['avaliacao_id', 'aluno_origem_id'], 'idx_av_doc_hist_avaliacao_origem');
                $table->index(['movimentacao_tipo', 'movimentado_em'], 'idx_av_doc_hist_tipo_data');
                $table->index('cgm', 'idx_av_doc_hist_cgm');
                $table->index('documento_id', 'idx_av_doc_hist_documento');
                $table->index('aluno_destino_id', 'idx_av_doc_hist_destino');
            });
        }

        if (! Schema::hasTable('avaliacao_resposta_fatos')) {
            Schema::create('avaliacao_resposta_fatos', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('documento_id')
                    ->constrained('avaliacao_aluno_documentos')
                    ->cascadeOnDelete();
                $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
                $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
                $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
                $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
                $table->foreignId('pauta_id')->constrained('pautas')->cascadeOnDelete();
                $table->unsignedBigInteger('componente_curricular_id')->nullable();
                $table->unsignedBigInteger('alternativa_id')->nullable();
                $table->unsignedBigInteger('professor_id')->nullable();
                $table->boolean('tem_observacao')->default(false);
                $table->text('observacao')->nullable();
                $table->timestamp('respondido_em')->nullable();
                $table->timestamps();

                $table->unique(['documento_id', 'pauta_id'], 'uniq_av_fato_documento_pauta');
                $table->index(['avaliacao_id', 'turma_id', 'pauta_id'], 'idx_av_fato_avaliacao_turma_pauta');
                $table->index(['avaliacao_id', 'alternativa_id'], 'idx_av_fato_avaliacao_alternativa');
                $table->index(['avaliacao_id', 'professor_id'], 'idx_av_fato_avaliacao_professor');
                $table->index(['avaliacao_id', 'aluno_id'], 'idx_av_fato_avaliacao_aluno');
                $table->index(['avaliacao_id', 'escola_id'], 'idx_av_fato_avaliacao_escola');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacao_resposta_fatos');
        Schema::dropIfExists('avaliacao_aluno_documentos_historico');
        Schema::dropIfExists('avaliacao_aluno_documentos');
    }
};
