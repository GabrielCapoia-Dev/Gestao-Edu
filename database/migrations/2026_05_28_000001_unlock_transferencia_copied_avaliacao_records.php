<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TRANSFERENCIA = 'transferencia';

    private const MATRICULADO = 'matriculado';

    public function up(): void
    {
        $this->atualizarBloqueioCopiasTransferencia('avaliacao_respostas', false);
        $this->atualizarBloqueioCopiasTransferencia('avaliacao_informacoes_complementares', false);
    }

    public function down(): void
    {
        $this->atualizarBloqueioCopiasTransferencia('avaliacao_respostas', true);
        $this->atualizarBloqueioCopiasTransferencia('avaliacao_informacoes_complementares', true);
    }

    private function atualizarBloqueioCopiasTransferencia(string $table, bool $bloqueada): void
    {
        foreach (['bloqueada', 'bloqueio_tipo', 'aluno_origem_id', 'aluno_id'] as $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        DB::table($table)
            ->where('bloqueio_tipo', self::TRANSFERENCIA)
            ->where('bloqueada', ! $bloqueada)
            ->whereNotNull('aluno_origem_id')
            ->whereExists(function ($query) use ($table): void {
                $query
                    ->selectRaw('1')
                    ->from('alunos')
                    ->whereColumn('alunos.id', $table.'.aluno_id')
                    ->where('alunos.status', self::MATRICULADO)
                    ->where('alunos.movimentacao_origem', self::TRANSFERENCIA)
                    ->whereColumn('alunos.aluno_origem_id', $table.'.aluno_origem_id');
            })
            ->update([
                'bloqueada' => $bloqueada,
                'updated_at' => DB::raw('CURRENT_TIMESTAMP'),
            ]);
    }
};
