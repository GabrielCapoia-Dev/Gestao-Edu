<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ContratoItem extends Pivot
{
    protected $table = 'contrato_item';

    protected $fillable = [
        'contrato_id',
        'item_id',
        'quantidade_total',
        'quantidade_utilizada',
        'preco_unitario',
    ];

    protected $casts = [
        'quantidade_total'     => 'decimal:3',
        'quantidade_utilizada' => 'decimal:3',
        'preco_unitario'       => 'decimal:2',
        'preco_total'          => 'decimal:2',
    ];
}