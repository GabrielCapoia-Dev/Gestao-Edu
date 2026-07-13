<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove a projeção linha-por-resposta, se existir.
 * Source of truth: avaliacao_aluno_documentos.payload.
 * Sem recálculo em massa — o cutover já materializa as métricas no documento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('avaliacao_resposta_fatos');
    }

    public function down(): void
    {
        // Não recria fatos: o armazenamento canônico é o documento.
        if (Schema::hasTable('avaliacao_resposta_fatos')) {
            return;
        }

        Schema::create('avaliacao_resposta_fatos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('documento_id')->nullable();
            $table->unsignedBigInteger('avaliacao_id');
            $table->unsignedBigInteger('aluno_id');
            $table->unsignedBigInteger('turma_id');
            $table->unsignedBigInteger('escola_id')->nullable();
            $table->unsignedBigInteger('pauta_id');
            $table->unsignedBigInteger('componente_curricular_id')->nullable();
            $table->unsignedBigInteger('alternativa_id')->nullable();
            $table->unsignedBigInteger('professor_id')->nullable();
            $table->boolean('tem_observacao')->default(false);
            $table->text('observacao')->nullable();
            $table->timestamp('respondido_em')->nullable();
            $table->timestamps();
        });
    }
};
