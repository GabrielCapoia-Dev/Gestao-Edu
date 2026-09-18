<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos_calendario', function (Blueprint $table): void {
            $table->string('publico_tipo', 16)->default('legado')->after('enviar_todas_escolas');
            $table->index('publico_tipo', 'eventos_calendario_publico_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::table('eventos_calendario', function (Blueprint $table): void {
            $table->dropIndex('eventos_calendario_publico_tipo_idx');
            $table->dropColumn('publico_tipo');
        });
    }
};
