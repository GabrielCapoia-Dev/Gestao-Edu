<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'ativo')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('ativo')
                    ->default(true)
                    ->after('email_approved')
                    ->index();
            });
        }

        if (! Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('users', 'auth_version')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedInteger('auth_version')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasColumn('users', 'auth_version')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('auth_version');
            });
        }

        if (Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('users', 'ativo')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('ativo');
            });
        }
    }
};
