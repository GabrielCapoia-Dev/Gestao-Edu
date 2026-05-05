<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'avaliacao_informacoes_complementares';

    private const OLD_UNIQUE = 'uniq_avaliacao_turma_aluno_complemento';

    private const NEW_UNIQUE = 'uniq_avaliacao_turma_aluno_componente_complemento';

    private const FOREIGN = 'fk_avic_componente';

    public function up(): void
    {
        if (! Schema::hasColumn(self::TABLE, 'componente_curricular_id')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->foreignId('componente_curricular_id')
                    ->nullable()
                    ->after('aluno_id');
            });
        }

        if (! $this->foreignKeyExists('componente_curricular_id')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->foreign('componente_curricular_id', self::FOREIGN)
                    ->references('id')
                    ->on('componentes_curriculares')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasIndex(self::TABLE, self::OLD_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::OLD_UNIQUE);
            });
        }

        if (! Schema::hasIndex(self::TABLE, self::NEW_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique(
                    ['avaliacao_id', 'turma_id', 'aluno_id', 'componente_curricular_id'],
                    self::NEW_UNIQUE
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex(self::TABLE, self::NEW_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::NEW_UNIQUE);
            });
        }

        $foreignKeyName = $this->foreignKeyName('componente_curricular_id');

        if ($foreignKeyName) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($foreignKeyName): void {
                $table->dropForeign($foreignKeyName);
            });
        }

        if (Schema::hasColumn(self::TABLE, 'componente_curricular_id')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropColumn('componente_curricular_id');
            });
        }

        if (! Schema::hasIndex(self::TABLE, self::OLD_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique(['avaliacao_id', 'turma_id', 'aluno_id'], self::OLD_UNIQUE);
            });
        }
    }

    private function foreignKeyExists(string $column): bool
    {
        return $this->foreignKeyName($column) !== null;
    }

    private function foreignKeyName(string $column): ?string
    {
        return collect(Schema::getForeignKeys(self::TABLE))
            ->map(function (array $foreignKey) use ($column): ?string {
                $columns = $foreignKey['columns'] ?? [];

                if (($foreignKey['name'] ?? null) === self::FOREIGN || in_array($column, $columns, true)) {
                    return $foreignKey['name'] ?? self::FOREIGN;
                }

                return null;
            })
            ->filter()
            ->first();
    }
};
