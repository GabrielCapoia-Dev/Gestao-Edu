<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'servidores_user_id_unique';

    public function up(): void
    {
        $duplicado = DB::table('servidores')
            ->select('user_id')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicado) {
            throw new \RuntimeException('Existem Usuários vinculados a mais de uma Pessoa. Execute a auditoria antes da migration.');
        }

        Schema::table('servidores', function (Blueprint $table): void {
            $table->unique('user_id', self::INDEX);
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX);
        });
    }
};
