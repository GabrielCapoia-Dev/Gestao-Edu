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

        $primaryKeyExists = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'pedido_historicos')
            ->where('CONSTRAINT_NAME', 'PRIMARY')
            ->where('COLUMN_NAME', 'id')
            ->exists();

        if (! $primaryKeyExists) {
            DB::statement('ALTER TABLE `pedido_historicos` ADD PRIMARY KEY (`id`)');
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
