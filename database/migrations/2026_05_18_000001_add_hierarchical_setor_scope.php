<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setor', function (Blueprint $table): void {
            if (! Schema::hasColumn('setor', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('setor')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('setor', 'path')) {
                $table->string('path', 768)
                    ->nullable()
                    ->after('parent_id');
            }

            if (! Schema::hasColumn('setor', 'depth')) {
                $table->unsignedSmallInteger('depth')
                    ->default(0)
                    ->after('path');
            }

            if (! Schema::hasColumn('setor', 'sort_order')) {
                $table->unsignedInteger('sort_order')
                    ->default(0)
                    ->after('depth');
            }

            if (! Schema::hasColumn('setor', 'is_default_root')) {
                $table->boolean('is_default_root')
                    ->default(false)
                    ->after('sort_order');
            }

            $table->index(['parent_id', 'ativo'], 'setor_parent_ativo_index');
            $table->index('path', 'setor_path_index');
            $table->index(['is_default_root', 'ativo'], 'setor_default_root_ativo_index');
        });

        Schema::table('escolas', function (Blueprint $table): void {
            if (! Schema::hasColumn('escolas', 'setor_id')) {
                $table->foreignId('setor_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('setor')
                    ->nullOnDelete();

                $table->index(['setor_id', 'ativo'], 'escolas_setor_ativo_index');
            }
        });

        Schema::table('contratos', function (Blueprint $table): void {
            if (! Schema::hasColumn('contratos', 'setor_id')) {
                $table->foreignId('setor_id')
                    ->nullable()
                    ->after('id_empresa_contratada')
                    ->constrained('setor')
                    ->nullOnDelete();

                $table->index(['setor_id', 'ativo'], 'contratos_setor_ativo_index');
            }
        });

        Schema::table('pedidos_merenda', function (Blueprint $table): void {
            if (! Schema::hasColumn('pedidos_merenda', 'setor_id')) {
                $table->foreignId('setor_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('setor')
                    ->nullOnDelete();

                $table->index(['setor_id', 'status'], 'pedidos_merenda_setor_status_index');
            }
        });

        Schema::table('inventarios', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventarios', 'setor_id')) {
                $table->foreignId('setor_id')
                    ->nullable()
                    ->after('escola_id')
                    ->constrained('setor')
                    ->nullOnDelete();

                $table->index(['setor_id', 'ativo'], 'inventarios_setor_ativo_index');
            }
        });

        Schema::table('pedidos', function (Blueprint $table): void {
            if (! Schema::hasColumn('pedidos', 'setor_origem_id')) {
                $table->foreignId('setor_origem_id')
                    ->nullable()
                    ->after('setor_id')
                    ->constrained('setor')
                    ->nullOnDelete();

                $table->index(['setor_origem_id', 'ativo'], 'pedidos_setor_origem_ativo_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            if (Schema::hasColumn('pedidos', 'setor_origem_id')) {
                $table->dropForeign(['setor_origem_id']);
                $table->dropIndex('pedidos_setor_origem_ativo_index');
                $table->dropColumn('setor_origem_id');
            }
        });

        Schema::table('inventarios', function (Blueprint $table): void {
            if (Schema::hasColumn('inventarios', 'setor_id')) {
                $table->dropForeign(['setor_id']);
                $table->dropIndex('inventarios_setor_ativo_index');
                $table->dropColumn('setor_id');
            }
        });

        Schema::table('pedidos_merenda', function (Blueprint $table): void {
            if (Schema::hasColumn('pedidos_merenda', 'setor_id')) {
                $table->dropForeign(['setor_id']);
                $table->dropIndex('pedidos_merenda_setor_status_index');
                $table->dropColumn('setor_id');
            }
        });

        Schema::table('contratos', function (Blueprint $table): void {
            if (Schema::hasColumn('contratos', 'setor_id')) {
                $table->dropForeign(['setor_id']);
                $table->dropIndex('contratos_setor_ativo_index');
                $table->dropColumn('setor_id');
            }
        });

        Schema::table('escolas', function (Blueprint $table): void {
            if (Schema::hasColumn('escolas', 'setor_id')) {
                $table->dropForeign(['setor_id']);
                $table->dropIndex('escolas_setor_ativo_index');
                $table->dropColumn('setor_id');
            }
        });

        Schema::table('setor', function (Blueprint $table): void {
            $table->dropIndex('setor_default_root_ativo_index');
            $table->dropIndex('setor_path_index');
            $table->dropIndex('setor_parent_ativo_index');

            if (Schema::hasColumn('setor', 'parent_id')) {
                $table->dropForeign(['parent_id']);
            }

            $columns = array_filter([
                Schema::hasColumn('setor', 'is_default_root') ? 'is_default_root' : null,
                Schema::hasColumn('setor', 'sort_order') ? 'sort_order' : null,
                Schema::hasColumn('setor', 'depth') ? 'depth' : null,
                Schema::hasColumn('setor', 'path') ? 'path' : null,
                Schema::hasColumn('setor', 'parent_id') ? 'parent_id' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
