<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas_contratadas', function (Blueprint $table) {
            $table->foreignId('setor_id')
                ->nullable()
                ->after('telefone')
                ->constrained('setor')
                ->nullOnDelete();

            $table->index(['setor_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::table('empresas_contratadas', function (Blueprint $table) {
            $table->dropForeign(['setor_id']);
            $table->dropIndex(['setor_id', 'ativo']);
            $table->dropColumn('setor_id');
        });
    }
};
