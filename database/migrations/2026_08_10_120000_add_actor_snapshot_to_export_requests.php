<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('export_requests')) {
            return;
        }

        Schema::table('export_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('export_requests', 'user_id_legado')) {
                $table->unsignedBigInteger('user_id_legado')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('export_requests', 'user_nome_snapshot')) {
                $table->string('user_nome_snapshot')->nullable()->after('user_id_legado');
            }

            if (! Schema::hasColumn('export_requests', 'user_email_snapshot')) {
                $table->string('user_email_snapshot')->nullable()->after('user_nome_snapshot');
            }
        });

        if (! Schema::hasIndex('export_requests', 'export_requests_user_id_legado_index')) {
            Schema::table('export_requests', function (Blueprint $table): void {
                $table->index('user_id_legado', 'export_requests_user_id_legado_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('export_requests')) {
            return;
        }

        Schema::table('export_requests', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('export_requests', 'user_email_snapshot') ? 'user_email_snapshot' : null,
                Schema::hasColumn('export_requests', 'user_nome_snapshot') ? 'user_nome_snapshot' : null,
                Schema::hasColumn('export_requests', 'user_id_legado') ? 'user_id_legado' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
