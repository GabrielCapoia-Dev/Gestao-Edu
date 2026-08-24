<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('professor_matriculas')) {
            return;
        }

        Schema::table('professor_matriculas', function (Blueprint $table): void {
            if (! Schema::hasColumn('professor_matriculas', 'carga_horaria')) {
                $table->unsignedTinyInteger('carga_horaria')->default(20);
            }
            if (! Schema::hasColumn('professor_matriculas', 'jornada')) {
                $table->boolean('jornada')->default(false);
            }
            if (! Schema::hasColumn('professor_matriculas', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        DB::table('professor_matriculas')->update([
            'carga_horaria' => DB::raw("CASE WHEN turno = 'integral' THEN 40 ELSE 20 END"),
            // Não existe sinal confiável para distinguir dois concursos de uma
            // jornada no legado. A classificação deve ser explicitada no cadastro.
            'jornada' => false,
        ]);

        if (Schema::hasTable('servidores') && Schema::hasColumn('servidores', 'jornada')) {
            DB::table('servidores')
                ->whereIn('id', DB::table('professor_matriculas')->select('servidor_id'))
                ->update(['jornada' => false]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('professor_matriculas')) {
            return;
        }

        Schema::table('professor_matriculas', function (Blueprint $table): void {
            $columns = collect(['carga_horaria', 'jornada', 'deleted_at'])
                ->filter(fn (string $column): bool => Schema::hasColumn('professor_matriculas', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
