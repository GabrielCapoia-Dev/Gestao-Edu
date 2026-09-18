<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evento_calendario_publico_regras', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evento_calendario_id')
                ->constrained('eventos_calendario')
                ->cascadeOnDelete();
            $table->json('filtros');
            $table->timestamps();

            $table->index('evento_calendario_id', 'evento_publico_regras_evento_idx');
        });

        Schema::create('evento_calendario_publico_excecoes', function (Blueprint $table): void {
            $table->foreignId('evento_calendario_id')
                ->constrained('eventos_calendario')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['evento_calendario_id', 'user_id'], 'evento_publico_excecoes_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_calendario_publico_excecoes');
        Schema::dropIfExists('evento_calendario_publico_regras');
    }
};
