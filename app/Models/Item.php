<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Enums\UnidadeMedida;

class Item extends Model
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

    public function contratos()
    {
        return $this->belongsToMany(Contrato::class, 'contrato_item')
            ->using(ContratoItem::class)
            ->withPivot('quantidade')
            ->withTimestamps();
    }
}
