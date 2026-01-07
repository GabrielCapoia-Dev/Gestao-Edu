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
        Schema::create('professores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_escola')->constrained('escolas')->onDelete('cascade');
            $table->string('matricula')->nullable()->unique();
            $table->string('nome');
            $table->string('email')->nullable();
            $table->enum('especializacao', [
                'Magisterio' => 'Magisterio',
                'Licenciatura' => 'Licenciatura',
                'Bacharelado' => 'Bacharelado',
                'Pos Graduacao' => 'Pos Graduacao',
                'Doutorado' => 'Doutorado',
                'Mestrado' => 'Mestrado',
            ])->nullable();
            $table->enum('turno', ['Manhã', 'Tarde','Noite'])->nullable();
            $table->boolean('professor_srm');
            $table->boolean('profissional_apoio');
            $table->boolean('especializacao_educacao_especial')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professores');
    }
};
