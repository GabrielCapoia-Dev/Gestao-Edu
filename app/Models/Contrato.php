<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contrato extends Model
{
    //
    protected $table = 'contratos';

    protected $fillable = [
        'id_empresa_contratada',
        'numero_contrato',
        'data_vencimento',
    ];

    public function empresaContratada()
    {
        return $this->belongsTo(EmpresaContratada::class, 'id_empresa_contratada');
    }

    public function itens()
    {
        return $this->belongsToMany(Item::class, 'contrato_item')
            ->withPivot('quantidade')
            ->withTimestamps();
    }
}
