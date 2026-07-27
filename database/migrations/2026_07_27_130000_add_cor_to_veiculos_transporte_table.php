<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veiculos_transporte', function (Blueprint $table): void {
            $table->string('cor', 7)
                ->default('#2563EB')
                ->after('identificacao');
        });
    }

    public function down(): void
    {
        Schema::table('veiculos_transporte', function (Blueprint $table): void {
            $table->dropColumn('cor');
        });
    }
};
