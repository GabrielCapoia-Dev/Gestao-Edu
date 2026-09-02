<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Conversão set-based: não carrega alunos nem chama services por registro.
        DB::table('alunos as origem')
            ->join('alunos as destino', 'destino.pendencia_origem_aluno_id', '=', 'origem.id')
            ->where('origem.status', 'matriculado')
            ->where('destino.status', 'pendente')
            ->update([
                'origem.status' => 'transferido',
                'origem.status_alterado_em' => DB::raw('COALESCE(origem.status_alterado_em, NOW())'),
                'origem.status_motivo' => 'Transferência legada concluída automaticamente durante a atualização da aplicação.',
                'destino.status' => 'matriculado',
                'destino.status_alterado_em' => DB::raw('NOW()'),
                'destino.status_motivo' => 'Matrícula legada convertida automaticamente durante a atualização da aplicação.',
                'destino.pendencia_origem_aluno_id' => null,
            ]);
    }

    public function down(): void
    {
        // Conversão de dados não é revertida automaticamente para não recriar bloqueios.
    }
};
