<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_pedidos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pedido_id')
                ->constrained('pedidos')
                ->cascadeOnDelete()
                ->unique(); // garante 1:1

            $table->unsignedTinyInteger('valor'); // 0 a 10
            $table->text('descricao')->nullable();

            $table->timestamps();

            $table->check('valor >= 0 AND valor <= 10');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_pedidos');
    }
};