<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('itens', function (Blueprint $table) {
            $table->string('codigo')->nullable()->after('nome');
        });

        DB::table('itens')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $item): void {
                DB::table('itens')
                    ->where('id', $item->id)
                    ->update([
                        'codigo' => 'ITM-' . str_pad((string) $item->id, 6, '0', STR_PAD_LEFT),
                    ]);
            });

        Schema::table('itens', function (Blueprint $table) {
            $table->unique('codigo');
        });
    }

    public function down(): void
    {
        Schema::table('itens', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->dropColumn('codigo');
        });
    }
};
