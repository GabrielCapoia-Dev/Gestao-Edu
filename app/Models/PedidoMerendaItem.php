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

    protected static function booted(): void
    {
        static::updating(function ($model) {
            if ($model->isDirty('quantidade_pedida')) {
                throw new \DomainException('Quantidade pedida não pode ser alterada após criação.');
            }

            if ($model->quantidade_entregue > $model->quantidade_pedida) {
                throw new \DomainException('Quantidade entregue não pode ser maior que a pedida.');
            }
        });
    }

    public function registrarEntrega(float $quantidade): void
    {
        if ($quantidade <= 0) {
            throw new \InvalidArgumentException('Quantidade inválida.');
        }

        if ($this->quantidade_entregue + $quantidade > $this->quantidade_pedida) {
            throw new \DomainException('Entrega excede o pedido.');
        }

        $this->increment('quantidade_entregue', $quantidade);

        $this->pedido->recalcularStatus();
    }
}
