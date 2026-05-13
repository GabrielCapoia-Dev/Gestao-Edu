<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tipo_status')) {
            return;
        }

        $now = now();

        if (DB::table('tipo_status')->where('nome', 'Pedido Adicional')->exists()) {
            DB::table('tipo_status')
                ->where('nome', 'Pedido Adicional')
                ->update([
                    'cor' => '#64748b',
                    'finaliza_pedido' => false,
                    'cancela_pedido' => false,
                    'ativo' => true,
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('tipo_status')->insert([
            'nome' => 'Pedido Adicional',
            'cor' => '#64748b',
            'finaliza_pedido' => false,
            'cancela_pedido' => false,
            'ativo' => true,
            'updated_at' => $now,
            'created_at' => $now,
        ]);
    }

    public function down(): void
    {
        //
    }
};
