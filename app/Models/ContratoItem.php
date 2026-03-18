<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use App\Models\Enums\TipoItemContrato;


class ContratoItem extends Pivot
{
    protected $table = 'contrato_item';

    protected $fillable = [
        'contrato_id',
        'item_id',
        'tipo',
        'quantidade_total',
        'quantidade_utilizada',
        'preco_unitario',
    ];

    protected $casts = [
        'tipo'                 => TipoItemContrato::class,
        'quantidade_total'     => 'decimal:3',
        'quantidade_utilizada' => 'decimal:3',
        'preco_unitario'       => 'decimal:2',
        'preco_total'          => 'decimal:2',
    ];
}
