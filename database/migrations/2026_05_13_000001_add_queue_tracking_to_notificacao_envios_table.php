<?php

use App\Models\NotificacaoEnvio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificacao_envios', function (Blueprint $table): void {
            $table->string('status', 20)->default(NotificacaoEnvio::STATUS_PROCESSED);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();

            $table->index(['status', 'created_at'], 'notificacao_envios_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('notificacao_envios', function (Blueprint $table): void {
            $table->dropIndex('notificacao_envios_status_created_index');
            $table->dropColumn([
                'status',
                'queued_at',
                'processing_started_at',
                'processed_at',
                'failed_at',
                'error_message',
            ]);
        });
    }
};
