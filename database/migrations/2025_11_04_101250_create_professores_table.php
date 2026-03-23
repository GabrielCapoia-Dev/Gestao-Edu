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
        Schema::create('professores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_escola')
                ->constrained('escolas')
                ->cascadeOnDelete();

            $table->string('matricula');
            $table->string('nome');
            $table->string('email')->nullable();
            $table->string('telefone')->nullable();

            $table->timestamps();

            // 🔐 UNIQUE composta (regra principal)
            $table->unique(['id_escola', 'matricula']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professors');
    }
};
