<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    //

    protected $table = 'categorias';

    protected $fillable = [
        'nome',
        'descricao',
    ];

    public function items()
    {
        return $this->hasMany(Item::class);
    }

}
