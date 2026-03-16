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
        Schema::create('itens', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('unidade_medida');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('contrato_item', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contrato_id')
                ->constrained('contratos')
                ->cascadeOnDelete();

            $table->foreignId('item_id')
                ->constrained('itens')
                ->cascadeOnDelete();

            $table->decimal('quantidade', 10, 3);
            $table->decimal('quantidade_utilizada', 10, 3)->default(0);

            $table->timestamps();

            $table->unique(['contrato_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contrato_item');
        Schema::dropIfExists('itens');
    }
};
