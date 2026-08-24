<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pessoa_exclusoes_definitivas')) {
            Schema::create('pessoa_exclusoes_definitivas', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('servidor_id_legado')->index();
                $table->foreignId('executado_por_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->json('resumo');
                $table->timestamp('ocorrido_em');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('servidor_funcao_administrativa')
            && Schema::hasColumn('servidor_funcao_administrativa', 'servidor_id')) {
            $this->tornarServidorIdNullable();
        }

        $this->restaurarEmailNormalizadoNoSqlite();
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Migration forward-only: vínculos funcionais históricos podem não possuir mais uma Pessoa vinculada.',
        );
    }

    private function tornarServidorIdNullable(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('servidor_funcao_administrativa', function (Blueprint $table): void {
                $table->unsignedBigInteger('servidor_id')->nullable()->change();
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
            ['servidor_funcao_administrativa', 'servidor_id', 'servidores'],
        );

        foreach ($constraints as $constraint) {
            $nome = str_replace('`', '``', (string) $constraint->nome);
            DB::statement("ALTER TABLE `servidor_funcao_administrativa` DROP FOREIGN KEY `{$nome}`");
        }

        DB::statement(
            'ALTER TABLE `servidor_funcao_administrativa` MODIFY `servidor_id` BIGINT UNSIGNED NULL',
        );

        DB::statement(
            'ALTER TABLE `servidor_funcao_administrativa`
             ADD CONSTRAINT `fk_servidor_funcao_pessoa_integridade`
             FOREIGN KEY (`servidor_id`) REFERENCES `servidores` (`id`) ON DELETE RESTRICT',
        );
    }

    private function restaurarEmailNormalizadoNoSqlite(): void
    {
        if (DB::getDriverName() !== 'sqlite' || ! Schema::hasColumn('servidores', 'email')) {
            return;
        }

        if (Schema::hasColumn('servidores', 'email_normalizado')) {
            if (Schema::hasIndex('servidores', 'idx_servidores_email_normalizado')) {
                Schema::table('servidores', function (Blueprint $table): void {
                    $table->dropIndex('idx_servidores_email_normalizado');
                });
            }

            Schema::table('servidores', function (Blueprint $table): void {
                $table->dropColumn('email_normalizado');
            });
        }

        Schema::table('servidores', function (Blueprint $table): void {
            $table->string('email_normalizado')
                ->virtualAs("NULLIF(LOWER(TRIM(email)), '')");
        });

        Schema::table('servidores', function (Blueprint $table): void {
            $table->index('email_normalizado', 'idx_servidores_email_normalizado');
        });
    }
};
