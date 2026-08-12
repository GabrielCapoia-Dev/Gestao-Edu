<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->string('codigo', 100)->index();
            $table->string('nome', 150);
            $table->timestamps();

            $table->unique(['escola_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lotacoes');
    }
};
