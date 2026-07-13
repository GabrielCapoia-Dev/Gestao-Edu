<?php

use App\Support\Migrations\AvaliacaoDocumentosMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Finaliza o cutover:
 * 1) reprocessa residual do legado (se ainda existir)
 * 2) valida consistência
 * 3) remove tabelas legadas
 *
 * Se a validação falhar, a migration lança exceção e o entrypoint (set -e)
 * impede o container de subir com banco/código inconsistentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $migrator = new AvaliacaoDocumentosMigrator();

        if (! Schema::hasTable(AvaliacaoDocumentosMigrator::DOCUMENTOS)) {
            throw new \RuntimeException(
                'Cutover de avaliacoes abortado: '.AvaliacaoDocumentosMigrator::DOCUMENTOS.' ausente.'
            );
        }

        if (! Schema::hasTable(AvaliacaoDocumentosMigrator::FATOS)) {
            throw new \RuntimeException(
                'Cutover de avaliacoes abortado: '.AvaliacaoDocumentosMigrator::FATOS.' ausente.'
            );
        }

        // Residual: se 000002 rodou parcial ou legado ainda tem dados, reprocessa.
        if ($migrator->hasLegacyTables()) {
            $stats = $migrator->migrateFromLegacy();
            Log::info('avaliacao_documentos.cutover_pre_drop', $stats);
            $migrator->assertMigracaoConsistente();
            $migrator->dropLegacyTables();
            Log::info('avaliacao_documentos.legacy_dropped', $migrator->stats());
        }

        // Garante fatos recalculados mesmo sem legado (ambientes que já droparam).
        if ((int) DB::table(AvaliacaoDocumentosMigrator::DOCUMENTOS)->count() > 0
            && (int) DB::table(AvaliacaoDocumentosMigrator::FATOS)->count() === 0
        ) {
            $migrator->migrateFromLegacy();
            $migrator->assertMigracaoConsistente();
        }
    }

    public function down(): void
    {
        // Recria estrutura legada mínima para rollback de código antigo.
        // Dados de resposta NÃO são reconstruídos a partir dos documentos aqui
        // (seria perda semântica). O restore operacional deve vir de backup.

        if (! Schema::hasTable('avaliacao_respostas')) {
            Schema::create('avaliacao_respostas', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
                $table->foreignId('pauta_id')->constrained('pautas')->cascadeOnDelete();
                $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
                $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
                $table->foreignId('professor_id')->nullable()->constrained('professores')->nullOnDelete();
                $table->foreignId('alternativa_id')->nullable()->constrained('alternativas')->nullOnDelete();
                $table->text('observacao')->nullable();
                $table->timestamp('respondido_em')->nullable();
                $table->boolean('bloqueada')->default(false);
                $table->unsignedBigInteger('resposta_origem_id')->nullable();
                $table->unsignedBigInteger('aluno_origem_id')->nullable();
                $table->unsignedBigInteger('turma_origem_id')->nullable();
                $table->string('bloqueio_tipo', 32)->nullable();
                $table->timestamps();

                $table->unique(
                    ['avaliacao_id', 'pauta_id', 'turma_id', 'aluno_id'],
                    'uniq_resposta_avaliacao_pauta_turma_aluno'
                );
            });
        }

        if (! Schema::hasTable('avaliacao_informacoes_complementares')) {
            Schema::create('avaliacao_informacoes_complementares', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
                $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
                $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
                $table->foreignId('componente_curricular_id')->nullable()->constrained('componentes_curriculares')->nullOnDelete();
                $table->foreignId('professor_id')->nullable()->constrained('professores')->nullOnDelete();
                $table->text('informacoes_complementares')->nullable();
                $table->boolean('bloqueada')->default(false);
                $table->unsignedBigInteger('informacao_origem_id')->nullable();
                $table->unsignedBigInteger('aluno_origem_id')->nullable();
                $table->unsignedBigInteger('turma_origem_id')->nullable();
                $table->string('bloqueio_tipo', 32)->nullable();
                $table->timestamps();

                $table->unique(
                    ['avaliacao_id', 'turma_id', 'aluno_id', 'componente_curricular_id'],
                    'uniq_avaliacao_turma_aluno_componente_complemento'
                );
            });
        }
    }
};
