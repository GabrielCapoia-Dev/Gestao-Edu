<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'escolas';

    private const NOME_UNIQUE = 'escolas_nome_unique';

    public function up(): void
    {
        if (Schema::hasIndex(self::TABLE, self::NOME_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::NOME_UNIQUE);
            });
        }

        $this->reativarCodigosSemVersaoAtiva();
    }

    public function down(): void
    {
        if (
            ! Schema::hasIndex(self::TABLE, self::NOME_UNIQUE, 'unique')
            && ! $this->nomeTemDuplicados()
        ) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique('nome', self::NOME_UNIQUE);
            });
        }
    }

    private function reativarCodigosSemVersaoAtiva(): void
    {
        if (
            ! Schema::hasColumn(self::TABLE, 'codigo')
            || ! Schema::hasColumn(self::TABLE, 'ativo')
        ) {
            return;
        }

        $codigosSemAtiva = DB::table(self::TABLE)
            ->select('codigo')
            ->whereNotNull('codigo')
            ->groupBy('codigo')
            ->havingRaw('SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) = 0')
            ->pluck('codigo');

        foreach ($codigosSemAtiva as $codigo) {
            $id = DB::table(self::TABLE)
                ->where('codigo', $codigo)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->value('id');

            if ($id) {
                DB::table(self::TABLE)
                    ->where('id', $id)
                    ->update([
                        'ativo' => true,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    private function nomeTemDuplicados(): bool
    {
        return DB::table(self::TABLE)
            ->select('nome')
            ->whereNotNull('nome')
            ->groupBy('nome')
            ->havingRaw('count(*) > 1')
            ->exists();
    }
};
