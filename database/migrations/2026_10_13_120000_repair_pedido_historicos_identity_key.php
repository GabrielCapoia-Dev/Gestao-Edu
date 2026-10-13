<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pedido_historicos') || ! Schema::hasColumn('pedido_historicos', 'id')) {
            return;
        }

        DB::statement(
            'ALTER TABLE `pedido_historicos` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT'
        );
    }

    public function down(): void
    {
        // A chave primária deve continuar sendo gerada pelo banco após a correção.
    }
};
