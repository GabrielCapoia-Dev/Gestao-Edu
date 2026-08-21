<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servidores', function (Blueprint $table): void {
            $table->unsignedTinyInteger('carga_horaria')->nullable()->after('observacoes');
            $table->boolean('jornada')->nullable()->after('carga_horaria');
            $table->foreignId('lotacao_id')
                ->nullable()
                ->after('jornada')
                ->constrained('lotacoes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table): void {
            $table->dropForeign(['lotacao_id']);
            $table->dropColumn(['carga_horaria', 'jornada', 'lotacao_id']);
        });
    }
};
