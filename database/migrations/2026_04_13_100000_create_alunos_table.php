<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('alunos')) {
            $this->prepareLegacyAlunosTable();

            return;
        }

        Schema::create('alunos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('cgm')->unique();
            $table->date('data_nascimento');
            $table->foreignId('id_turma')->constrained('turmas')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (
            Schema::hasTable('alunos')
            && ! Schema::hasColumn('alunos', 'sexo')
            && ! Schema::hasColumn('alunos', 'id_professor')
        ) {
            Schema::dropIfExists('alunos');
        }
    }

    private function prepareLegacyAlunosTable(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $nullableLegacyColumns = [
            'sexo',
            'encaminhado_para_sme',
            'ja_foi_retido',
            'encaminhado_para_caei',
        ];

        foreach ($nullableLegacyColumns as $column) {
            if (Schema::hasColumn('alunos', $column)) {
                $definition = DB::selectOne(
                    'select COLUMN_TYPE as column_type
                    from information_schema.COLUMNS
                    where TABLE_SCHEMA = DATABASE()
                        and TABLE_NAME = ?
                        and COLUMN_NAME = ?',
                    ['alunos', $column],
                )?->column_type;

                if ($definition) {
                    DB::statement("ALTER TABLE `alunos` MODIFY `{$column}` {$definition} NULL");
                }
            }
        }
    }
};
