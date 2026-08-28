<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createIfMissing('avaliacao_turma_ciclos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('turma_avaliativa_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('turma_origem_id')->constrained('turmas')->restrictOnDelete();
            $table->string('status', 16)->default('aberta');
            $table->string('roster_mode', 16)->default('dinamico');
            $table->unsignedInteger('versao_conclusao')->default(0);
            $table->uuid('snapshot_evento_atual_id')->nullable();
            $table->timestamp('concluida_em')->nullable();
            $table->foreignId('concluida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->json('concluida_por_snapshot')->nullable();
            $table->timestamp('reaberta_em')->nullable();
            $table->foreignId('reaberta_por')->nullable()->constrained('users')->nullOnDelete();
            $table->json('reaberta_por_snapshot')->nullable();
            $table->text('motivo_reabertura')->nullable();
            $table->timestamps();

            $table->unique(['avaliacao_id', 'turma_avaliativa_id'], 'uniq_av_turma_ciclo');
            $table->index(['status', 'avaliacao_id'], 'idx_av_ciclo_status_av');
            $table->index(['turma_avaliativa_id', 'status'], 'idx_av_ciclo_turma_status');
            $table->index('snapshot_evento_atual_id', 'idx_av_ciclo_snapshot_atual');
        });

        $this->createIfMissing('avaliacao_turma_tokens_escrita', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ciclo_id')->constrained('avaliacao_turma_ciclos')->cascadeOnDelete();
            $table->uuid('generation_uuid')->unique();
            $table->timestamps();

            $table->unique('ciclo_id', 'uniq_av_token_ciclo');
        });

        $this->createIfMissing('avaliacao_respostas_operacionais', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('token_escrita_id')->constrained('avaliacao_turma_tokens_escrita')->restrictOnDelete();
            $table->foreignId('ciclo_id')->constrained('avaliacao_turma_ciclos')->cascadeOnDelete();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('turma_avaliativa_id')->constrained('turmas')->restrictOnDelete();
            $table->foreignId('turma_origem_id')->constrained('turmas')->restrictOnDelete();
            $table->foreignId('aluno_id')->constrained('alunos')->restrictOnDelete();
            $table->foreignId('pauta_id')->constrained('pautas')->restrictOnDelete();
            $table->foreignId('componente_curricular_id')->nullable()->constrained('componentes_curriculares')->nullOnDelete();
            $table->foreignId('professor_id')->nullable()->constrained('professores')->nullOnDelete();
            $table->foreignId('alternativa_id')->nullable()->constrained('alternativas')->nullOnDelete();
            $table->text('observacao')->nullable();
            $table->timestamp('respondido_em')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['ciclo_id', 'aluno_id', 'pauta_id'], 'uniq_av_resp_ciclo_aluno_pauta');
            $table->index(['ciclo_id', 'pauta_id', 'alternativa_id'], 'idx_av_resp_ciclo_pauta_alt');
            $table->index(['ciclo_id', 'componente_curricular_id', 'professor_id'], 'idx_av_resp_ciclo_comp_prof');
            $table->index(['avaliacao_id', 'aluno_id'], 'idx_av_resp_av_aluno');
        });

        $this->createIfMissing('avaliacao_informacoes_operacionais', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('token_escrita_id')->constrained('avaliacao_turma_tokens_escrita')->restrictOnDelete();
            $table->foreignId('ciclo_id')->constrained('avaliacao_turma_ciclos')->cascadeOnDelete();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('turma_avaliativa_id')->constrained('turmas')->restrictOnDelete();
            $table->foreignId('turma_origem_id')->constrained('turmas')->restrictOnDelete();
            $table->foreignId('aluno_id')->constrained('alunos')->restrictOnDelete();
            $table->foreignId('componente_curricular_id')->nullable()->constrained('componentes_curriculares')->nullOnDelete();
            $table->unsignedBigInteger('componente_chave')->default(0);
            $table->foreignId('professor_id')->nullable()->constrained('professores')->nullOnDelete();
            $table->text('texto')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['ciclo_id', 'aluno_id', 'componente_chave'], 'uniq_av_info_ciclo_aluno_comp');
            $table->index(['avaliacao_id', 'aluno_id'], 'idx_av_info_av_aluno');
        });

        $this->createIfMissing('avaliacao_snapshot_eventos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('idempotency_key', 120)->unique();
            $table->string('tipo', 24);
            $table->foreignId('ciclo_id')->nullable()->constrained('avaliacao_turma_ciclos')->nullOnDelete();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('turma_avaliativa_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->foreignId('turma_origem_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->foreignId('turma_destino_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->unsignedInteger('versao')->default(1);
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->json('criado_por_snapshot')->nullable();
            $table->text('motivo')->nullable();
            $table->unsignedInteger('total_alunos')->default(0);
            $table->unsignedInteger('total_respostas_esperadas')->default(0);
            $table->unsignedInteger('total_respostas_geradas')->default(0);
            $table->unsignedBigInteger('tamanho_bytes')->default(0);
            $table->char('payload_hash_agregado', 64);
            $table->timestamp('publicado_em')->nullable();
            $table->timestamps();

            $table->unique(['ciclo_id', 'versao', 'tipo'], 'uniq_av_snapshot_ciclo_versao_tipo');
            $table->index(['avaliacao_id', 'tipo', 'publicado_em'], 'idx_av_snapshot_av_tipo_data');
        });

        $this->createIfMissing('avaliacao_aluno_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('evento_id');
            $table->foreign('evento_id')->references('id')->on('avaliacao_snapshot_eventos')->cascadeOnDelete();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('ciclo_id')->nullable()->constrained('avaliacao_turma_ciclos')->nullOnDelete();
            $table->foreignId('turma_avaliativa_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->foreignId('turma_origem_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->foreignId('turma_destino_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->foreignId('aluno_id')->nullable()->constrained('alunos')->nullOnDelete();
            $table->string('cgm')->nullable();
            $table->string('tipo', 24);
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->json('payload');
            $table->char('payload_hash', 64);
            $table->unsignedInteger('total_pautas_esperadas')->default(0);
            $table->unsignedInteger('total_respostas')->default(0);
            $table->unsignedInteger('total_informacoes')->default(0);
            $table->unsignedBigInteger('tamanho_bytes')->default(0);
            $table->timestamps();

            $table->unique(['evento_id', 'aluno_id'], 'uniq_av_aluno_snapshot_evento_aluno');
            $table->index(['avaliacao_id', 'turma_avaliativa_id', 'tipo'], 'idx_av_aluno_snapshot_contexto');
            $table->index(['cgm', 'tipo'], 'idx_av_aluno_snapshot_cgm_tipo');
        });

        $this->createIfMissing('avaliacao_snapshot_resumos_componentes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('evento_id');
            $table->foreign('evento_id')->references('id')->on('avaliacao_snapshot_eventos')->cascadeOnDelete();
            $table->foreignId('ciclo_id')->constrained('avaliacao_turma_ciclos')->cascadeOnDelete();
            $table->foreignId('componente_curricular_id')->nullable()->constrained('componentes_curriculares')->nullOnDelete();
            $table->unsignedInteger('respostas_esperadas')->default(0);
            $table->unsignedInteger('respostas_concluidas')->default(0);
            $table->unsignedInteger('respostas_pendentes')->default(0);
            $table->decimal('percentual', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['evento_id', 'componente_curricular_id'], 'uniq_av_snapshot_resumo_evento_comp');
            $table->index(['ciclo_id', 'componente_curricular_id'], 'idx_av_snapshot_resumo_ciclo_comp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacao_snapshot_resumos_componentes');
        Schema::dropIfExists('avaliacao_aluno_snapshots');
        Schema::dropIfExists('avaliacao_snapshot_eventos');
        Schema::dropIfExists('avaliacao_informacoes_operacionais');
        Schema::dropIfExists('avaliacao_respostas_operacionais');
        Schema::dropIfExists('avaliacao_turma_tokens_escrita');
        Schema::dropIfExists('avaliacao_turma_ciclos');
    }

    private function createIfMissing(string $tableName, Closure $callback): void
    {
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, $callback);
        }
    }
};
