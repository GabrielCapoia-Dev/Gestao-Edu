<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_merenda', function (Blueprint $table) {
            $table->id();

            $table->string('status')->default('aguardando');
            $table->text('observacoes')->nullable();
            $table->string('criado_por')->nullable();

            $table->timestamps();

            $table->index('status');
        });


        Schema::create('pedido_merenda_itens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pedido_merenda_id')
                ->constrained('pedidos_merenda')
                ->cascadeOnDelete();

            $table->foreignId('contrato_item_id')
                ->constrained('contrato_item')
                ->restrictOnDelete();

            $table->decimal('quantidade_pedida', 10, 3);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_merenda_itens');
        Schema::dropIfExists('pedidos_merenda');
    }
};
