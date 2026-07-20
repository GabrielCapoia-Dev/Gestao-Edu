<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importacoes_eventos_calendario', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nome_arquivo');
            $table->string('disk', 40)->default('local');
            $table->string('caminho_arquivo');
            $table->char('checksum', 64);
            $table->string('status', 24)->default('em_pre_visualizacao');
            $table->unsignedInteger('total_linhas')->default(0);
            $table->unsignedInteger('total_validas')->default(0);
            $table->unsignedInteger('total_invalidas')->default(0);
            $table->unsignedInteger('total_criadas')->default(0);
            $table->unsignedInteger('total_atualizadas')->default(0);
            $table->json('relatorio')->nullable();
            $table->timestamp('confirmada_em')->nullable();
            $table->timestamp('cancelada_em')->nullable();
            $table->timestamps();

            $table->index(['usuario_id', 'created_at'], 'idx_import_eventos_usuario_data');
            $table->index(['status', 'created_at'], 'idx_import_eventos_status_data');
        });

        Schema::create('eventos_calendario', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('publico_alvo_id')->constrained('publicos_alvo')->restrictOnDelete();
            $table->foreignId('escola_id')->nullable()->constrained('escolas')->nullOnDelete();
            $table->foreignId('setor_id')->nullable()->constrained('setor')->nullOnDelete();
            $table->foreignId('criado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('atualizado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('excluido_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ultima_importacao_id')->nullable()->constrained('importacoes_eventos_calendario')->nullOnDelete();
            $table->string('titulo', 160);
            $table->text('descricao')->nullable();
            $table->string('categoria', 48)->default('administrativo');
            $table->string('assunto', 100)->nullable();
            $table->string('prioridade', 16)->default('normal');
            $table->dateTime('data_inicio');
            $table->dateTime('data_fim');
            $table->string('link_acao', 2048)->nullable();
            $table->string('texto_botao', 80)->nullable();
            $table->string('status', 24)->default('agendado');
            $table->boolean('ativo')->default(true);
            $table->decimal('progresso', 5, 2)->nullable();
            $table->string('cor', 16)->default('azul');
            $table->string('origem', 16)->default('manual');
            $table->string('fonte_externa', 80)->nullable();
            $table->string('identificador_externo', 160)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['fonte_externa', 'identificador_externo'], 'uniq_evento_calendario_externo');
            $table->index(['ativo', 'status', 'data_inicio', 'data_fim'], 'idx_evento_cal_status_periodo');
            $table->index(['categoria', 'ativo', 'data_inicio'], 'idx_evento_cal_categoria_ativo_inicio');
            $table->index(['escola_id', 'data_inicio'], 'idx_evento_cal_escola_inicio');
            $table->index(['setor_id', 'data_inicio'], 'idx_evento_cal_setor_inicio');
        });

        Schema::create('importacao_evento_calendario_linhas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('importacao_id')->constrained('importacoes_eventos_calendario')->cascadeOnDelete();
            $table->foreignId('evento_calendario_id')->nullable()->constrained('eventos_calendario')->nullOnDelete();
            $table->unsignedInteger('numero_linha');
            $table->json('dados_originais');
            $table->json('dados_normalizados')->nullable();
            $table->json('erros')->nullable();
            $table->string('acao', 16)->default('invalida');
            $table->timestamps();

            $table->unique(['importacao_id', 'numero_linha'], 'uniq_import_evento_linha');
            $table->index(['importacao_id', 'acao'], 'idx_import_evento_linha_acao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacao_evento_calendario_linhas');
        Schema::dropIfExists('eventos_calendario');
        Schema::dropIfExists('importacoes_eventos_calendario');
    }
};
