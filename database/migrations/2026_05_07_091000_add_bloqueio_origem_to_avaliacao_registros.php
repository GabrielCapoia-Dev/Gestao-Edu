<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RESPOSTAS = 'avaliacao_respostas';

    private const INFORMACOES = 'avaliacao_informacoes_complementares';

    public function up(): void
    {
        if (Schema::hasTable(self::RESPOSTAS)) {
            Schema::table(self::RESPOSTAS, function (Blueprint $table): void {
                if (! Schema::hasColumn(self::RESPOSTAS, 'bloqueada')) {
                    $table->boolean('bloqueada')->default(false)->after('respondido_em');
                }

                if (! Schema::hasColumn(self::RESPOSTAS, 'resposta_origem_id')) {
                    $table->unsignedBigInteger('resposta_origem_id')
                        ->nullable()
                        ->after('bloqueada');
                }

                if (! Schema::hasColumn(self::RESPOSTAS, 'aluno_origem_id')) {
                    $table->unsignedBigInteger('aluno_origem_id')
                        ->nullable()
                        ->after('resposta_origem_id');
                }

                if (! Schema::hasColumn(self::RESPOSTAS, 'turma_origem_id')) {
                    $table->unsignedBigInteger('turma_origem_id')
                        ->nullable()
                        ->after('aluno_origem_id');
                }

                if (! Schema::hasColumn(self::RESPOSTAS, 'bloqueio_tipo')) {
                    $table->string('bloqueio_tipo', 32)->nullable()->after('turma_origem_id');
                }
            });

            $this->ensureForeign(self::RESPOSTAS, 'resposta_origem_id', self::RESPOSTAS, 'fk_avresp_resp_orig');
            $this->ensureForeign(self::RESPOSTAS, 'aluno_origem_id', 'alunos', 'fk_avresp_aluno_orig');
            $this->ensureForeign(self::RESPOSTAS, 'turma_origem_id', 'turmas', 'fk_avresp_turma_orig');

            if (! Schema::hasIndex(self::RESPOSTAS, 'idx_avresp_bloqueio_origem')) {
                Schema::table(self::RESPOSTAS, function (Blueprint $table): void {
                    $table->index(['bloqueada', 'aluno_origem_id'], 'idx_avresp_bloqueio_origem');
                });
            }
        }

        if (Schema::hasTable(self::INFORMACOES)) {
            Schema::table(self::INFORMACOES, function (Blueprint $table): void {
                if (! Schema::hasColumn(self::INFORMACOES, 'bloqueada')) {
                    $table->boolean('bloqueada')->default(false)->after('informacoes_complementares');
                }

                if (! Schema::hasColumn(self::INFORMACOES, 'informacao_origem_id')) {
                    $table->unsignedBigInteger('informacao_origem_id')
                        ->nullable()
                        ->after('bloqueada');
                }

                if (! Schema::hasColumn(self::INFORMACOES, 'aluno_origem_id')) {
                    $table->unsignedBigInteger('aluno_origem_id')
                        ->nullable()
                        ->after('informacao_origem_id');
                }

                if (! Schema::hasColumn(self::INFORMACOES, 'turma_origem_id')) {
                    $table->unsignedBigInteger('turma_origem_id')
                        ->nullable()
                        ->after('aluno_origem_id');
                }

                if (! Schema::hasColumn(self::INFORMACOES, 'bloqueio_tipo')) {
                    $table->string('bloqueio_tipo', 32)->nullable()->after('turma_origem_id');
                }
            });

            $this->ensureForeign(self::INFORMACOES, 'informacao_origem_id', self::INFORMACOES, 'fk_avic_info_orig');
            $this->ensureForeign(self::INFORMACOES, 'aluno_origem_id', 'alunos', 'fk_avic_aluno_orig');
            $this->ensureForeign(self::INFORMACOES, 'turma_origem_id', 'turmas', 'fk_avic_turma_orig');

            if (! Schema::hasIndex(self::INFORMACOES, 'idx_avic_bloqueio_origem')) {
                Schema::table(self::INFORMACOES, function (Blueprint $table): void {
                    $table->index(['bloqueada', 'aluno_origem_id'], 'idx_avic_bloqueio_origem');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable(self::RESPOSTAS)) {
            if (Schema::hasIndex(self::RESPOSTAS, 'idx_avresp_bloqueio_origem')) {
                Schema::table(self::RESPOSTAS, function (Blueprint $table): void {
                    $table->dropIndex('idx_avresp_bloqueio_origem');
                });
            }

            foreach (['turma_origem_id', 'aluno_origem_id', 'resposta_origem_id'] as $column) {
                $this->dropForeignIfExists(self::RESPOSTAS, $column);
            }

            Schema::table(self::RESPOSTAS, function (Blueprint $table): void {
                foreach (['bloqueio_tipo', 'turma_origem_id', 'aluno_origem_id', 'resposta_origem_id', 'bloqueada'] as $column) {
                    if (Schema::hasColumn(self::RESPOSTAS, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable(self::INFORMACOES)) {
            if (Schema::hasIndex(self::INFORMACOES, 'idx_avic_bloqueio_origem')) {
                Schema::table(self::INFORMACOES, function (Blueprint $table): void {
                    $table->dropIndex('idx_avic_bloqueio_origem');
                });
            }

            foreach (['turma_origem_id', 'aluno_origem_id', 'informacao_origem_id'] as $column) {
                $this->dropForeignIfExists(self::INFORMACOES, $column);
            }

            Schema::table(self::INFORMACOES, function (Blueprint $table): void {
                foreach (['bloqueio_tipo', 'turma_origem_id', 'aluno_origem_id', 'informacao_origem_id', 'bloqueada'] as $column) {
                    if (Schema::hasColumn(self::INFORMACOES, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function ensureForeign(string $table, string $column, string $referencesTable, string $constraint): void
    {
        if (! Schema::hasColumn($table, $column) || $this->foreignKeyExists($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($column, $referencesTable, $constraint): void {
            $table
                ->foreign($column, $constraint)
                ->references('id')
                ->on($referencesTable)
                ->nullOnDelete();
        });
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        $constraint = $this->foreignKeyName($table, $column);

        if (! $constraint) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($constraint): void {
            $table->dropForeign($constraint);
        });
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        return filled($this->foreignKeyName($table, $column));
    }

    private function foreignKeyName(string $table, string $column): ?string
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return null;
        }

        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');
    }
};
