<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'avatar_url')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_url', 2048)->nullable()->after('google_email');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'avatar_url')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('avatar_url');
        });
    }
};
