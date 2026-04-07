<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estoque', function (Blueprint $table) {
            $table->decimal('quantidade_reservada', 10, 3)->default(0)->after('quantidade');
        });
    }

    public function down(): void
    {
        Schema::table('estoque', function (Blueprint $table) {
            $table->dropColumn('quantidade_reservada');
        });
    }
};
