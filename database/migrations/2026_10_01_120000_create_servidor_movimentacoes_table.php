<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servidor_movimentacoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 80);
            $table->json('alteracoes');
            $table->timestamp('ocorrido_em')->useCurrent();
            $table->index(['servidor_id', 'ocorrido_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servidor_movimentacoes');
    }
};
