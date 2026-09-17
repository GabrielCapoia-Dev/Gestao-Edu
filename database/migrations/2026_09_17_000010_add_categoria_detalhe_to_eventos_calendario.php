<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('eventos_calendario', 'categoria_detalhe')) {
            Schema::table('eventos_calendario', function (Blueprint $table): void {
                $table->string('categoria_detalhe', 160)->nullable()->after('categoria');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('eventos_calendario', 'categoria_detalhe')) {
            Schema::table('eventos_calendario', function (Blueprint $table): void {
                $table->dropColumn('categoria_detalhe');
            });
        }
    }
};
