<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ContratoItem extends Pivot
{
    protected $table = 'contrato_item';

    protected $fillable = [
        'contrato_id',
        'item_id',
        'quantidade',
        'quantidade_utilizada',
    ];

    protected $casts = [
        'quantidade'           => 'decimal:3',
        'quantidade_utilizada' => 'decimal:3',
    ];
}