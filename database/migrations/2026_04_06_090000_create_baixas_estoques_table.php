<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baixas_estoques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estoque_id')->constrained('estoque')->restrictOnDelete();
            $table->decimal('quantidade', 10, 3);
            $table->string('motivo', 60);
            $table->text('descricao');
            $table->decimal('saldo_anterior', 10, 3);
            $table->decimal('saldo_posterior', 10, 3);
            $table->string('registrado_por')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baixas_estoques');
    }
};
