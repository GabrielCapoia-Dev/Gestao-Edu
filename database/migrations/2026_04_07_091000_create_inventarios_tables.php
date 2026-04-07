<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->restrictOnDelete();
            $table->string('nome')->nullable();
            $table->boolean('ativo')->default(true);
            $table->foreignId('criado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('escola_id');
        });

        Schema::create('inventario_estoques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_id')->constrained('inventarios')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('itens')->restrictOnDelete();
            $table->decimal('quantidade', 10, 3)->default(0);
            $table->timestamps();

            $table->unique(['inventario_id', 'item_id']);
        });

        Schema::create('inventario_romaneios', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable()->unique();
            $table->text('observacoes')->nullable();
            $table->foreignId('gerado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('gerado_em')->nullable();
            $table->timestamps();
        });

        Schema::create('inventario_pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_id')->constrained('inventarios')->cascadeOnDelete();
            $table->foreignId('escola_id')->constrained('escolas')->restrictOnDelete();
            $table->string('status', 30);
            $table->text('observacao_escola')->nullable();
            $table->text('observacao_gestor')->nullable();
            $table->text('observacao_conferencia')->nullable();
            $table->foreignId('inventario_romaneio_id')->nullable()->constrained('inventario_romaneios')->nullOnDelete();
            $table->foreignId('solicitado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprovado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('entregue_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('aprovado_em')->nullable();
            $table->dateTime('em_andamento_em')->nullable();
            $table->dateTime('entregue_em')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['inventario_id', 'status']);
        });

        Schema::create('inventario_pedido_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_pedido_id')->constrained('inventario_pedidos')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('itens')->restrictOnDelete();
            $table->decimal('quantidade_solicitada', 10, 3);
            $table->decimal('quantidade_aprovada', 10, 3)->nullable();
            $table->decimal('quantidade_recebida', 10, 3)->nullable();
            $table->text('observacao_solicitacao')->nullable();
            $table->text('observacao_aprovacao')->nullable();
            $table->text('observacao_conferencia')->nullable();
            $table->string('status', 30)->default('pendente');
            $table->timestamps();

            $table->unique(['inventario_pedido_id', 'item_id'], 'inventario_pedido_item_unique');
        });

        Schema::create('inventario_movimentacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_estoque_id')->constrained('inventario_estoques')->cascadeOnDelete();
            $table->enum('tipo', ['entrada', 'saida', 'transferencia']);
            $table->decimal('quantidade', 10, 3);
            $table->foreignId('inventario_pedido_id')->nullable()->constrained('inventario_pedidos')->nullOnDelete();
            $table->text('observacao')->nullable();
            $table->string('registrado_por')->nullable();
            $table->timestamps();
        });

        Schema::create('inventario_baixas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_estoque_id')->constrained('inventario_estoques')->cascadeOnDelete();
            $table->decimal('quantidade', 10, 3);
            $table->string('motivo', 60);
            $table->text('descricao');
            $table->decimal('saldo_anterior', 10, 3);
            $table->decimal('saldo_posterior', 10, 3);
            $table->string('registrado_por')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_baixas');
        Schema::dropIfExists('inventario_movimentacoes');
        Schema::dropIfExists('inventario_pedido_itens');
        Schema::dropIfExists('inventario_pedidos');
        Schema::dropIfExists('inventario_romaneios');
        Schema::dropIfExists('inventario_estoques');
        Schema::dropIfExists('inventarios');
    }
};
