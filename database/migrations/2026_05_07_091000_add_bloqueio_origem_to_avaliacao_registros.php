<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avaliacao_respostas', function (Blueprint $table): void {
            if (! Schema::hasColumn('avaliacao_respostas', 'bloqueada')) {
                $table->boolean('bloqueada')->default(false)->after('respondido_em');
            }

            if (! Schema::hasColumn('avaliacao_respostas', 'resposta_origem_id')) {
                $table->foreignId('resposta_origem_id')
                    ->nullable()
                    ->after('bloqueada')
                    ->constrained('avaliacao_respostas')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('avaliacao_respostas', 'aluno_origem_id')) {
                $table->foreignId('aluno_origem_id')
                    ->nullable()
                    ->after('resposta_origem_id')
                    ->constrained('alunos')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('avaliacao_respostas', 'turma_origem_id')) {
                $table->foreignId('turma_origem_id')
                    ->nullable()
                    ->after('aluno_origem_id')
                    ->constrained('turmas')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('avaliacao_respostas', 'bloqueio_tipo')) {
                $table->string('bloqueio_tipo', 32)->nullable()->after('turma_origem_id');
            }
        });

        if (! Schema::hasIndex('avaliacao_respostas', 'idx_avresp_bloqueio_origem')) {
            Schema::table('avaliacao_respostas', function (Blueprint $table): void {
                $table->index(['bloqueada', 'aluno_origem_id'], 'idx_avresp_bloqueio_origem');
            });
        }

        Schema::table('avaliacao_informacoes_complementares', function (Blueprint $table): void {
            if (! Schema::hasColumn('avaliacao_informacoes_complementares', 'bloqueada')) {
                $table->boolean('bloqueada')->default(false)->after('informacoes_complementares');
            }

            if (! Schema::hasColumn('avaliacao_informacoes_complementares', 'informacao_origem_id')) {
                $table->foreignId('informacao_origem_id')
                    ->nullable()
                    ->after('bloqueada')
                    ->constrained('avaliacao_informacoes_complementares')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('avaliacao_informacoes_complementares', 'aluno_origem_id')) {
                $table->foreignId('aluno_origem_id')
                    ->nullable()
                    ->after('informacao_origem_id')
                    ->constrained('alunos')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('avaliacao_informacoes_complementares', 'turma_origem_id')) {
                $table->foreignId('turma_origem_id')
                    ->nullable()
                    ->after('aluno_origem_id')
                    ->constrained('turmas')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('avaliacao_informacoes_complementares', 'bloqueio_tipo')) {
                $table->string('bloqueio_tipo', 32)->nullable()->after('turma_origem_id');
            }
        });

        if (! Schema::hasIndex('avaliacao_informacoes_complementares', 'idx_avic_bloqueio_origem')) {
            Schema::table('avaliacao_informacoes_complementares', function (Blueprint $table): void {
                $table->index(['bloqueada', 'aluno_origem_id'], 'idx_avic_bloqueio_origem');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('avaliacao_respostas', 'idx_avresp_bloqueio_origem')) {
            Schema::table('avaliacao_respostas', function (Blueprint $table): void {
                $table->dropIndex('idx_avresp_bloqueio_origem');
            });
        }

        Schema::table('avaliacao_respostas', function (Blueprint $table): void {
            foreach (['turma_origem_id', 'aluno_origem_id', 'resposta_origem_id'] as $column) {
                if (Schema::hasColumn('avaliacao_respostas', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['bloqueio_tipo', 'bloqueada'] as $column) {
                if (Schema::hasColumn('avaliacao_respostas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasIndex('avaliacao_informacoes_complementares', 'idx_avic_bloqueio_origem')) {
            Schema::table('avaliacao_informacoes_complementares', function (Blueprint $table): void {
                $table->dropIndex('idx_avic_bloqueio_origem');
            });
        }

        Schema::table('avaliacao_informacoes_complementares', function (Blueprint $table): void {
            foreach (['turma_origem_id', 'aluno_origem_id', 'informacao_origem_id'] as $column) {
                if (Schema::hasColumn('avaliacao_informacoes_complementares', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['bloqueio_tipo', 'bloqueada'] as $column) {
                if (Schema::hasColumn('avaliacao_informacoes_complementares', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
