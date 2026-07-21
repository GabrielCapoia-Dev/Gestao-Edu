<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos_calendario', function (Blueprint $table): void {
            $table->boolean('enviar_todas_escolas')->default(true)->after('setor_id');
            $table->index(
                ['enviar_todas_escolas', 'ativo', 'data_inicio'],
                'idx_evento_cal_todas_escolas_ativo_inicio',
            );
        });

        Schema::create('evento_calendario_escolas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evento_calendario_id')
                ->constrained('eventos_calendario')
                ->cascadeOnDelete();
            $table->foreignId('escola_id')->constrained('escolas')->restrictOnDelete();
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->boolean('precisa_transporte')->default(false);
            $table->string('escopo_transporte', 24)->nullable();
            $table->unsignedInteger('quantidade_estimada_transporte')->nullable();
            $table->timestamps();

            $table->unique(
                ['evento_calendario_id', 'escola_id'],
                'uniq_evento_cal_escola',
            );
            $table->index(
                ['escola_id', 'evento_calendario_id'],
                'idx_evento_cal_escola_reverso',
            );
        });

        Schema::create('evento_calendario_escola_serie', function (Blueprint $table): void {
            $table->foreignId('evento_calendario_escola_id')
                ->constrained(
                    'evento_calendario_escolas',
                    indexName: 'fk_evento_cal_escola_serie_evento',
                )
                ->cascadeOnDelete();
            $table->foreignId('serie_id')->constrained('series')->restrictOnDelete();
            $table->primary(
                ['evento_calendario_escola_id', 'serie_id'],
                'pk_evento_cal_escola_serie',
            );
            $table->index(['serie_id', 'evento_calendario_escola_id'], 'idx_evento_cal_serie_reverso');
        });

        Schema::create('evento_calendario_escola_turma', function (Blueprint $table): void {
            $table->foreignId('evento_calendario_escola_id')
                ->constrained(
                    'evento_calendario_escolas',
                    indexName: 'fk_evento_cal_escola_turma_evento',
                )
                ->cascadeOnDelete();
            $table->foreignId('turma_id')->constrained('turmas')->restrictOnDelete();
            $table->primary(
                ['evento_calendario_escola_id', 'turma_id'],
                'pk_evento_cal_escola_turma',
            );
            $table->index(['turma_id', 'evento_calendario_escola_id'], 'idx_evento_cal_turma_reverso');
        });

        DB::table('eventos_calendario')
            ->whereNotNull('escola_id')
            ->update(['enviar_todas_escolas' => false]);

        DB::table('eventos_calendario')
            ->whereNotNull('escola_id')
            ->orderBy('id')
            ->chunkById(250, function ($eventos): void {
                $agora = now();
                $linhas = collect($eventos)->map(function ($evento) use ($agora): array {
                    $inicio = Carbon::parse($evento->data_inicio);
                    $fim = Carbon::parse($evento->data_fim);

                    return [
                        'evento_calendario_id' => $evento->id,
                        'escola_id' => $evento->escola_id,
                        'hora_inicio' => $inicio->format('H:i:s'),
                        'hora_fim' => $fim->format('H:i:s'),
                        'precisa_transporte' => false,
                        'escopo_transporte' => null,
                        'quantidade_estimada_transporte' => null,
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ];
                })->all();

                DB::table('evento_calendario_escolas')->insertOrIgnore($linhas);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_calendario_escola_turma');
        Schema::dropIfExists('evento_calendario_escola_serie');
        Schema::dropIfExists('evento_calendario_escolas');

        Schema::table('eventos_calendario', function (Blueprint $table): void {
            $table->dropIndex('idx_evento_cal_todas_escolas_ativo_inicio');
            $table->dropColumn('enviar_todas_escolas');
        });
    }
};
