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

            $table->json('encaminha_pedido_para_setor_ids')
                ->nullable()
                ->after('recebe_pedidos_iniciais');
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->decimal('valor_custo', 12, 2)
                ->nullable()
                ->after('empresa_contratada_id');
        });

        $setorGeralId = DB::table('setor')
            ->where('ativo', true)
            ->orderBy('id')
            ->value('id');

        if ($setorGeralId) {
            DB::table('setor')
                ->where('id', $setorGeralId)
                ->update([
                    'recebe_pedidos_iniciais' => true,
                    'encaminha_pedido_para_setor_ids' => json_encode([], JSON_UNESCAPED_UNICODE),
                ]);

            DB::table('setor')
                ->where('id', '!=', $setorGeralId)
                ->whereNull('encaminha_pedido_para_setor_ids')
                ->update([
                    'encaminha_pedido_para_setor_ids' => json_encode([$setorGeralId], JSON_UNESCAPED_UNICODE),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('valor_custo');
        });

        Schema::table('setor', function (Blueprint $table) {
            $table->dropColumn('encaminha_pedido_para_setor_ids');
            $table->dropColumn('recebe_pedidos_iniciais');
        });
    }
};
