<?php

namespace App\Exceptions;

use App\Models\Aluno;
use RuntimeException;

class MatriculaAlunoBloqueadaException extends RuntimeException
{
    public function __construct(public readonly Aluno $alunoAtivo, ?string $message = null)
    {
        $alunoAtivo->loadMissing('turma.escola');

        parent::__construct($message ?: sprintf(
            'O aluno %s com CGM %s continua com o status de Matriculado na escola %s, sendo impossivel o aluno manter a matricula ativa em duas unidades diferentes, entre em contato com o gestor da unidade para iniciar o processo de transferencia.',
            $alunoAtivo->nome,
            $alunoAtivo->cgm,
            $alunoAtivo->turma?->escola?->nome ?? 'sem escola vinculada'
        ));
    }
}
