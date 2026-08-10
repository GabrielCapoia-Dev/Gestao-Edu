<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pedidos')) {
            Schema::table('pedidos', function (Blueprint $table): void {
                if (! Schema::hasColumn('pedidos', 'escola_id_legado')) {
                    $table->unsignedBigInteger('escola_id_legado')->nullable()->index();
                }
                if (! Schema::hasColumn('pedidos', 'escola_nome_snapshot')) {
                    $table->string('escola_nome_snapshot')->nullable();
                }
                if (! Schema::hasColumn('pedidos', 'escola_codigo_snapshot')) {
                    $table->string('escola_codigo_snapshot')->nullable();
                }
                if (! Schema::hasColumn('pedidos', 'solicitante_id_legado')) {
                    $table->unsignedBigInteger('solicitante_id_legado')->nullable()->index();
                }
                if (! Schema::hasColumn('pedidos', 'solicitante_nome_snapshot')) {
                    $table->string('solicitante_nome_snapshot')->nullable();
                }
                if (! Schema::hasColumn('pedidos', 'solicitante_email_snapshot')) {
                    $table->string('solicitante_email_snapshot')->nullable();
                }
                if (! Schema::hasColumn('pedidos', 'responsavel_id_legado')) {
                    $table->unsignedBigInteger('responsavel_id_legado')->nullable()->index();
                }
                if (! Schema::hasColumn('pedidos', 'responsavel_nome_snapshot')) {
                    $table->string('responsavel_nome_snapshot')->nullable();
                }
                if (! Schema::hasColumn('pedidos', 'responsavel_email_snapshot')) {
                    $table->string('responsavel_email_snapshot')->nullable();
                }
                if (! Schema::hasColumn('pedidos', 'comentario_gestor_user_id_legado')) {
                    $table->unsignedBigInteger('comentario_gestor_user_id_legado')->nullable()->index();
                }
                if (! Schema::hasColumn('pedidos', 'comentario_gestor_user_nome_snapshot')) {
                    $table->string('comentario_gestor_user_nome_snapshot')->nullable();
                }
                if (! Schema::hasColumn('pedidos', 'comentario_gestor_user_email_snapshot')) {
                    $table->string('comentario_gestor_user_email_snapshot')->nullable();
                }
            });

            foreach ([
                'escola_id_legado',
                'solicitante_id_legado',
                'responsavel_id_legado',
                'comentario_gestor_user_id_legado',
            ] as $column) {
                $this->ensureIndex('pedidos', $column);
            }

            $this->makeNullableWithoutForeignKey('pedidos', 'solicitante_id');
        }

        if (Schema::hasTable('pedido_historicos')) {
            Schema::table('pedido_historicos', function (Blueprint $table): void {
                if (! Schema::hasColumn('pedido_historicos', 'usuario_id_legado')) {
                    $table->unsignedBigInteger('usuario_id_legado')->nullable()->index();
                }
                if (! Schema::hasColumn('pedido_historicos', 'usuario_nome_snapshot')) {
                    $table->string('usuario_nome_snapshot')->nullable();
                }
                if (! Schema::hasColumn('pedido_historicos', 'usuario_email_snapshot')) {
                    $table->string('usuario_email_snapshot')->nullable();
                }
            });

            $this->ensureIndex('pedido_historicos', 'usuario_id_legado');

            $this->makeNullableWithoutForeignKey('pedido_historicos', 'usuario_id');
        }

        if (Schema::hasTable('pedido_arquivos')) {
            Schema::table('pedido_arquivos', function (Blueprint $table): void {
                if (! Schema::hasColumn('pedido_arquivos', 'usuario_id_legado')) {
                    $table->unsignedBigInteger('usuario_id_legado')->nullable()->index();
                }
                if (! Schema::hasColumn('pedido_arquivos', 'usuario_nome_snapshot')) {
                    $table->string('usuario_nome_snapshot')->nullable();
                }
                if (! Schema::hasColumn('pedido_arquivos', 'usuario_email_snapshot')) {
                    $table->string('usuario_email_snapshot')->nullable();
                }
            });

            $this->ensureIndex('pedido_arquivos', 'usuario_id_legado');

            $this->makeNullableWithoutForeignKey('pedido_arquivos', 'usuario_id');
        }
    }

    public function down(): void
    {
        $this->dropExistingColumns('pedido_arquivos', [
            'usuario_id_legado',
            'usuario_nome_snapshot',
            'usuario_email_snapshot',
        ]);
        $this->dropExistingColumns('pedido_historicos', [
            'usuario_id_legado',
            'usuario_nome_snapshot',
            'usuario_email_snapshot',
        ]);
        $this->dropExistingColumns('pedidos', [
            'escola_id_legado',
            'escola_nome_snapshot',
            'escola_codigo_snapshot',
            'solicitante_id_legado',
            'solicitante_nome_snapshot',
            'solicitante_email_snapshot',
            'responsavel_id_legado',
            'responsavel_nome_snapshot',
            'responsavel_email_snapshot',
            'comentario_gestor_user_id_legado',
            'comentario_gestor_user_nome_snapshot',
            'comentario_gestor_user_email_snapshot',
        ]);
    }

    private function makeNullableWithoutForeignKey(string $tableName, string $column): void
    {
        if (! Schema::hasColumn($tableName, $column)) {
            return;
        }

        $foreignKeys = collect(Schema::getForeignKeys($tableName))
            ->filter(fn (array $foreign): bool => in_array($column, $foreign['columns'] ?? [], true));

        foreach ($foreignKeys as $foreign) {
            Schema::table($tableName, function (Blueprint $table) use ($foreign, $column): void {
                $table->dropForeign(filled($foreign['name'] ?? null) ? $foreign['name'] : [$column]);
            });
        }

        Schema::table($tableName, function (Blueprint $table) use ($column): void {
            $table->unsignedBigInteger($column)->nullable()->change();
        });
    }

    private function ensureIndex(string $tableName, string $column): void
    {
        $indexName = "{$tableName}_{$column}_index";

        if (! Schema::hasColumn($tableName, $column) || Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($column, $indexName): void {
            $table->index($column, $indexName);
        });
    }

    /** @param array<int, string> $columns */
    private function dropExistingColumns(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $existing = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($tableName, $column),
        ));

        if ($existing === []) {
            return;
        }

        Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($existing));
    }
};
