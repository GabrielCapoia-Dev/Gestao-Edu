<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['permissions', 'roles'] as $table) {
            $this->garantirChavePrimariaId($table);
        }
    }

    public function down(): void
    {
        // Reparação estrutural de um schema legado; não é seguro remover as chaves.
    }

    private function garantirChavePrimariaId(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $possuiChavePrimariaId = collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => (bool) ($index['primary'] ?? false)
                && ($index['columns'] ?? []) === ['id']);

        if ($possuiChavePrimariaId) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException("A tabela {$table} precisa ter uma chave primária em id.");
        }

        $idsDuplicados = DB::table($table)
            ->select('id')
            ->groupBy('id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($idsDuplicados || DB::table($table)->whereNull('id')->exists()) {
            throw new RuntimeException("Não foi possível restaurar a chave primária de {$table}: existem IDs ausentes ou duplicados.");
        }

        DB::statement("ALTER TABLE `{$table}` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)");
    }
};
