<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('funcao_administrativa')
            || ! Schema::hasTable('servidor_funcao_administrativa')
        ) {
            return;
        }

        $funcaoIds = DB::table('funcao_administrativa')
            ->where(function ($query): void {
                $query
                    ->where('direcao_escolar', true)
                    ->orWhere('coordenacao_pedagogica', true);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($funcaoIds === []) {
            return;
        }

        $hoje = now()->toDateString();

        DB::table('servidor_funcao_administrativa')
            ->whereIn('funcao_administrativa_id', $funcaoIds)
            ->whereNull('data_inicio')
            ->select(['id', 'created_at', 'data_fim'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $vinculos) use ($hoje): void {
                $vinculos
                    ->groupBy(fn (object $vinculo): string => $this->resolverDataInicio($vinculo, $hoje))
                    ->each(function (Collection $grupo, string $dataInicio): void {
                        DB::table('servidor_funcao_administrativa')
                            ->whereIn('id', $grupo->pluck('id')->all())
                            ->whereNull('data_inicio')
                            ->update([
                                'data_inicio' => $dataInicio,
                                'updated_at' => now(),
                            ]);
                    });
            });

        if (! Schema::hasTable('servidor_funcao_turma')
            || ! Schema::hasColumn('servidor_funcao_turma', 'data_inicio')
        ) {
            return;
        }

        DB::table('servidor_funcao_administrativa')
            ->whereIn('funcao_administrativa_id', $funcaoIds)
            ->whereNotNull('data_inicio')
            ->select(['id', 'data_inicio'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $vinculos): void {
                $vinculos
                    ->groupBy(fn (object $vinculo): string => (string) $vinculo->data_inicio)
                    ->each(function (Collection $grupo, string $dataInicio): void {
                        DB::table('servidor_funcao_turma')
                            ->whereIn('servidor_funcao_administrativa_id', $grupo->pluck('id')->all())
                            ->whereNull('data_inicio')
                            ->update([
                                'data_inicio' => $dataInicio,
                                'updated_at' => now(),
                            ]);
                    });
            });
    }

    public function down(): void
    {
        // Saneamento de dados deliberadamente irreversível.
    }

    private function resolverDataInicio(object $vinculo, string $hoje): string
    {
        $dataInicio = filled($vinculo->created_at)
            ? CarbonImmutable::parse($vinculo->created_at)->toDateString()
            : $hoje;

        if (filled($vinculo->data_fim) && $dataInicio > (string) $vinculo->data_fim) {
            return CarbonImmutable::parse($vinculo->data_fim)->toDateString();
        }

        return $dataInicio;
    }
};
