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
        Schema::table('funcao_administrativa', function (Blueprint $table) {
            $table->boolean('tem_relacao_turma')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funcao_administrativa', function (Blueprint $table) {
            $table->dropColumn('tem_relacao_turma');
        });
    }
};