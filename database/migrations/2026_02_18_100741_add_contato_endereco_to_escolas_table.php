<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escolas', function (Blueprint $table) {

            // Contato
            $table->string('email')->nullable()->after('nome');
            $table->string('telefone')->nullable()->after('email');

            // Endereço
            $table->string('logradouro')->nullable();
            $table->string('numero')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('complemento')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('escolas', function (Blueprint $table) {

            $table->dropColumn([
                'email',
                'telefone',
                'logradouro',
                'numero',
                'bairro',
                'cep',
                'cidade',
                'estado',
                'complemento',
            ]);
        });
    }
};
