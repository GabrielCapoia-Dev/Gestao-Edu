<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'avaliacao_dashboard_pendencias',
            'avaliacao_dashboard_escopo_pendencias',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }

    public function down(): void
    {
        // Pendências de uma consolidação desativada não devem ser recriadas.
    }
};
