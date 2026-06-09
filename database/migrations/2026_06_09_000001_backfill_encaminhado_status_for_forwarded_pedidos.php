<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('pedidos')
            || ! Schema::hasTable('tipo_status')
            || ! Schema::hasTable('setor')
        ) {
            return;
        }

        $statusAbertoId = DB::table('tipo_status')->where('nome', 'Em Aberto')->value('id');
        $statusEncaminhadoId = DB::table('tipo_status')->where('nome', 'Encaminhado ao Setor')->value('id');

        if (! $statusAbertoId || ! $statusEncaminhadoId) {
            return;
        }

        $setorGeralIds = DB::table('setor')
            ->where(function ($query): void {
                if (Schema::hasColumn('setor', 'is_default_root')) {
                    $query->orWhere('is_default_root', true);
                }

                if (Schema::hasColumn('setor', 'recebe_pedidos_iniciais')) {
                    $query->orWhere('recebe_pedidos_iniciais', true);
                }

                $query->orWhereNull('parent_id');
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($setorGeralIds === []) {
            return;
        }

        DB::table('pedidos')
            ->where('tipo_status_id', $statusAbertoId)
            ->whereNotNull('setor_id')
            ->when($setorGeralIds !== [], fn ($query) => $query->whereNotIn('setor_id', $setorGeralIds))
            ->update([
                'tipo_status_id' => $statusEncaminhadoId,
            ]);
    }

    public function down(): void
    {
        //
    }
};
