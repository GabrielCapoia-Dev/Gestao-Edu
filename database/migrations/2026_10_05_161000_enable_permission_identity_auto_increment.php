<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['permissions', 'roles'] as $table) {
            $this->garantirIdAutoIncremental($table);
        }
    }

    public function down(): void
    {
        // Não remover a geração automática de IDs restaurada no schema legado.
    }

    private function garantirIdAutoIncremental(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $id = collect(Schema::getColumns($table))->firstWhere('name', 'id');

        if (! $id) {
            throw new RuntimeException("A tabela {$table} não possui a coluna id.");
        }

        $hasPrimaryId = collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => (bool) ($index['primary'] ?? false)
                && ($index['columns'] ?? []) === ['id']);

        if ($hasPrimaryId && (bool) ($id['auto_increment'] ?? false)) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException("A coluna id de {$table} precisa ser AUTO_INCREMENT no MySQL.");
        }

        $idsInvalidos = DB::table($table)
            ->select('id')
            ->groupBy('id')
            ->havingRaw('COUNT(*) > 1')
            ->exists() || DB::table($table)->whereNull('id')->exists();

        if ($idsInvalidos) {
            throw new RuntimeException("Não foi possível restaurar a geração de IDs em {$table}: existem IDs ausentes ou duplicados.");
        }

        $alter = "ALTER TABLE `{$table}` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT";

        if (! $hasPrimaryId) {
            $alter .= ', ADD PRIMARY KEY (`id`)';
        }

        DB::statement($alter);
    }
};
