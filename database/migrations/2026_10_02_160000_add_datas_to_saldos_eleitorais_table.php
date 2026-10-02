<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saldos_eleitorais', function (Blueprint $table): void {
            $table->json('datas')->nullable()->after('dias');
        });
    }

    public function down(): void
    {
        Schema::table('saldos_eleitorais', function (Blueprint $table): void {
            $table->dropColumn('datas');
        });
    }
};
