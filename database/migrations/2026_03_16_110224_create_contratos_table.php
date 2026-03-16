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
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_empresa_contratada')
                ->constrained('empresas_contratadas')
                ->restrictOnDelete();

            $table->string('numero_contrato')->unique();
            $table->date('data_inicio');
            $table->date('data_vencimento')->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
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
        Schema::dropIfExists('contratos');
    }
};
