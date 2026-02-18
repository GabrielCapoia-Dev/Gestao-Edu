<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_manutencao', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->timestamps();

            // Campos de histórico
            $table->boolean('ativo')->default(true);
            $table->foreignId('registro_anterior_id')
                ->nullable()
                ->constrained('tipo_manutencao')
                ->nullOnDelete();

            $table->index(['nome', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_manutencao');
    }
};