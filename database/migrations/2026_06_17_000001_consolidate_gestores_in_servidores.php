<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funcao_administrativa', function (Blueprint $table): void {
            if (! Schema::hasColumn('funcao_administrativa', 'direcao_escolar')) {
                $table->boolean('direcao_escolar')->default(false)->after('tem_relacao_turma');
            }

            if (! Schema::hasColumn('funcao_administrativa', 'coordenacao_pedagogica')) {
                $table->boolean('coordenacao_pedagogica')->default(false)->after('direcao_escolar');
            }
        });

        if (! Schema::hasTable('servidor_funcao_turma')) {
            Schema::create('servidor_funcao_turma', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('servidor_funcao_administrativa_id')
                    ->constrained('servidor_funcao_administrativa')
                    ->cascadeOnDelete();
                $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(
                    ['servidor_funcao_administrativa_id', 'turma_id'],
                    'servidor_funcao_turma_unique'
                );
            });
        }

        $this->normalizarFuncoesGestoras();
        $this->backfillTurmasDosVinculos();
    }

    public function down(): void
    {
        Schema::dropIfExists('servidor_funcao_turma');

        Schema::table('funcao_administrativa', function (Blueprint $table): void {
            foreach (['coordenacao_pedagogica', 'direcao_escolar'] as $column) {
                if (Schema::hasColumn('funcao_administrativa', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function normalizarFuncoesGestoras(): void
    {
        DB::table('funcao_administrativa')
            ->orderBy('id')
            ->chunkById(100, function ($funcoes): void {
                foreach ($funcoes as $funcao) {
                    $texto = Str::of(($funcao->nome ?? '').' '.($funcao->codigo ?? ''))
                        ->ascii()
                        ->lower()
                        ->toString();

                    $direcao = str_contains($texto, 'diretor')
                        || str_contains($texto, 'diretora')
                        || str_contains($texto, 'direcao');

                    $coordenacao = str_contains($texto, 'coordenador')
                        || str_contains($texto, 'coordenadora')
                        || str_contains($texto, 'coordenacao');

                    $categoria = $funcao->categoria === 'equipe_gestora'
                        ? ($coordenacao ? 'pedagogico' : 'administrativo')
                        : $funcao->categoria;

                    DB::table('funcao_administrativa')
                        ->where('id', $funcao->id)
                        ->update([
                            'direcao_escolar' => $direcao,
                            'coordenacao_pedagogica' => $coordenacao,
                            'categoria' => $categoria,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    private function backfillTurmasDosVinculos(): void
    {
        if (! Schema::hasTable('professor_funcao_turma')) {
            return;
        }

        DB::table('professor_funcao_turma as pft')
            ->join('professores as p', 'p.id', '=', 'pft.professor_id')
            ->join('servidor_funcao_administrativa as sfa', function ($join): void {
                $join
                    ->on('sfa.servidor_id', '=', 'p.servidor_id')
                    ->on('sfa.funcao_administrativa_id', '=', 'p.funcao_administrativa_id')
                    ->where('sfa.status', '=', 'ativo');
            })
            ->whereNotNull('p.servidor_id')
            ->whereNotNull('p.funcao_administrativa_id')
            ->select([
                'sfa.id as servidor_funcao_administrativa_id',
                'pft.turma_id',
                'pft.created_at',
                'pft.updated_at',
            ])
            ->orderBy('pft.id')
            ->chunk(200, function ($vinculos): void {
                foreach ($vinculos as $vinculo) {
                    DB::table('servidor_funcao_turma')->insertOrIgnore([
                        'servidor_funcao_administrativa_id' => $vinculo->servidor_funcao_administrativa_id,
                        'turma_id' => $vinculo->turma_id,
                        'created_at' => $vinculo->created_at ?? now(),
                        'updated_at' => $vinculo->updated_at ?? now(),
                    ]);
                }
            });
    }
};
