<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'lotacoes';

    private const OLD_UNIQUE = 'lotacoes_escola_id_codigo_unique';

    private const SCHOOL_INDEX = 'lotacoes_escola_id_index';

    private const GLOBAL_UNIQUE = 'lotacoes_codigo_unique';

    public function up(): void
    {
        $duplicado = DB::table(self::TABLE)
            ->selectRaw('LOWER(codigo) as codigo_normalizado, COUNT(*) as total')
            ->groupByRaw('LOWER(codigo)')
            ->havingRaw('COUNT(*) > 1')
            ->value('codigo_normalizado');

        if ($duplicado !== null) {
            throw new RuntimeException(
                "Não foi possível tornar as lotações únicas: o número {$duplicado} está cadastrado mais de uma vez.",
            );
        }

        if (! Schema::hasIndex(self::TABLE, self::SCHOOL_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->index('escola_id', self::SCHOOL_INDEX);
            });
        }

        if (Schema::hasIndex(self::TABLE, self::OLD_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::OLD_UNIQUE);
            });
        }

        if (! Schema::hasIndex(self::TABLE, self::GLOBAL_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique('codigo', self::GLOBAL_UNIQUE);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex(self::TABLE, self::GLOBAL_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::GLOBAL_UNIQUE);
            });
        }

        if (! Schema::hasIndex(self::TABLE, self::OLD_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique(['escola_id', 'codigo'], self::OLD_UNIQUE);
            });
        }
    }
};
