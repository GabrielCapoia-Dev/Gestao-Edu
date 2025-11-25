<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('alunos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_turma')->constrained('turmas')->restrictOnDelete();
            $table->foreignId('id_professor')->nullable()->constrained('professores')->onDelete('cascade');
            $table->string('nome');
            $table->string('cgm');
            $table->enum('sexo', ['Masculino', 'Feminino']);
            $table->date('data_nascimento');

            $table->boolean('dificuldade_aprendizagem')->default(false);
            $table->boolean('frequenta_srm')->default(false);

            $table->enum('encaminhado_para_sme', ['Sim', 'Nao']);

            $table->enum('ja_foi_retido', ['Sim', 'Nao']);

            $table->enum('encaminhado_para_caei', ['Sim', 'Nao']);

            $table->enum('status_fonoaudiologo', [
                'Sim, Lista de Espera',
                'Sim, Em Atendimento',
                'Sim, Desistente',
                'Sim, Desligado',
                'Não',
            ])->nullable();
            $table->enum('status_psicologo', [
                'Sim, Lista de Espera',
                'Sim, Em Atendimento',
                'Sim, Desistente',
                'Sim, Desligado',
                'Não',
            ])->nullable();
            $table->enum('status_psicopedagogo', [
                'Sim, Lista de Espera',
                'Sim, Em Atendimento',
                'Sim, Desistente',
                'Sim, Desligado',
                'Não',
            ])->nullable();

            $table->enum('avanco_caei', ['Sim', 'Nao', 'Nao está em atendimento'])->nullable();
            $table->unique(['cgm', 'id_turma']);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alunos');
    }
};
