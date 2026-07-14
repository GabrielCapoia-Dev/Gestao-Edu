<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avaliacoes', function (Blueprint $table): void {
            $table->date('data_inicio_preenchimento')->nullable()->after('data_fim');
            $table->date('data_fim_preenchimento')->nullable()->after('data_inicio_preenchimento');
            $table->index(
                ['status', 'data_inicio_preenchimento', 'data_fim_preenchimento'],
                'idx_avaliacoes_status_preenchimento'
            );
        });

        DB::table('avaliacoes')->update([
            'data_inicio_preenchimento' => DB::raw('data_inicio'),
            'data_fim_preenchimento' => DB::raw('data_fim'),
        ]);
    }

    public function down(): void
    {
        Schema::table('avaliacoes', function (Blueprint $table): void {
            $table->dropIndex('idx_avaliacoes_status_preenchimento');
            $table->dropColumn(['data_inicio_preenchimento', 'data_fim_preenchimento']);
        });
    }
};
