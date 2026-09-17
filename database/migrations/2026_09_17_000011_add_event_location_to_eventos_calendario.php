<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos_calendario', function (Blueprint $table): void {
            if (! Schema::hasColumn('eventos_calendario', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('local');
            }

            if (! Schema::hasColumn('eventos_calendario', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('eventos_calendario', function (Blueprint $table): void {
            if (Schema::hasColumn('eventos_calendario', 'latitude')) {
                $table->dropColumn('latitude');
            }

            if (Schema::hasColumn('eventos_calendario', 'longitude')) {
                $table->dropColumn('longitude');
            }
        });
    }
};
