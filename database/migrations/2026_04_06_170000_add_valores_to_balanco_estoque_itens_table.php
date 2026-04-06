<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balanco_estoque_itens', function (Blueprint $table) {
            $table->decimal('valor_unitario_referencia', 10, 2)->default(0)->after('diferenca');
            $table->decimal('valor_impacto', 12, 2)->nullable()->after('valor_unitario_referencia');
        });
    }

    public function down(): void
    {
        Schema::table('balanco_estoque_itens', function (Blueprint $table) {
            $table->dropColumn(['valor_unitario_referencia', 'valor_impacto']);
        });
    }
};
