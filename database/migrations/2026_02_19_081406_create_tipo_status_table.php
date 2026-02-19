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
        | Tabela: tipo_status
        |--------------------------------------------------------------------------
        */
        Schema::create('tipo_status', function (Blueprint $table) {
            $table->id();

            $table->string('nome', 100);
            $table->string('cor')->nullable();

            $table->boolean('finaliza_pedido')->default(false);
            $table->boolean('cancela_pedido')->default(false);

            $table->boolean('ativo')->default(true);

            // Versionamento
            $table->foreignId('registro_anterior_id')
                ->nullable()
                ->constrained('tipo_status')
                ->nullOnDelete();

            $table->timestamps();

            // Índices
            $table->index(['nome', 'ativo']);
            $table->index('registro_anterior_id');
        });

        /*
        |--------------------------------------------------------------------------
        | Tabela Pivot: setor_tipo_status
        |--------------------------------------------------------------------------
        */
        Schema::create('setor_tipo_status', function (Blueprint $table) {
            $table->id();

            $table->foreignId('setor_id')
                ->constrained('setor')
                ->cascadeOnDelete();

            $table->foreignId('tipo_status_id')
                ->constrained('tipo_status')
                ->cascadeOnDelete();

            // Evita duplicação de vínculo
            $table->unique(['setor_id', 'tipo_status_id']);

            // Índices explícitos (performance)
            $table->index('setor_id');
            $table->index('tipo_status_id');
        });
    }

    public function down(): void
    {
        // Ordem inversa de criação
        Schema::dropIfExists('setor_tipo_status');
        Schema::dropIfExists('tipo_status');
    }
};
