<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    /*
    |--------------------------------------------------------------------------
    | Observers
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Métodos de Negócio
    |--------------------------------------------------------------------------
    */

    /**
     * Registra a entrega (parcial ou total) de uma quantidade deste item.
     *
     * O que acontece no ContratoItem:
     *   - quantidade_reservada  -= $quantidade  (saiu da reserva)
     *   - quantidade_utilizada  += $quantidade  (entrou como utilizado)
     *
     * Isso mantém o saldo_disponivel intacto (já estava descontado pela reserva),
     * mas move o valor para o campo correto, refletindo o consumo real do contrato.
     */
    public function registrarEntrega(float $quantidade): void
    {
        if ($quantidade <= 0) {
            throw new \InvalidArgumentException('Quantidade inválida.');
        }

        if ($this->quantidade_entregue + $quantidade > $this->quantidade_pedida) {
            throw new \DomainException('Entrega excede o pedido.');
        }

        DB::transaction(function () use ($quantidade) {
            // 1. Atualiza a quantidade entregue neste item do pedido
            $this->increment('quantidade_entregue', $quantidade);

            // 2. No ContratoItem: move da reserva para utilizado
            //    reservada -= quantidade  →  utilizada += quantidade
            //    O saldo_disponivel não muda pois ambos os campos o afetam igualmente.
            $this->contratoItem()->lockForUpdate()->first()->tap(function (ContratoItem $ci) use ($quantidade) {
                $ci->decrement('quantidade_reservada', $quantidade);
                $ci->increment('quantidade_utilizada', $quantidade);
            });

            // 3. Garante dados frescos antes de recalcular o status do pedido
            $this->refresh();
            $this->pedido->refresh();
            $this->pedido->recalcularStatus();
        });
    }
}