<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('servidores') || ! Schema::hasColumn('servidores', 'email_normalizado')) {
            throw new RuntimeException('Execute primeiro a migration estrutural de e-mail normalizado de Pessoas.');
        }

        $duplicateGroups = DB::query()
            ->fromSub(
                DB::table('servidores')
                    ->select('email_normalizado')
                    ->whereNotNull('email_normalizado')
                    ->groupBy('email_normalizado')
                    ->havingRaw('COUNT(*) > 1'),
                'emails_duplicados',
            )
            ->count();

        if ($duplicateGroups > 0) {
            throw new RuntimeException(
                "Ainda existem {$duplicateGroups} grupo(s) de e-mail duplicado. Corrija-os sem mesclar Pessoas antes da fase 2.",
            );
        }

        if (Schema::hasIndex('servidores', 'idx_servidores_email_normalizado')) {
            Schema::table('servidores', function (Blueprint $table): void {
                $table->dropIndex('idx_servidores_email_normalizado');
            });
        }

        if (! Schema::hasIndex('servidores', 'uq_servidores_email_normalizado')) {
            Schema::table('servidores', function (Blueprint $table): void {
                $table->unique('email_normalizado', 'uq_servidores_email_normalizado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('servidores', 'uq_servidores_email_normalizado')) {
            Schema::table('servidores', function (Blueprint $table): void {
                $table->dropUnique('uq_servidores_email_normalizado');
            });
        }

        if (! Schema::hasIndex('servidores', 'idx_servidores_email_normalizado')) {
            Schema::table('servidores', function (Blueprint $table): void {
                $table->index('email_normalizado', 'idx_servidores_email_normalizado');
            });
        }
    }
};
