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
        Schema::create('setor', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('status')->nullable();
            $table->timestamps();

            $table->boolean('ativo')->default(true);

            $table->foreignId('registro_anterior_id')
                ->nullable()
                ->constrained('setor')
                ->nullOnDelete();

            $table->string('alterado_por')->nullable();

            $table->index(['nome', 'ativo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setors');
    }
};
