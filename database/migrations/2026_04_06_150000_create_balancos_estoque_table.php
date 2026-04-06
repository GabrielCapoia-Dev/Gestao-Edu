<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balancos_estoque', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable()->unique();
            $table->string('status', 30);
            $table->dateTime('data_agendada');
            $table->text('observacao_inicial')->nullable();
            $table->dateTime('iniciado_em')->nullable();
            $table->dateTime('concluido_em')->nullable();
            $table->dateTime('cancelado_em')->nullable();
            $table->foreignId('criado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('iniciado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('concluido_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'data_agendada']);
        });

        Schema::create('balanco_estoque_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('balanco_estoque_id')->constrained('balancos_estoque')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('itens')->restrictOnDelete();
            $table->boolean('incluido_na_contagem')->default(true);
            $table->decimal('saldo_sistema_antes', 10, 3)->default(0);
            $table->decimal('quantidade_contada', 10, 3)->nullable();
            $table->decimal('saldo_final', 10, 3)->nullable();
            $table->decimal('diferenca', 10, 3)->nullable();
            $table->text('observacao_contagem')->nullable();
            $table->dateTime('contado_em')->nullable();
            $table->foreignId('contado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['balanco_estoque_id', 'item_id'], 'balanco_item_unique');
            $table->index(['balanco_estoque_id', 'incluido_na_contagem'], 'balanco_item_inclusao_index');
            $table->index('item_id');
        });

        Schema::create('balanco_estoque_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('balanco_estoque_id')->constrained('balancos_estoque')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->text('descricao');
            $table->json('dados')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['balanco_estoque_id', 'created_at'], 'balanco_eventos_ordem_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balanco_estoque_eventos');
        Schema::dropIfExists('balanco_estoque_itens');
        Schema::dropIfExists('balancos_estoque');
    }
};
