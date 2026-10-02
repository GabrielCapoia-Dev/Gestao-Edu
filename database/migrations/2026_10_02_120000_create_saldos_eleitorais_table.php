<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saldos_eleitorais', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->foreignId('solicitante_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('aprovador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 20);
            $table->unsignedInteger('dias');
            $table->string('status', 20)->default('pendente');
            $table->boolean('lancamento_manual')->default(false);
            $table->text('observacao')->nullable();
            $table->timestamp('decidido_em')->nullable();
            $table->timestamps();
            $table->index(['tipo', 'status', 'created_at'], 'saldo_eleitoral_status_idx');
            $table->index(['servidor_id', 'status', 'tipo'], 'saldo_eleitoral_servidor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saldos_eleitorais');
    }
};
