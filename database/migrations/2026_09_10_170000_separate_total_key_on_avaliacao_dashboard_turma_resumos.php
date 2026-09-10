<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('avaliacao_dashboard_turma_resumos')
            ->where('componente_chave', 0)
            ->whereNull('componente_curricular_id')
            ->update(['componente_chave' => 4294967295]);
    }

    public function down(): void
    {
        DB::table('avaliacao_dashboard_turma_resumos')
            ->where('componente_chave', 4294967295)
            ->delete();
    }
};
