<?php

use App\Models\Aluno;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'alunos';

    private const CGM_UNIDADE_ACTIVE_UNIQUE = 'alunos_cgm_unidade_matricula_ativa_unique';

    private const CGM_UNIDADE_STATUS_INDEX = 'idx_alunos_cgm_unidade_status';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table): void {
            if (! Schema::hasColumn(self::TABLE, 'cgm_unidade_matricula_ativa')) {
                $table->string('cgm_unidade_matricula_ativa')->nullable()->after('cgm_matricula_ativa');
            }

            if (! Schema::hasColumn(self::TABLE, 'pendencia_origem_aluno_id')) {
                $table->foreignId('pendencia_origem_aluno_id')
                    ->nullable()
                    ->after('movimentacao_origem')
                    ->constrained('alunos')
                    ->nullOnDelete();
            }
        });

        $this->popularChaveAtivaPorUnidade();

        if (! Schema::hasIndex(self::TABLE, self::CGM_UNIDADE_ACTIVE_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique('cgm_unidade_matricula_ativa', self::CGM_UNIDADE_ACTIVE_UNIQUE);
            });
        }

        if (! Schema::hasIndex(self::TABLE, self::CGM_UNIDADE_STATUS_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->index(['cgm', 'status', 'id_turma'], self::CGM_UNIDADE_STATUS_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex(self::TABLE, self::CGM_UNIDADE_ACTIVE_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::CGM_UNIDADE_ACTIVE_UNIQUE);
            });
        }

        if (Schema::hasIndex(self::TABLE, self::CGM_UNIDADE_STATUS_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropIndex(self::CGM_UNIDADE_STATUS_INDEX);
            });
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            if (Schema::hasColumn(self::TABLE, 'pendencia_origem_aluno_id')) {
                $table->dropConstrainedForeignId('pendencia_origem_aluno_id');
            }

            if (Schema::hasColumn(self::TABLE, 'cgm_unidade_matricula_ativa')) {
                $table->dropColumn('cgm_unidade_matricula_ativa');
            }
        });
    }

    private function popularChaveAtivaPorUnidade(): void
    {
        DB::table(self::TABLE)
            ->whereIn('status', [Aluno::STATUS_MATRICULADO, Aluno::STATUS_PENDENTE])
            ->orderBy('id')
            ->chunkById(200, function ($alunos): void {
                $turmasPorId = DB::table('turmas')
                    ->whereIn('id', $alunos->pluck('id_turma')->filter()->unique()->values()->all())
                    ->pluck('id_escola', 'id');

                foreach ($alunos as $aluno) {
                    $escolaId = (int) ($turmasPorId[(int) $aluno->id_turma] ?? 0);
                    $chave = Aluno::chaveCgmUnidade($escolaId, (string) $aluno->cgm);

                    DB::table(self::TABLE)
                        ->where('id', (int) $aluno->id)
                        ->update(['cgm_unidade_matricula_ativa' => $chave]);
                }
            });
    }
};
