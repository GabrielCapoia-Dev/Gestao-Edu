<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escolas', function (Blueprint $table) {

            // Permitir múltiplos registros da mesma escola
            $table->dropUnique(['codigo']);

            // Controle de versão ativa
            $table->boolean('ativo')->default(true)->after('nome');

            // Referência ao registro anterior
            $table->foreignId('registro_anterior_id')
                ->nullable()
                ->after('ativo')
                ->constrained('escolas')
                ->nullOnDelete();

            // Índice para busca rápida
            $table->index(['codigo', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::table('escolas', function (Blueprint $table) {
            $table->dropForeign(['registro_anterior_id']);
            $table->dropIndex(['codigo', 'ativo']);
            $table->dropColumn(['ativo', 'registro_anterior_id']);
            $table->unique('codigo');
        });
    }
};
