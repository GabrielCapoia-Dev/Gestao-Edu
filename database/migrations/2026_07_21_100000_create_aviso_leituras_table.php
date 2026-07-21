<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avisos', function (Blueprint $table): void {
            $table->unsignedInteger('versao_envio')->default(1)->after('ativo');
        });

        Schema::create('aviso_leituras', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aviso_id')->constrained('avisos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('versao_envio');
            $table->timestamp('lido_em');
            $table->timestamps();

            $table->unique(
                ['aviso_id', 'user_id', 'versao_envio'],
                'aviso_leituras_aviso_usuario_versao_unique',
            );
            $table->index(
                ['user_id', 'aviso_id', 'versao_envio'],
                'aviso_leituras_usuario_aviso_versao_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aviso_leituras');

        Schema::table('avisos', function (Blueprint $table): void {
            $table->dropColumn('versao_envio');
        });
    }
};
