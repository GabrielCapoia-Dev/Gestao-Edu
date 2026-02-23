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

            $table->string('nome', 100);
            $table->string('cor')->nullable();

            $table->boolean('finaliza_pedido')->default(false);
            $table->boolean('cancela_pedido')->default(false);
            $table->boolean('ativo')->default(true);

            $table->foreignId('registro_anterior_id')
                ->nullable()
                ->constrained('tipo_status')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['nome', 'ativo']);
            $table->index('registro_anterior_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_status');
    }
};
