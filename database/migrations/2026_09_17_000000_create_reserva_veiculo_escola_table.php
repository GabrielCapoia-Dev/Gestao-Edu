<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserva_veiculo_escola', function (Blueprint $table): void {
            $table->foreignId('reserva_veiculo_id')->constrained('reservas_veiculos')->cascadeOnDelete();
            $table->foreignId('escola_id')->constrained('escolas')->restrictOnDelete();
            $table->primary(['reserva_veiculo_id', 'escola_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva_veiculo_escola');
    }
};
