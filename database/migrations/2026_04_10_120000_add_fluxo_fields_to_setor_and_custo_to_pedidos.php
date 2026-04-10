<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setor', function (Blueprint $table) {
            $table->boolean('recebe_pedidos_iniciais')
                ->default(false)
                ->after('status');

            $table->foreignId('encaminha_pedido_para_setor_id')
                ->nullable()
                ->after('recebe_pedidos_iniciais')
                ->constrained('setor')
                ->nullOnDelete();
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->decimal('valor_custo', 12, 2)
                ->nullable()
                ->after('empresa_contratada_id');
        });

        $setorGeralId = DB::table('setor')
            ->where('ativo', true)
            ->orderByRaw("case when nome = 'Educação' then 0 else 1 end")
            ->orderBy('id')
            ->value('id');

        if ($setorGeralId) {
            DB::table('setor')
                ->where('id', $setorGeralId)
                ->update([
                    'recebe_pedidos_iniciais' => true,
                    'encaminha_pedido_para_setor_id' => null,
                ]);

            DB::table('setor')
                ->where('id', '!=', $setorGeralId)
                ->whereNull('encaminha_pedido_para_setor_id')
                ->update(['encaminha_pedido_para_setor_id' => $setorGeralId]);
        }
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('valor_custo');
        });

        Schema::table('setor', function (Blueprint $table) {
            $table->dropConstrainedForeignId('encaminha_pedido_para_setor_id');
            $table->dropColumn('recebe_pedidos_iniciais');
        });
    }
};
