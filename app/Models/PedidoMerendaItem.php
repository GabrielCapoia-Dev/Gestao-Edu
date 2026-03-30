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
        'quantidade_entregue',
    ];

    protected $casts = [
        'quantidade_pedida'   => 'decimal:3',
        'quantidade_entregue' => 'decimal:3',
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

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Quantidade ainda pendente de entrega para este item.
     */
    public function getQuantidadePendenteAttribute(): float
    {
        return max(0, (float) $this->quantidade_pedida - (float) $this->quantidade_entregue);
    }

    /**
     * Indica se este item está completamente entregue.
     */
    public function getEntregueCompletoAttribute(): bool
    {
        return $this->quantidade_pendente === 0.0;
    }
}