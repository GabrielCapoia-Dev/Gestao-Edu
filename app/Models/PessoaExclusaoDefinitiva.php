<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PessoaExclusaoDefinitiva extends Model
{
    protected $table = 'pessoa_exclusoes_definitivas';

    protected $fillable = [
        'servidor_id_legado',
        'executado_por_user_id',
        'resumo',
        'ocorrido_em',
    ];

    protected function casts(): array
    {
        return [
            'servidor_id_legado' => 'integer',
            'executado_por_user_id' => 'integer',
            'resumo' => 'array',
            'ocorrido_em' => 'datetime',
        ];
    }
}
