<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('servidores', 'deleted_at')) {
            Schema::table('servidores', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('servidores', 'email_normalizado')) {
            Schema::table('servidores', function (Blueprint $table): void {
                $emailNormalizado = $table->string('email_normalizado');

                if (DB::getDriverName() === 'sqlite') {
                    $emailNormalizado->virtualAs("NULLIF(LOWER(TRIM(email)), '')");
                } else {
                    $emailNormalizado->storedAs("NULLIF(LOWER(TRIM(email)), '')");
                }
            });
        }

        if (! Schema::hasIndex('servidores', 'idx_servidores_email_normalizado')) {
            Schema::table('servidores', function (Blueprint $table): void {
                $table->index('email_normalizado', 'idx_servidores_email_normalizado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('servidores', 'idx_servidores_email_normalizado')) {
            Schema::table('servidores', function (Blueprint $table): void {
                $table->dropIndex('idx_servidores_email_normalizado');
            });
        }

        if (Schema::hasColumn('servidores', 'email_normalizado')) {
            Schema::table('servidores', function (Blueprint $table): void {
                $table->dropColumn('email_normalizado');
            });
        }

        if (Schema::hasColumn('servidores', 'deleted_at')) {
            Schema::table('servidores', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
