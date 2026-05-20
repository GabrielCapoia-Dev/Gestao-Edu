<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'escolas';

    private const EMAIL_UNIQUE = 'escolas_email_unique';

    public function up(): void
    {
        if (Schema::hasIndex(self::TABLE, self::EMAIL_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::EMAIL_UNIQUE);
            });
        }

        $this->reativarCodigosSemVersaoAtiva();
    }

    public function down(): void
    {
        if (
            ! Schema::hasIndex(self::TABLE, self::EMAIL_UNIQUE, 'unique')
            && ! $this->emailTemDuplicados()
        ) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique('email', self::EMAIL_UNIQUE);
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

    private function emailTemDuplicados(): bool
    {
        return DB::table(self::TABLE)
            ->select('email')
            ->whereNotNull('email')
            ->groupBy('email')
            ->havingRaw('count(*) > 1')
            ->exists();
    }
};
