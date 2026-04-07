<?php

namespace App\Models;

use App\Models\Enums\InventarioPedidoItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioPedidoItem extends Model
{
    protected $table = 'inventario_pedido_itens';

    protected $fillable = [
        'inventario_pedido_id',
        'item_id',
        'quantidade_solicitada',
        'quantidade_aprovada',
        'quantidade_recebida',
        'observacao_solicitacao',
        'observacao_aprovacao',
        'observacao_conferencia',
        'status',
    ];

    protected $casts = [
        'quantidade_solicitada' => 'decimal:3',
        'quantidade_aprovada' => 'decimal:3',
        'quantidade_recebida' => 'decimal:3',
        'status' => InventarioPedidoItemStatus::class,
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(InventarioPedido::class, 'inventario_pedido_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function getQuantidadeAprovadaEfetivaAttribute(): float
    {
        return round((float) ($this->quantidade_aprovada ?? 0), 3);
    }

    public function getQuantidadeRecebidaEfetivaAttribute(): float
    {
        return round((float) ($this->quantidade_recebida ?? 0), 3);
    }
}
