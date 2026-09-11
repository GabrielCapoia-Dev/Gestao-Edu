<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professor_componente_solicitacoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('turma_componente_professor_id')
                ->constrained('turma_componente_professor')
                ->cascadeOnDelete();
            $table->foreignId('professor_id')->constrained('professores')->cascadeOnDelete();
            $table->foreignId('solicitado_por_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pendente')->index();
            $table->foreignId('analisado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('analisado_em')->nullable();
            $table->string('motivo_rejeicao')->nullable();
            $table->timestamps();

            $table->unique(
                ['turma_componente_professor_id', 'professor_id'],
                'prof_comp_solicitacao_unica'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professor_componente_solicitacoes');
    }
};
