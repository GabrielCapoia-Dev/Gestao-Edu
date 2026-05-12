<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            $table->index(['ativo', 'data_solicitacao'], 'idx_pedidos_export_ativo_data');
            $table->index(['ativo', 'escola_id', 'data_solicitacao'], 'idx_pedidos_export_escola_data');
            $table->index(['ativo', 'tipo_manutencao_id'], 'idx_pedidos_export_tipo_manut');
            $table->index(['ativo', 'empresa_contratada_id'], 'idx_pedidos_export_empresa');
        });

        Schema::table('estoque_movimentacoes', function (Blueprint $table): void {
            $table->index(['estoque_id', 'created_at'], 'idx_estoque_mov_export_estoque_data');
            $table->index(['tipo', 'created_at'], 'idx_estoque_mov_export_tipo_data');
            $table->index('pedido_merenda_id', 'idx_estoque_mov_export_pedido');
        });

        Schema::table('baixas_estoques', function (Blueprint $table): void {
            $table->index(['estoque_id', 'created_at'], 'idx_baixas_estoque_export_data');
            $table->index(['motivo', 'created_at'], 'idx_baixas_estoque_export_motivo');
        });

        Schema::table('inventario_movimentacoes', function (Blueprint $table): void {
            $table->index(['inventario_estoque_id', 'created_at'], 'idx_inv_mov_export_estoque_data');
            $table->index(['tipo', 'inventario_pedido_id', 'created_at'], 'idx_inv_mov_export_tipo_pedido_data');
        });

        Schema::table('inventario_baixas', function (Blueprint $table): void {
            $table->index(['inventario_estoque_id', 'created_at'], 'idx_inv_baixas_export_estoque_data');
            $table->index(['motivo', 'created_at'], 'idx_inv_baixas_export_motivo');
        });

        Schema::table('feedback_pedidos', function (Blueprint $table): void {
            $table->index('created_at', 'idx_feedback_export_created');
            $table->index(['valor', 'created_at'], 'idx_feedback_export_valor_created');
        });

        Schema::table('avaliacao_respostas', function (Blueprint $table): void {
            $table->index(['avaliacao_id', 'turma_id', 'aluno_id'], 'idx_resp_export_avaliacao_turma_aluno');
            $table->index(['avaliacao_id', 'alternativa_id'], 'idx_resp_export_avaliacao_alternativa');
            $table->index(['avaliacao_id', 'pauta_id', 'aluno_id'], 'idx_resp_export_avaliacao_pauta_aluno');
        });
    }

    public function down(): void
    {
        Schema::table('avaliacao_respostas', function (Blueprint $table): void {
            $table->dropIndex('idx_resp_export_avaliacao_pauta_aluno');
            $table->dropIndex('idx_resp_export_avaliacao_alternativa');
            $table->dropIndex('idx_resp_export_avaliacao_turma_aluno');
        });

        Schema::table('feedback_pedidos', function (Blueprint $table): void {
            $table->dropIndex('idx_feedback_export_valor_created');
            $table->dropIndex('idx_feedback_export_created');
        });

        Schema::table('inventario_baixas', function (Blueprint $table): void {
            $table->dropIndex('idx_inv_baixas_export_motivo');
            $table->dropIndex('idx_inv_baixas_export_estoque_data');
        });

        Schema::table('inventario_movimentacoes', function (Blueprint $table): void {
            $table->dropIndex('idx_inv_mov_export_tipo_pedido_data');
            $table->dropIndex('idx_inv_mov_export_estoque_data');
        });

        Schema::table('baixas_estoques', function (Blueprint $table): void {
            $table->dropIndex('idx_baixas_estoque_export_motivo');
            $table->dropIndex('idx_baixas_estoque_export_data');
        });

        Schema::table('estoque_movimentacoes', function (Blueprint $table): void {
            $table->dropIndex('idx_estoque_mov_export_pedido');
            $table->dropIndex('idx_estoque_mov_export_tipo_data');
            $table->dropIndex('idx_estoque_mov_export_estoque_data');
        });

        Schema::table('pedidos', function (Blueprint $table): void {
            $table->dropIndex('idx_pedidos_export_empresa');
            $table->dropIndex('idx_pedidos_export_tipo_manut');
            $table->dropIndex('idx_pedidos_export_escola_data');
            $table->dropIndex('idx_pedidos_export_ativo_data');
        });
    }
};
