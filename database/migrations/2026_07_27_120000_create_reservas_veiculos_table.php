<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas_veiculos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('veiculo_transporte_id')
                ->constrained('veiculos_transporte')
                ->restrictOnDelete();
            $table->foreignId('usuario_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('escola_id')
                ->nullable()
                ->constrained('escolas')
                ->nullOnDelete();
            $table->string('local_nome', 255);
            $table->string('atividade', 500);
            $table->dateTime('data_inicio');
            $table->dateTime('data_fim');
            $table->string('status', 20)->default('ativa');
            $table->uuid('grupo_recorrencia')->nullable();
            $table->foreignId('criado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('atualizado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelado_em')->nullable();
            $table->string('motivo_cancelamento', 500)->nullable();
            $table->timestamps();

            $table->index(
                ['veiculo_transporte_id', 'status', 'data_inicio', 'data_fim'],
                'idx_res_veic_disponibilidade',
            );
            $table->index(['escola_id', 'status', 'data_inicio'], 'idx_res_veic_escola');
            $table->index(['usuario_id', 'status', 'data_inicio'], 'idx_res_veic_usuario');
            $table->index('grupo_recorrencia', 'idx_res_veic_grupo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas_veiculos');
    }
};
