<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            $table->index(
                ['ativo', 'is_pedido_adicional', 'data_prevista'],
                'idx_pedidos_ativo_adicional_prevista',
            );
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            $table->dropIndex('idx_pedidos_ativo_adicional_prevista');
        });
    }
};
