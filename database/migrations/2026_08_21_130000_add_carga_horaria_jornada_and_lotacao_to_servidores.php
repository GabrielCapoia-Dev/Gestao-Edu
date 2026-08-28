<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('servidores')) {
            return;
        }

        if (! Schema::hasColumn('servidores', 'carga_horaria')) {
            Schema::table('servidores', function (Blueprint $table): void {
                $table->unsignedTinyInteger('carga_horaria')->nullable()->after('observacoes');
            });
        }

        if (! Schema::hasColumn('servidores', 'jornada')) {
            Schema::table('servidores', function (Blueprint $table): void {
                $table->boolean('jornada')->nullable()->after('carga_horaria');
            });
        }

        if (! Schema::hasColumn('servidores', 'lotacao_id')) {
            Schema::table('servidores', function (Blueprint $table): void {
                $table->foreignId('lotacao_id')
                    ->nullable()
                    ->after('jornada')
                    ->constrained('lotacoes')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('servidores')) {
            return;
        }

        if (Schema::hasColumn('servidores', 'lotacao_id')) {
            Schema::table('servidores', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('lotacao_id');
            });
        }

        $columns = collect(['carga_horaria', 'jornada'])
            ->filter(fn (string $column): bool => Schema::hasColumn('servidores', $column))
            ->all();

        if ($columns !== []) {
            Schema::table('servidores', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
