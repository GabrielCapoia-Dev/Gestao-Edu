<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escolas', function (Blueprint $table): void {
            $table->boolean('nao_e_escola')
                ->default(false)
                ->after('ativo');

            $table->index(
                ['nao_e_escola', 'ativo', 'nome'],
                'escolas_tipo_ativo_nome_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('escolas', function (Blueprint $table): void {
            $table->dropIndex('escolas_tipo_ativo_nome_index');
            $table->dropColumn('nao_e_escola');
        });
    }
};
