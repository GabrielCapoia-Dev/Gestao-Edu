<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professor_componente_funcional', function (Blueprint $table): void {
            $table->foreignId('professor_id')->constrained('professores')->cascadeOnDelete();
            $table->foreignId('componente_curricular_id')->constrained('componentes_curriculares')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['professor_id', 'componente_curricular_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professor_componente_funcional');
    }
};
