<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avaliacao_exportacoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('escola_id')->nullable()->constrained('escolas')->nullOnDelete();
            $table->foreignId('turma_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->foreignId('aluno_id')->nullable()->constrained('alunos')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('escopo', 20);
            $table->string('formato', 20)->default('pdf');
            $table->unsignedInteger('quantidade_alunos')->default(0);
            $table->unsignedInteger('quantidade_paginas')->nullable();
            $table->json('parametros')->nullable();
            $table->timestamp('exportado_em')->useCurrent();
            $table->timestamps();

            $table->index(['avaliacao_id', 'escopo', 'exportado_em'], 'idx_avaliacao_exportacoes_escopo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacao_exportacoes');
    }
};
