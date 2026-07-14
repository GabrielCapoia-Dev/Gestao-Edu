<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avaliacao_aluno_documentos', function (Blueprint $table): void {
            $table->json('responsaveis_snapshot')->nullable()->after('payload');
            $table->timestamp('responsaveis_snapshot_em')->nullable()->after('responsaveis_snapshot');
        });

        Schema::table('avaliacao_aluno_documentos_historico', function (Blueprint $table): void {
            $table->json('responsaveis_snapshot')->nullable()->after('payload');
            $table->timestamp('responsaveis_snapshot_em')->nullable()->after('responsaveis_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('avaliacao_aluno_documentos_historico', function (Blueprint $table): void {
            $table->dropColumn(['responsaveis_snapshot', 'responsaveis_snapshot_em']);
        });

        Schema::table('avaliacao_aluno_documentos', function (Blueprint $table): void {
            $table->dropColumn(['responsaveis_snapshot', 'responsaveis_snapshot_em']);
        });
    }
};
