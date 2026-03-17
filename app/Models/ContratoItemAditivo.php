<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContratoItemAditivo extends Model
{
    protected $table = 'contrato_item_aditivos';

    protected $fillable = [
        'contrato_item_id',
        'quantidade',
        'preco_unitario',
        'justificativa',
    ];

    protected $casts = [
        'quantidade'   => 'decimal:3',
        'preco_unitario' => 'decimal:2',
        'preco_total'    => 'decimal:2',
    ];

    public function contratoItem()
    {
        return $this->belongsTo(ContratoItem::class, 'contrato_item_id');
    }
}