<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Tabela: pedidos
        |--------------------------------------------------------------------------
        */
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();

            $table->string('numero_protocolo')->unique();
            $table->text('descricao_pedido');

            $table->foreignId('tipo_manutencao_id')
                ->constrained('tipo_manutencao')
                ->cascadeOnDelete();

            $table->foreignId('tipo_status_id')
                ->constrained('tipo_status')
                ->cascadeOnDelete();

            $table->string('nivel_prioridade')->nullable();
            $table->index('nivel_prioridade');

            $table->foreignId('escola_id')
                ->nullable()
                ->constrained('escolas')
                ->cascadeOnDelete();

            $table->foreignId('solicitante_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('responsavel_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('setor_id')
                ->nullable()
                ->constrained('setor')
                ->nullOnDelete();

            $table->foreignId('empresa_contratada_id')
                ->nullable()
                ->constrained('empresas_contratadas')
                ->nullOnDelete();

            $table->date('data_solicitacao');
            $table->date('data_prevista')->nullable();
            $table->date('data_entrega')->nullable();

            $table->integer('quantidade_dias_prorrogado')->default(0);

            $table->boolean('ativo')->default(true);

            $table->timestamps();

            $table->index('tipo_status_id');
            $table->index('escola_id');
            $table->index('setor_id');
        });

        /*
        |--------------------------------------------------------------------------
        | Tabela: pedido_historicos
        |--------------------------------------------------------------------------
        */
        Schema::create('pedido_historicos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pedido_id')
                ->constrained('pedidos')
                ->cascadeOnDelete();

            $table->foreignId('status_anterior_id')
                ->nullable()
                ->constrained('tipo_status')
                ->nullOnDelete();

            $table->foreignId('status_novo_id')
                ->constrained('tipo_status')
                ->cascadeOnDelete();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('setor_id')
                ->nullable()
                ->constrained('setor')
                ->nullOnDelete();

            $table->text('descricao_alteracao')->nullable();

            $table->timestamps();

            $table->index('pedido_id');
            $table->index('status_novo_id');
        });

        /*
        |--------------------------------------------------------------------------
        | Tabela: pedido_arquivos
        |--------------------------------------------------------------------------
        */
        Schema::create('pedido_arquivos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pedido_id')
                ->constrained('pedidos')
                ->cascadeOnDelete();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('tipo_arquivo'); // Classificação lógica
            $table->index('tipo_arquivo');

            $table->string('caminho');
            $table->string('nome_original');
            $table->string('mime_type');

            $table->text('descricao')->nullable();

            $table->timestamps();

            $table->index('pedido_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_arquivos');
        Schema::dropIfExists('pedido_historicos');
        Schema::dropIfExists('pedidos');
    }
};
