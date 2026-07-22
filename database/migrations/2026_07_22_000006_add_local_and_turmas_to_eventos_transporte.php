<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PIVOT = 'evento_transporte_alocacao_turma';

    public function up(): void
    {
        if (! Schema::hasColumn('eventos_calendario', 'local')) {
            Schema::table('eventos_calendario', function (Blueprint $table): void {
                $table->string('local')->nullable()->after('descricao');
            });
        }

        if (! Schema::hasTable(self::PIVOT)) {
            Schema::create(self::PIVOT, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('alocacao_id');
                $table->foreignId('turma_id');
                $table->timestamps();

                $table->unique(['alocacao_id', 'turma_id'], 'uq_evt_aloc_turma');
                $table->index(['turma_id', 'alocacao_id'], 'idx_evt_aloc_turma_turma');
                $table->foreign('alocacao_id', 'fk_evt_aloc_turma_aloc')
                    ->references('id')->on('evento_calendario_transporte_alocacoes')
                    ->cascadeOnDelete();
                $table->foreign('turma_id', 'fk_evt_aloc_turma_turma')
                    ->references('id')->on('turmas')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(self::PIVOT);

        if (Schema::hasColumn('eventos_calendario', 'local')) {
            Schema::table('eventos_calendario', fn (Blueprint $table) => $table->dropColumn('local'));
        }
    }
};
