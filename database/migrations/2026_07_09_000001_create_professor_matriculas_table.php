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
            Schema::create('professor_matriculas', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('servidor_id')
                    ->constrained('servidores')
                    ->cascadeOnDelete();
                $table->string('matricula');
                $table->string('turno');
                $table->timestamps();

                $table->unique(['servidor_id', 'matricula'], 'uniq_professor_matriculas_servidor_matricula');
                $table->index(['servidor_id', 'turno'], 'idx_professor_matriculas_servidor_turno');
            });
        }

        if (! Schema::hasColumn('professores', 'professor_matricula_id')) {
            Schema::table('professores', function (Blueprint $table): void {
                $table->foreignId('professor_matricula_id')
                    ->nullable()
                    ->after('servidor_id')
                    ->constrained('professor_matriculas')
                    ->nullOnDelete();

                $table->index('professor_matricula_id', 'idx_professores_professor_matricula');
            });
        }

        $this->backfillMatriculas();
    }

    public function down(): void
    {
        if (Schema::hasColumn('professores', 'professor_matricula_id')) {
            Schema::table('professores', function (Blueprint $table): void {
                $table->dropForeign(['professor_matricula_id']);
                $table->dropIndex('idx_professores_professor_matricula');
                $table->dropColumn('professor_matricula_id');
            });
        }

        Schema::dropIfExists('professor_matriculas');
    }

    private function backfillMatriculas(): void
    {
        if (! Schema::hasTable('professores') || ! Schema::hasTable('professor_matriculas')) {
            return;
        }

        $professores = DB::table('professores')
            ->whereNotNull('servidor_id')
            ->whereNotNull('matricula')
            ->orderBy('id')
            ->get(['id', 'servidor_id', 'matricula', 'turno', 'professor_matricula_id']);

        $cache = [];

        foreach ($professores as $professor) {
            if (filled($professor->professor_matricula_id ?? null)) {
                continue;
            }

            $servidorId = (int) $professor->servidor_id;
            $matricula = (string) $professor->matricula;
            $turno = filled($professor->turno ?? null) ? (string) $professor->turno : 'manha';
            $cacheKey = "{$servidorId}|{$matricula}";

            if (! isset($cache[$cacheKey])) {
                $existente = DB::table('professor_matriculas')
                    ->where('servidor_id', $servidorId)
                    ->where('matricula', $matricula)
                    ->first();

                if ($existente) {
                    $cache[$cacheKey] = (int) $existente->id;
                } else {
                    $cache[$cacheKey] = (int) DB::table('professor_matriculas')->insertGetId([
                        'servidor_id' => $servidorId,
                        'matricula' => $matricula,
                        'turno' => $turno,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('professores')
                ->where('id', $professor->id)
                ->update([
                    'professor_matricula_id' => $cache[$cacheKey],
                    'turno' => $turno,
                    'updated_at' => now(),
                ]);
        }
    }
};
