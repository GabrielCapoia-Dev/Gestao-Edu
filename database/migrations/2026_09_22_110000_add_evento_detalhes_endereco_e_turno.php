<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('eventos_calendario', 'endereco_mapa')) {
            Schema::table('eventos_calendario', function (Blueprint $table): void {
                $table->string('endereco_mapa', 500)->nullable()->after('local');
            });
        }

        if (! Schema::hasColumn('evento_calendario_participantes_snapshot', 'turno')) {
            Schema::table('evento_calendario_participantes_snapshot', function (Blueprint $table): void {
                $table->string('turno', 80)->nullable()->after('cargo_nome');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('evento_calendario_participantes_snapshot', 'turno')) {
            Schema::table('evento_calendario_participantes_snapshot', function (Blueprint $table): void {
                $table->dropColumn('turno');
            });
        }

        if (Schema::hasColumn('eventos_calendario', 'endereco_mapa')) {
            Schema::table('eventos_calendario', function (Blueprint $table): void {
                $table->dropColumn('endereco_mapa');
            });
        }
    }
};
