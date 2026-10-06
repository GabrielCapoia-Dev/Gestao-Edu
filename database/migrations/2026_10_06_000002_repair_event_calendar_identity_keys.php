<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Event creation writes to each of these tables in one transaction.
     * The Hub schema lost their identity keys even though their create migrations ran.
     *
     * @var list<string>
     */
    private const TABLES = [
        'eventos_calendario',
        'evento_calendario_historicos',
        'evento_calendario_publico_regras',
        'evento_calendario_participantes_snapshot',
        'evento_calendario_alunos_snapshot',
        'evento_calendario_escolas',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->garantirIdAutoIncremental($table);
        }
    }

    public function down(): void
    {
        // Identity keys are required for safe event creation and are not rolled back.
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
