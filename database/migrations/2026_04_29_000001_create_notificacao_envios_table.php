<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacao_envios', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titulo');
            $table->text('mensagem');
            $table->string('url')->nullable();
            $table->string('label')->nullable();
            $table->string('prioridade', 30)->default('normal');
            $table->string('destino_tipo', 50);
            $table->string('destino_label');
            $table->unsignedInteger('destinatarios_count')->default(0);
            $table->json('destinatarios_ids')->nullable();
            $table->json('filtros')->nullable();
            $table->timestamps();

            $table->index(['destino_tipo', 'created_at']);
            $table->index(['prioridade', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacao_envios');
    }
};
