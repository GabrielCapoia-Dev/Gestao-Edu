<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'alunos';

    private const CGM_UNIQUE = 'alunos_cgm_unique';

    private const CGM_ACTIVE_UNIQUE = 'alunos_cgm_matricula_ativa_unique';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table): void {
            if (! Schema::hasColumn(self::TABLE, 'status')) {
                $table->string('status', 32)->default('matriculado')->after('id_turma');
            }

            if (! Schema::hasColumn(self::TABLE, 'status_alterado_em')) {
                $table->timestamp('status_alterado_em')->nullable()->after('status');
            }

            if (! Schema::hasColumn(self::TABLE, 'status_alterado_por')) {
                $table->foreignId('status_alterado_por')
                    ->nullable()
                    ->after('status_alterado_em')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn(self::TABLE, 'status_motivo')) {
                $table->text('status_motivo')->nullable()->after('status_alterado_por');
            }

            if (! Schema::hasColumn(self::TABLE, 'cgm_matricula_ativa')) {
                $table->string('cgm_matricula_ativa')->nullable()->after('cgm');
            }

            if (! Schema::hasColumn(self::TABLE, 'aluno_origem_id')) {
                $table->foreignId('aluno_origem_id')
                    ->nullable()
                    ->after('status_motivo')
                    ->constrained('alunos')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn(self::TABLE, 'turma_origem_id')) {
                $table->foreignId('turma_origem_id')
                    ->nullable()
                    ->after('aluno_origem_id')
                    ->constrained('turmas')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn(self::TABLE, 'movimentacao_origem')) {
                $table->string('movimentacao_origem', 32)->nullable()->after('turma_origem_id');
            }
        });

        DB::table(self::TABLE)
            ->whereNull('status')
            ->update(['status' => 'matriculado']);

        DB::table(self::TABLE)
            ->where('status', 'matriculado')
            ->update(['cgm_matricula_ativa' => DB::raw('cgm')]);

        DB::table(self::TABLE)
            ->whereNull('status_alterado_em')
            ->update(['status_alterado_em' => DB::raw('updated_at')]);

        if (Schema::hasIndex(self::TABLE, self::CGM_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::CGM_UNIQUE);
            });
        }

        if (! Schema::hasIndex(self::TABLE, self::CGM_ACTIVE_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique('cgm_matricula_ativa', self::CGM_ACTIVE_UNIQUE);
                $table->index(['status', 'id_turma'], 'idx_alunos_status_turma');
                $table->index(['cgm', 'status'], 'idx_alunos_cgm_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex(self::TABLE, self::CGM_ACTIVE_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::CGM_ACTIVE_UNIQUE);
            });
        }

        foreach (['idx_alunos_status_turma', 'idx_alunos_cgm_status'] as $index) {
            if (Schema::hasIndex(self::TABLE, $index)) {
                Schema::table(self::TABLE, function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index);
                });
            }
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            foreach (['turma_origem_id', 'aluno_origem_id', 'status_alterado_por'] as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach ([
                'movimentacao_origem',
                'status_motivo',
                'status_alterado_em',
                'status',
                'cgm_matricula_ativa',
            ] as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (! Schema::hasIndex(self::TABLE, self::CGM_UNIQUE, 'unique') && ! $this->cgmTemDuplicados()) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique('cgm', self::CGM_UNIQUE);
            });
        }
    }

    private function cgmTemDuplicados(): bool
    {
        return DB::table(self::TABLE)
            ->select('cgm')
            ->groupBy('cgm')
            ->havingRaw('count(*) > 1')
            ->exists();
    }
};
