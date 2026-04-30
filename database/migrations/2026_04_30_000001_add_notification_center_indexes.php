<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at', 'created_at'],
                'notifications_center_read_index'
            );

            $table->index(
                ['notifiable_type', 'notifiable_id', 'updated_at'],
                'notifications_center_updated_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_center_read_index');
            $table->dropIndex('notifications_center_updated_index');
        });
    }
};
