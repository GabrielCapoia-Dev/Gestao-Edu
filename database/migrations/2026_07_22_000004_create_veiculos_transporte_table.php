<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veiculos_transporte', function (Blueprint $table): void {
            $table->id();
            $table->string('placa', 7)->unique();
            $table->string('identificacao', 120)->nullable();
            $table->unsignedSmallInteger('capacidade_passageiros');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['ativo', 'identificacao'], 'idx_veic_transp_ativo_ident');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculos_transporte');
    }
};
