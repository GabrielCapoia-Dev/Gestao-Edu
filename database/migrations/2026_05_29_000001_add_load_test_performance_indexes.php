<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('users', ['setor_id', 'updated_at'], 'idx_users_setor_updated');
        $this->addIndex('users', ['id_escola', 'updated_at'], 'idx_users_escola_updated');
        $this->addIndex('alunos', ['id_turma', 'nome'], 'idx_alunos_turma_nome');
        $this->addIndex('turmas', ['id_escola', 'id_serie', 'nome'], 'idx_turmas_escola_serie_nome');
        $this->addIndex('turma_componente_professor', ['professor_id', 'turma_id'], 'idx_tcp_professor_turma');
        $this->addIndex('turma_componente_professor', ['turma_id', 'professor_id'], 'idx_tcp_turma_professor');
        $this->addIndex('pedidos', ['is_pedido_adicional', 'updated_at'], 'idx_pedidos_adicional_updated');
        $this->addIndex('pedidos', ['is_pedido_adicional', 'tipo_status_id', 'updated_at'], 'idx_pedidos_adicional_status_updated');
        $this->addIndex('inventario_estoques', ['inventario_id', 'updated_at'], 'idx_inv_estoques_inventario_updated');
        $this->addIndex('inventario_estoques', ['inventario_id', 'item_id'], 'idx_inv_estoques_inventario_item');
    }

    public function down(): void
    {
        $this->dropIndex('inventario_estoques', 'idx_inv_estoques_inventario_item');
        $this->dropIndex('inventario_estoques', 'idx_inv_estoques_inventario_updated');
        $this->dropIndex('pedidos', 'idx_pedidos_adicional_status_updated');
        $this->dropIndex('pedidos', 'idx_pedidos_adicional_updated');
        $this->dropIndex('turma_componente_professor', 'idx_tcp_turma_professor');
        $this->dropIndex('turma_componente_professor', 'idx_tcp_professor_turma');
        $this->dropIndex('turmas', 'idx_turmas_escola_serie_nome');
        $this->dropIndex('alunos', 'idx_alunos_turma_nome');
        $this->dropIndex('users', 'idx_users_escola_updated');
        $this->dropIndex('users', 'idx_users_setor_updated');
    }

    /**
     * @param array<int, string> $columns
     */
    private function addIndex(string $tableName, array $columns, string $indexName): void
    {
        if (
            ! Schema::hasTable($tableName)
            || Schema::hasIndex($tableName, $indexName)
            || Schema::hasIndex($tableName, $columns)
        ) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
            $table->index($columns, $indexName);
        });
    }

    private function dropIndex(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }
};
