<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_manutencao_opcoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_manutencao_id')
                ->constrained('tipo_manutencao')
                ->cascadeOnDelete();
            $table->string('texto');
            $table->boolean('ativo')->default(true);
            $table->string('alterado_por')->nullable();
            $table->timestamps();

            $table->index(['tipo_manutencao_id', 'ativo']);
            $table->index('texto');
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->date('data_identificacao_problema')
                ->nullable()
                ->after('data_solicitacao');

            $table->foreignId('pedido_principal_id')
                ->nullable()
                ->after('id')
                ->constrained('pedidos')
                ->nullOnDelete();

            $table->boolean('is_pedido_adicional')
                ->default(false)
                ->after('pedido_principal_id');

            $table->index(['pedido_principal_id', 'is_pedido_adicional']);
            $table->index('data_identificacao_problema');
        });

        Schema::create('pedido_problemas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')
                ->constrained('pedidos')
                ->cascadeOnDelete();
            $table->foreignId('tipo_manutencao_id')
                ->nullable()
                ->constrained('tipo_manutencao')
                ->nullOnDelete();
            $table->foreignId('tipo_manutencao_opcao_id')
                ->nullable()
                ->constrained('tipo_manutencao_opcoes')
                ->nullOnDelete();
            $table->string('texto_problema');
            $table->timestamps();

            $table->index(['pedido_id', 'tipo_manutencao_opcao_id']);
            $table->index('tipo_manutencao_id');
        });

        Schema::table('feedback_pedidos', function (Blueprint $table) {
            $table->boolean('reabrir_pedido')
                ->default(false)
                ->after('descricao');
        });

        Schema::create('feedback_pedido_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_pedido_id')
                ->constrained('feedback_pedidos')
                ->cascadeOnDelete();
            $table->foreignId('pedido_id')
                ->constrained('pedidos')
                ->cascadeOnDelete();
            $table->foreignId('pedido_problema_id')
                ->nullable()
                ->constrained('pedido_problemas')
                ->nullOnDelete();
            $table->unsignedTinyInteger('valor');
            $table->string('resultado')->nullable();
            $table->text('comentario')->nullable();
            $table->timestamps();

            $table->index(['feedback_pedido_id', 'pedido_id']);
            $table->index(['pedido_problema_id', 'resultado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_pedido_itens');

        Schema::table('feedback_pedidos', function (Blueprint $table) {
            $table->dropColumn('reabrir_pedido');
        });

        Schema::dropIfExists('pedido_problemas');

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropForeign(['pedido_principal_id']);
            $table->dropIndex(['pedido_principal_id', 'is_pedido_adicional']);
            $table->dropIndex(['data_identificacao_problema']);
            $table->dropColumn([
                'pedido_principal_id',
                'is_pedido_adicional',
                'data_identificacao_problema',
            ]);
        });

        Schema::dropIfExists('tipo_manutencao_opcoes');
    }
};
