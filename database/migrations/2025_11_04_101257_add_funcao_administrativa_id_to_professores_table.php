<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professores', function (Blueprint $table) {
            $table->foreignId('funcao_administrativa_id')
                ->nullable()
                ->after('telefone')
                ->constrained('funcao_administrativa')
                ->nullOnDelete();
            
            $table->string('portaria')->nullable()->after('funcao_administrativa_id');
        });
    }

    public function down(): void
    {
        Schema::table('professores', function (Blueprint $table) {
            $table->dropForeign(['funcao_administrativa_id']);
            $table->dropColumn(['funcao_administrativa_id', 'portaria']);
        });
    }
};