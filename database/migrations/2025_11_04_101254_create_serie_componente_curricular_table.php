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
        Schema::create('serie_componente_curricular', function (Blueprint $table) {
            $table->id();

            $table->foreignId('serie_id')
                ->constrained('series')
                ->cascadeOnDelete();

            $table->foreignId('componente_curricular_id')
                ->constrained('componentes_curriculares')
                ->cascadeOnDelete();

            $table->unique(
                ['serie_id', 'componente_curricular_id'],
                'uniq_serie_componente'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('serie_componente_curricular');
    }
};
