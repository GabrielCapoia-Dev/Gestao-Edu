<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('empresas_contratadas', function (Blueprint $table) {
            $table->id();

            $table->string('nome');
            $table->string('cnpj')->unique();
            $table->string('email')->nullable();
            $table->string('responsavel')->nullable();
            $table->string('telefone')->nullable();

            $table->string('cep')->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero')->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado', 2)->nullable();

            $table->boolean('ativo')->default(true);

            $table->foreignId('registro_anterior_id')
                ->nullable()
                ->constrained('empresas_contratadas')
                ->nullOnDelete();

            $table->string('alterado_por')->nullable();

            $table->timestamps();

            $table->index('ativo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas_contratadas');
    }
};
