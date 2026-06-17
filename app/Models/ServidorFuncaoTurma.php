<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ServidorFuncaoTurma extends Pivot
{
    protected $table = 'servidor_funcao_turma';

    public $incrementing = true;

    protected $fillable = [
        'servidor_funcao_administrativa_id',
        'turma_id',
    ];

    protected function casts(): array
    {
        return [
            'servidor_funcao_administrativa_id' => 'integer',
            'turma_id' => 'integer',
        ];
    }

    public function servidorFuncaoAdministrativa(): BelongsTo
    {
        return $this->belongsTo(ServidorFuncaoAdministrativa::class, 'servidor_funcao_administrativa_id');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }
}
