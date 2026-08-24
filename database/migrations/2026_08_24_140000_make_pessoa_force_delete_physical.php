<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->tornarReferenciaNullableComSetNull('alunos', 'id_professor', 'professores');
        $this->tornarReferenciaNullableComSetNull('reservas_veiculos', 'usuario_id', 'users');
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Migration forward-only: professores e usuários excluídos podem possuir históricos preservados sem referência ativa.',
        );
    }

    private function tornarReferenciaNullableComSetNull(string $tabela, string $coluna, string $referenciada): void
    {
        if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, $coluna)) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            Schema::table($tabela, function (Blueprint $table) use ($coluna): void {
                $table->dropForeign([$coluna]);
            });
            Schema::table($tabela, function (Blueprint $table) use ($coluna): void {
                $table->unsignedBigInteger($coluna)->nullable()->change();
            });
            Schema::table($tabela, function (Blueprint $table) use ($coluna, $referenciada): void {
                $table->foreign($coluna)->references('id')->on($referenciada)->nullOnDelete();
            });

            return;
        }

        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME AS nome
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME = ?',
            [$tabela, $coluna, $referenciada],
        );

        foreach ($constraints as $constraint) {
            $nome = str_replace('`', '``', (string) $constraint->nome);
            DB::statement("ALTER TABLE `{$tabela}` DROP FOREIGN KEY `{$nome}`");
        }

        DB::statement("ALTER TABLE `{$tabela}` MODIFY `{$coluna}` BIGINT UNSIGNED NULL");
        DB::statement(
            "ALTER TABLE `{$tabela}` ADD FOREIGN KEY (`{$coluna}`) REFERENCES `{$referenciada}` (`id`) ON DELETE SET NULL",
        );
    }
};
