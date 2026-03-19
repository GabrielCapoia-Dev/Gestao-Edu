<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itens', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('tipo_item');
            $table->string('unidade_medida');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('contrato_item', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contrato_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('item_id')
                ->constrained('itens') // ✅ correto
                ->restrictOnDelete();

            $table->string('tipo'); // enum no PHP já resolve

            $table->decimal('quantidade_total', 10, 3);
            $table->decimal('quantidade_utilizada', 10, 3)->default(0);

            $table->decimal('preco_unitario', 10, 2);

            // 🔥 calculado no banco (correto)
            $table->decimal('preco_total', 10, 2)
                ->storedAs('quantidade_total * preco_unitario');

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_item');
        Schema::dropIfExists('itens');
    }
};
