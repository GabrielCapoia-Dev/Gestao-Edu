<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('export_requests', function (Blueprint $table): void {
            $table->string('session_hash', 64)->nullable()->after('user_email_snapshot');
            $table->timestamp('session_expires_at')->nullable()->after('session_hash');
            $table->timestamp('session_ended_at')->nullable()->after('session_expires_at');

            $table->index(['session_hash', 'status'], 'export_requests_session_status_index');
            $table->index(['session_expires_at', 'session_ended_at'], 'export_requests_session_expiration_index');
        });

        DB::table('export_requests')->update([
            'session_expires_at' => now(),
            'session_ended_at' => now(),
            'expires_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('export_requests', function (Blueprint $table): void {
            $table->dropIndex('export_requests_session_status_index');
            $table->dropIndex('export_requests_session_expiration_index');
            $table->dropColumn(['session_hash', 'session_expires_at', 'session_ended_at']);
        });
    }
};
