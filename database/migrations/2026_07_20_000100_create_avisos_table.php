<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('publico_alvo_id')->constrained('publicos_alvo')->cascadeOnDelete();
            $table->string('titulo', 160);
            $table->text('descricao');
            $table->string('link_acao', 2048)->nullable();
            $table->string('texto_botao', 80)->nullable();
            $table->string('prioridade', 20)->default('normal');
            $table->unsignedTinyInteger('posicao_preferencial')->nullable();
            $table->integer('ordem_manual')->default(0);
            $table->timestamp('inicio_exibicao');
            $table->timestamp('fim_exibicao');
            $table->boolean('ativo')->default(false);
            $table->foreignId('criado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('atualizado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('excluido_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['ativo', 'inicio_exibicao', 'fim_exibicao'],
                'avisos_vigencia_index'
            );
            $table->index(
                ['prioridade', 'posicao_preferencial', 'ordem_manual'],
                'avisos_ordenacao_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos');
    }
};
