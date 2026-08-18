<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avaliacao_dashboard_pendencias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['avaliacao_id', 'id'], 'idx_av_dashboard_pend_avaliacao');
            $table->index(['avaliacao_id', 'aluno_id'], 'idx_av_dashboard_pend_aluno');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacao_dashboard_pendencias');
    }
};
