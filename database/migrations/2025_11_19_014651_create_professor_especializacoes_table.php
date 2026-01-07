<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professor_especializacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_professor')
                ->constrained('professores')
                ->cascadeOnDelete();

            $table->string('tipo'); // Magistério, Licenciatura etc.
            $table->string('descricao_especializacao', 255);

            $table->boolean('especializacao_educacao_especial')->default(false);

            $table->string('anexo_especializacao_path')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professor_especializacoes');
    }
};
