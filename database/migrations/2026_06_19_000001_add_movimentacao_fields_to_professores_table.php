<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professores', function (Blueprint $table): void {
            if (! Schema::hasColumn('professores', 'ativo')) {
                $table->boolean('ativo')->default(true)->after('telefone')->index();
            }

            if (! Schema::hasColumn('professores', 'desativado_em')) {
                $table->timestamp('desativado_em')->nullable()->after('ativo');
            }

            if (! Schema::hasColumn('professores', 'desativado_por_id')) {
                $table->foreignId('desativado_por_id')
                    ->nullable()
                    ->after('desativado_em')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('professores', 'motivo_desativacao')) {
                $table->text('motivo_desativacao')->nullable()->after('desativado_por_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('professores', function (Blueprint $table): void {
            if (Schema::hasColumn('professores', 'motivo_desativacao')) {
                $table->dropColumn('motivo_desativacao');
            }

            if (Schema::hasColumn('professores', 'desativado_por_id')) {
                $table->dropConstrainedForeignId('desativado_por_id');
            }

            if (Schema::hasColumn('professores', 'desativado_em')) {
                $table->dropColumn('desativado_em');
            }

            if (Schema::hasColumn('professores', 'ativo')) {
                $table->dropColumn('ativo');
            }
        });
    }
};
