<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessoria_pedagogica_escola', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('servidor_funcao_administrativa_id')
                ->constrained('servidor_funcao_administrativa')
                ->cascadeOnDelete();
            $table->foreignId('escola_id')
                ->constrained('escolas')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['servidor_funcao_administrativa_id', 'escola_id'],
                'assessoria_pedagogica_escola_unique',
            );
            $table->index(
                ['escola_id', 'servidor_funcao_administrativa_id'],
                'assessoria_pedagogica_escola_reverso',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessoria_pedagogica_escola');
    }
};
