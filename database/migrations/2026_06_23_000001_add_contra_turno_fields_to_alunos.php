<?php

use App\Models\Aluno;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'alunos';

    private const INDEX_CGM_CONTRA_TURNO_ATIVO = 'alunos_cgm_contra_turno_ativo_unique';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table): void {
            if (! Schema::hasColumn(self::TABLE, 'tipo_vinculo')) {
                $table->string('tipo_vinculo')->default(Aluno::TIPO_VINCULO_PRINCIPAL)->after('id_turma');
            }

            if (! Schema::hasColumn(self::TABLE, 'permite_contra_turno')) {
                $table->boolean('permite_contra_turno')->default(false)->after('tipo_vinculo');
            }

            if (! Schema::hasColumn(self::TABLE, 'cgm_contra_turno_ativo')) {
                $table->string('cgm_contra_turno_ativo')->nullable()->after('cgm_matricula_ativa');
            }
        });

        DB::table(self::TABLE)
            ->whereNull('tipo_vinculo')
            ->update([
                'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
                'permite_contra_turno' => false,
            ]);

        DB::table(self::TABLE)
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_CONTRA_TURNO)
            ->where('status', Aluno::STATUS_MATRICULADO)
            ->update([
                'cgm_contra_turno_ativo' => DB::raw('cgm'),
            ]);

        if (! Schema::hasIndex(self::TABLE, self::INDEX_CGM_CONTRA_TURNO_ATIVO, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique('cgm_contra_turno_ativo', self::INDEX_CGM_CONTRA_TURNO_ATIVO);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex(self::TABLE, self::INDEX_CGM_CONTRA_TURNO_ATIVO, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::INDEX_CGM_CONTRA_TURNO_ATIVO);
            });
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            foreach (['cgm_contra_turno_ativo', 'permite_contra_turno', 'tipo_vinculo'] as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
