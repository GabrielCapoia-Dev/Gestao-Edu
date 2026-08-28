<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avaliacao_migracao_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->string('chave', 120)->unique();
            $table->unsignedBigInteger('ultimo_documento_id')->default(0);
            $table->unsignedBigInteger('processados')->default(0);
            $table->unsignedBigInteger('migrados')->default(0);
            $table->unsignedBigInteger('inconsistentes')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('avaliacao_migracao_inconsistencias', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('documento_id')->nullable();
            $table->foreignId('avaliacao_id')->nullable()->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('aluno_id')->nullable()->constrained('alunos')->nullOnDelete();
            $table->string('codigo', 80);
            $table->text('mensagem');
            $table->json('contexto')->nullable();
            $table->timestamp('resolvida_em')->nullable();
            $table->foreignId('resolvida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['documento_id', 'codigo'], 'uniq_av_migracao_doc_codigo');
            $table->index(['avaliacao_id', 'codigo', 'resolvida_em'], 'idx_av_migracao_av_codigo_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacao_migracao_inconsistencias');
        Schema::dropIfExists('avaliacao_migracao_checkpoints');
    }
};
