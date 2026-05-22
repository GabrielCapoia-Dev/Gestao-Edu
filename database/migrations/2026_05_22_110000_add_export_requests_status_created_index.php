<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('export_requests', 'export_requests_status_created_at_index')) {
            Schema::table('export_requests', function (Blueprint $table): void {
                $table->index(['status', 'created_at'], 'export_requests_status_created_at_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('export_requests', 'export_requests_status_created_at_index')) {
            Schema::table('export_requests', function (Blueprint $table): void {
                $table->dropIndex('export_requests_status_created_at_index');
            });
        }
    }
};
