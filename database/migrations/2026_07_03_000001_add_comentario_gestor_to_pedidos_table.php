<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            if (! Schema::hasColumn('pedidos', 'comentario_gestor')) {
                $table->text('comentario_gestor')->nullable()->after('descricao_pedido');
            }

            if (! Schema::hasColumn('pedidos', 'comentario_gestor_user_id')) {
                $table->foreignId('comentario_gestor_user_id')
                    ->nullable()
                    ->after('comentario_gestor')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('pedidos', 'comentario_gestor_at')) {
                $table->timestamp('comentario_gestor_at')->nullable()->after('comentario_gestor_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            if (Schema::hasColumn('pedidos', 'comentario_gestor_user_id')) {
                $table->dropForeign(['comentario_gestor_user_id']);
            }

            foreach (['comentario_gestor_at', 'comentario_gestor_user_id', 'comentario_gestor'] as $column) {
                if (Schema::hasColumn('pedidos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
