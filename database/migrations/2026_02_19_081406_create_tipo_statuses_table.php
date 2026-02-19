<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_setor')->constrained('setor')->cascadeOnDelete();
            $table->string('nome');
            $table->string('cor')->nullable(); // Para exibição na UI (ex: #FF0000)
            $table->boolean('finaliza_pedido')->default(false); // Se marca pedido como concluído
            $table->boolean('cancela_pedido')->default(false); // Se marca pedido como cancelado
            $table->timestamps();

            // Campos de histórico
            $table->boolean('ativo')->default(true);
            $table->foreignId('registro_anterior_id')
                ->nullable()
                ->constrained('tipo_status')
                ->nullOnDelete();

            $table->index(['nome', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_status');
    }
};