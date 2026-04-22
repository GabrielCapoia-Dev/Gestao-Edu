<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_avaliacao', function (Blueprint $table): void {
            $table->id();
            $table->string('nome')->unique();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('periodos_avaliacao', function (Blueprint $table): void {
            $table->id();
            $table->string('nome')->unique();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::table('alternativas', function (Blueprint $table): void {
            $table->foreignId('tipo_avaliacao_id')
                ->nullable()
                ->after('id')
                ->constrained('tipos_avaliacao')
                ->nullOnDelete();
        });

        Schema::table('pautas', function (Blueprint $table): void {
            $table->foreignId('tipo_avaliacao_id')
                ->nullable()
                ->after('id')
                ->constrained('tipos_avaliacao')
                ->nullOnDelete();
            $table->foreignId('serie_id')
                ->nullable()
                ->after('componente_curricular_id')
                ->constrained('series')
                ->nullOnDelete();
        });

        Schema::table('avaliacoes', function (Blueprint $table): void {
            $table->foreignId('tipo_avaliacao_id')
                ->nullable()
                ->after('id')
                ->constrained('tipos_avaliacao')
                ->nullOnDelete();
            $table->foreignId('periodo_avaliacao_id')
                ->nullable()
                ->after('tipo_avaliacao_id')
                ->constrained('periodos_avaliacao')
                ->nullOnDelete();
        });

        Schema::create('avaliacao_serie', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('serie_id')->constrained('series')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['avaliacao_id', 'serie_id'], 'uniq_avaliacao_serie');
        });

        Schema::create('avaliacao_componente', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('componente_curricular_id')->constrained('componentes_curriculares')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['avaliacao_id', 'componente_curricular_id'], 'uniq_avaliacao_componente');
        });

        Schema::create('avaliacao_escola', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['avaliacao_id', 'escola_id'], 'uniq_avaliacao_escola');
        });

        Schema::create('avaliacao_pauta_alternativa', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('pauta_id')->constrained('pautas')->cascadeOnDelete();
            $table->foreignId('alternativa_id')->constrained('alternativas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['avaliacao_id', 'pauta_id', 'alternativa_id'],
                'uniq_avaliacao_pauta_alternativa'
            );
        });

        Schema::create('avaliacao_informacoes_complementares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->foreignId('professor_id')->nullable()->constrained('professores')->nullOnDelete();
            $table->text('informacoes_complementares')->nullable();
            $table->timestamps();

            $table->unique(
                ['avaliacao_id', 'turma_id', 'aluno_id'],
                'uniq_avaliacao_turma_aluno_complemento'
            );
        });

        $agora = now();

        $tipoPadraoId = DB::table('tipos_avaliacao')->insertGetId([
            'nome' => 'Geral',
            'status' => true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $periodoPadraoId = DB::table('periodos_avaliacao')->insertGetId([
            'nome' => 'Sem período definido',
            'status' => true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        DB::table('alternativas')
            ->whereNull('tipo_avaliacao_id')
            ->update(['tipo_avaliacao_id' => $tipoPadraoId]);

        DB::table('pautas')
            ->whereNull('tipo_avaliacao_id')
            ->update(['tipo_avaliacao_id' => $tipoPadraoId]);

        DB::table('avaliacoes')
            ->whereNull('tipo_avaliacao_id')
            ->update([
                'tipo_avaliacao_id' => $tipoPadraoId,
                'periodo_avaliacao_id' => $periodoPadraoId,
            ]);

        $avaliacoesIds = DB::table('avaliacoes')->pluck('id')->all();

        foreach ($avaliacoesIds as $avaliacaoId) {
            $turmas = DB::table('avaliacao_turma as at')
                ->join('turmas as t', 't.id', '=', 'at.turma_id')
                ->where('at.avaliacao_id', (int) $avaliacaoId)
                ->select('t.id', 't.id_serie', 't.id_escola')
                ->get();

            $seriesIds = collect($turmas)->pluck('id_serie')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
            $escolasIds = collect($turmas)->pluck('id_escola')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

            $componentesIds = DB::table('avaliacao_pauta as ap')
                ->join('pautas as p', 'p.id', '=', 'ap.pauta_id')
                ->where('ap.avaliacao_id', (int) $avaliacaoId)
                ->whereNotNull('p.componente_curricular_id')
                ->distinct()
                ->pluck('p.componente_curricular_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if ($componentesIds === [] && $turmas->isNotEmpty()) {
                $componentesIds = DB::table('turma_componente_professor')
                    ->whereIn('turma_id', $turmas->pluck('id')->all())
                    ->distinct()
                    ->pluck('componente_curricular_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
            }

            foreach ($seriesIds as $serieId) {
                DB::table('avaliacao_serie')->updateOrInsert(
                    ['avaliacao_id' => (int) $avaliacaoId, 'serie_id' => $serieId],
                    ['created_at' => $agora, 'updated_at' => $agora]
                );
            }

            foreach ($componentesIds as $componenteId) {
                DB::table('avaliacao_componente')->updateOrInsert(
                    ['avaliacao_id' => (int) $avaliacaoId, 'componente_curricular_id' => $componenteId],
                    ['created_at' => $agora, 'updated_at' => $agora]
                );
            }

            foreach ($escolasIds as $escolaId) {
                DB::table('avaliacao_escola')->updateOrInsert(
                    ['avaliacao_id' => (int) $avaliacaoId, 'escola_id' => $escolaId],
                    ['created_at' => $agora, 'updated_at' => $agora]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacao_informacoes_complementares');
        Schema::dropIfExists('avaliacao_pauta_alternativa');
        Schema::dropIfExists('avaliacao_escola');
        Schema::dropIfExists('avaliacao_componente');
        Schema::dropIfExists('avaliacao_serie');

        Schema::table('avaliacoes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('periodo_avaliacao_id');
            $table->dropConstrainedForeignId('tipo_avaliacao_id');
        });

        Schema::table('pautas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('serie_id');
            $table->dropConstrainedForeignId('tipo_avaliacao_id');
        });

        Schema::table('alternativas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tipo_avaliacao_id');
        });

        Schema::dropIfExists('periodos_avaliacao');
        Schema::dropIfExists('tipos_avaliacao');
    }
};
