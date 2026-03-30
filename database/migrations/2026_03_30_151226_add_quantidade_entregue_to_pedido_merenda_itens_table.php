<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_merenda_itens', function (Blueprint $table) {
            $table->decimal('quantidade_entregue', 10, 3)->default(0)->after('quantidade_pedida');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_merenda_itens', function (Blueprint $table) {
            $table->dropColumn('quantidade_entregue');
        });
    }
};