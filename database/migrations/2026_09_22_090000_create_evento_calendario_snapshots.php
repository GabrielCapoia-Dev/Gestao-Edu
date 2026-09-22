<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('eventos_calendario', 'participantes_snapshot_em')) {
            Schema::table('eventos_calendario', function (Blueprint $table): void {
                $table->timestamp('participantes_snapshot_em')->nullable()->after('ultima_importacao_id');
            });
        }

        if (! Schema::hasColumn('eventos_calendario', 'alunos_snapshot_em')) {
            Schema::table('eventos_calendario', function (Blueprint $table): void {
                $table->timestamp('alunos_snapshot_em')->nullable()->after('participantes_snapshot_em');
            });
        }

        if (! Schema::hasTable('evento_calendario_participantes_snapshot')) {
            Schema::create('evento_calendario_participantes_snapshot', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evento_calendario_id')->constrained('eventos_calendario')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('escola_id')->nullable()->constrained('escolas')->nullOnDelete();
            $table->json('escola_ids')->nullable();
            $table->string('nome');
            $table->string('email')->nullable();
            $table->string('escola_nome')->nullable();
            $table->string('cargo_nome')->nullable();
            $table->timestamps();

            $table->unique(['evento_calendario_id', 'user_id'], 'uq_evento_participante_snapshot');
            $table->index(['evento_calendario_id', 'escola_id'], 'idx_evento_participante_escola');
            });
        }

        if (! Schema::hasTable('evento_calendario_alunos_snapshot')) {
            Schema::create('evento_calendario_alunos_snapshot', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evento_calendario_id')->constrained('eventos_calendario')->cascadeOnDelete();
            $table->foreignId('evento_calendario_escola_id')->constrained('evento_calendario_escolas')->cascadeOnDelete();
            $table->foreignId('aluno_id')->nullable()->constrained('alunos')->nullOnDelete();
            $table->foreignId('escola_id')->nullable()->constrained('escolas')->nullOnDelete();
            $table->foreignId('serie_id')->nullable()->constrained('series')->nullOnDelete();
            $table->foreignId('turma_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->string('aluno_nome');
            $table->string('cgm')->nullable();
            $table->string('escola_nome');
            $table->string('serie_nome')->nullable();
            $table->string('turma_nome')->nullable();
            $table->string('turno', 30)->nullable();
            $table->timestamps();

            $table->index(['evento_calendario_id', 'escola_id'], 'idx_evento_aluno_escola');
            $table->index(['evento_calendario_id', 'turma_id'], 'idx_evento_aluno_turma');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_calendario_alunos_snapshot');
        Schema::dropIfExists('evento_calendario_participantes_snapshot');

        if (Schema::hasColumn('eventos_calendario', 'participantes_snapshot_em')) {
            Schema::table('eventos_calendario', function (Blueprint $table): void {
                $table->dropColumn('participantes_snapshot_em');
            });
        }

        if (Schema::hasColumn('eventos_calendario', 'alunos_snapshot_em')) {
            Schema::table('eventos_calendario', function (Blueprint $table): void {
                $table->dropColumn('alunos_snapshot_em');
            });
        }
    }
};
