<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 100);
            $table->string('format', 20);
            $table->string('label')->nullable();
            $table->json('filters')->nullable();
            $table->json('metadata')->nullable();
            $table->string('fingerprint', 64);
            $table->string('status', 20)->default('queued');
            $table->string('status_message')->nullable();
            $table->unsignedBigInteger('progress_current')->default(0);
            $table->unsignedBigInteger('progress_total')->nullable();
            $table->string('file_disk')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'idx_export_requests_user_status');
            $table->index(['type', 'format', 'status'], 'idx_export_requests_type_format_status');
            $table->index(['fingerprint', 'status'], 'idx_export_requests_fingerprint_status');
            $table->index(['expires_at', 'status'], 'idx_export_requests_expiration_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_requests');
    }
};
