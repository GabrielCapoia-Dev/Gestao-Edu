<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saldos_eleitorais', function (Blueprint $table): void {
            $table->foreignId('movimento_origem_id')
                ->nullable()
                ->after('servidor_id')
                ->constrained('saldos_eleitorais')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('saldos_eleitorais', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('movimento_origem_id');
        });
    }
};
