<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evento_calendario_transporte_alocacoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evento_calendario_id')->constrained('eventos_calendario')->restrictOnDelete();
            $table->foreignId('veiculo_transporte_id')->constrained('veiculos_transporte')->restrictOnDelete();
            $table->foreignId('motorista_id')->constrained('servidores')->restrictOnDelete();
            $table->foreignId('criado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('removido_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('removido_em')->nullable();
            $table->timestamps();

            $table->index(['evento_calendario_id', 'removido_em'], 'idx_evt_transp_aloc_evento_ativo');
            $table->index(
                ['veiculo_transporte_id', 'removido_em', 'evento_calendario_id'],
                'idx_evt_transp_aloc_veiculo_ativo',
            );
            $table->index(
                ['motorista_id', 'removido_em', 'evento_calendario_id'],
                'idx_evt_transp_aloc_motorista_ativo',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_calendario_transporte_alocacoes');
    }
};
