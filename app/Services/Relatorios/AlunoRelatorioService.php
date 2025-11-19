<?php

namespace App\Services\Relatorios;

use App\Models\Aluno;
use Illuminate\Support\Collection;

class AlunoRelatorioService
{
    /**
     * Ficha de um aluno (detalhado).
     */
    public function payloadFicha(Aluno $aluno): array
    {
        $aluno->loadMissing([
            'turma.escola',
            'turma.serie',
            'laudos',
        ]);

        return [
            // resources/views/relatorios/Ficha/alunos-ficha.blade.php
            'view' => 'relatorios.Ficha.alunos-ficha',
            'data' => [
                'aluno' => $aluno,
            ],
        ];
    }

    /**
     * Listagem em tabela (bulkList).
     */
    public function payloadBulkList(Collection $alunos): array
    {
        $alunos->loadMissing([
            'turma.escola',
            'turma.serie',
            'laudos',
        ]);

        return [
            // resources/views/relatorios/BulkList/alunos-bulklist.blade.php
            'view' => 'relatorios.BulkList.alunos-bulklist',
            'data' => [
                'alunos' => $alunos,
            ],
        ];
    }

    /**
     * Fichas em lote (bulkFicha).
     */
    public function payloadBulkFicha(Collection $alunos): array
    {
        $alunos->loadMissing([
            'turma.escola',
            'turma.serie',
            'laudos',
        ]);

        return [
            // resources/views/relatorios/BulkFicha/alunos-bulkficha.blade.php
            'view' => 'relatorios.BulkFicha.alunos-bulkficha',
            'data' => [
                'alunos' => $alunos,
            ],
        ];
    }
}
