<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // estoque
        Schema::create('estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('itens')->restrictOnDelete();
            $table->decimal('quantidade', 10, 3)->default(0);
            $table->timestamps();

            $table->unique('item_id'); // cada item tem apenas uma linha no estoque
        });

        // estoque_movimentacoes
        Schema::create('estoque_movimentacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estoque_id')->constrained('estoque')->restrictOnDelete();
            $table->enum('tipo', ['entrada', 'saida']);
            $table->decimal('quantidade', 10, 3);
            $table->foreignId('pedido_merenda_id')->nullable()->constrained('pedidos_merenda')->nullOnDelete();
            $table->text('observacao')->nullable();
            $table->string('registrado_por')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estoque_movimentacoes');
        Schema::dropIfExists('estoque');
    }
};
