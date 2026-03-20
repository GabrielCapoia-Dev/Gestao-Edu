<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turma_componente_professor', function (Blueprint $table) {
            $table->id();

            $table->foreignId('turma_id')
                ->constrained('turmas')
                ->cascadeOnDelete();

            $table->foreignId('componente_curricular_id')
                ->constrained('componentes_curriculares')
                ->cascadeOnDelete();

            $table->foreignId('professor_id')
                ->nullable()
                ->constrained('professores')
                ->cascadeOnDelete();

            $table->boolean('tem_professor')->default(false);

            $table->unique(
                ['turma_id', 'componente_curricular_id'],
                'uniq_turma_componente'
            );

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turma_componente_professor');
    }
};
