<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alternativas', function (Blueprint $table): void {
            $table->unsignedInteger('ordem_documento')
                ->nullable()
                ->after('descricao_documento');
        });
    }

    public function down(): void
    {
        Schema::table('alternativas', function (Blueprint $table): void {
            $table->dropColumn('ordem_documento');
        });
    }
};
