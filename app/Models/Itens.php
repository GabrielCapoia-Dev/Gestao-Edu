<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Enums\UnidadeMedida;

class Itens extends Model
{
    //
    protected $table = 'itens';

    protected $fillable = [
        'nome',
        'descricao',
        'unidade_medida',
    ];


    protected $casts = [
        'nome' => 'string',
        'descricao' => 'string',
        'unidade_medida' => UnidadeMedida::class,
    ];
}
