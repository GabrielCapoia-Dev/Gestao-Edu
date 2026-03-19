<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoMerendaItem extends Model
{
    protected $table = 'pedido_merenda_itens';

    protected $fillable = [
        'pedido_merenda_id',
        'contrato_item_id',
        'quantidade_pedida',
    ];

    protected $casts = [
        'quantidade_pedida' => 'decimal:3',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relacionamentos
    |--------------------------------------------------------------------------
    */

    public function pedido()
    {
        return $this->belongsTo(PedidoMerenda::class, 'pedido_merenda_id');
    }

    public function contratoItem()
    {
        return $this->belongsTo(ContratoItem::class, 'contrato_item_id');
    }
}