<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'turma_componente_professor';

    private const CHECK_CONSTRAINT = 'chk_tcp_professor_tem_professor';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        DB::table(self::TABLE)
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNotNull('professor_id')
                        ->where('tem_professor', false);
                })->orWhere(function ($query): void {
                    $query->whereNull('professor_id')
                        ->where('tem_professor', true);
                });
            })
            ->update([
                'tem_professor' => DB::raw('CASE WHEN professor_id IS NULL THEN 0 ELSE 1 END'),
                'updated_at' => now(),
            ]);

        $this->addCheckConstraint();
    }

    public function down(): void
    {
        $this->dropCheckConstraint();
    }

    private function addCheckConstraint(): void
    {
        if (! $this->supportsCheckConstraints() || $this->checkConstraintExists()) {
            return;
        }

        DB::statement(
            'ALTER TABLE `'.self::TABLE.'` ADD CONSTRAINT `'.self::CHECK_CONSTRAINT.'` '
            .'CHECK ((`professor_id` IS NULL AND `tem_professor` = 0) '
            .'OR (`professor_id` IS NOT NULL AND `tem_professor` = 1))'
        );
    }

    private function dropCheckConstraint(): void
    {
        if (! $this->supportsCheckConstraints() || ! $this->checkConstraintExists()) {
            return;
        }

        try {
            DB::statement('ALTER TABLE `'.self::TABLE.'` DROP CHECK `'.self::CHECK_CONSTRAINT.'`');
        } catch (Throwable) {
            DB::statement('ALTER TABLE `'.self::TABLE.'` DROP CONSTRAINT `'.self::CHECK_CONSTRAINT.'`');
        }
    }

    private function supportsCheckConstraints(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function checkConstraintExists(): bool
    {
        if (! $this->supportsCheckConstraints()) {
            return false;
        }

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', self::TABLE)
            ->where('CONSTRAINT_NAME', self::CHECK_CONSTRAINT)
            ->exists();
    }
};
