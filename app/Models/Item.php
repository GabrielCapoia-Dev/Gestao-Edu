<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Enums\UnidadeMedida;

class Item extends Model
{
    //
    protected $table = 'items';

    protected $fillable = [
        'nome',
        'categoria_id',
        'descricao',
        'unidade_medida',
    ];

    protected $casts = [
        'unidade_medida' => UnidadeMedida::class,
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }
}
