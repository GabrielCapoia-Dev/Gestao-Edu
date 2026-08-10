<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalancoInventarioItem extends Model
{
    protected $table = 'balanco_inventario_itens';

    protected $fillable = [
        'balanco_inventario_id',
        'inventario_estoque_id',
        'item_id',
        'incluido_na_contagem',
        'saldo_sistema_antes',
        'quantidade_contada',
        'saldo_final',
        'diferenca',
        'valor_unitario_referencia',
        'valor_impacto',
        'observacao_contagem',
        'contado_em',
        'contado_por_id',
    ];

    protected $casts = [
        'incluido_na_contagem' => 'boolean',
        'saldo_sistema_antes' => 'decimal:3',
        'quantidade_contada' => 'decimal:3',
        'saldo_final' => 'decimal:3',
        'diferenca' => 'decimal:3',
        'valor_unitario_referencia' => 'decimal:2',
        'valor_impacto' => 'decimal:2',
        'contado_em' => 'datetime',
    ];

    public function balanco(): BelongsTo
    {
        return $this->belongsTo(BalancoInventario::class, 'balanco_inventario_id');
    }

    public function inventarioEstoque(): BelongsTo
    {
        return $this->belongsTo(InventarioEstoque::class, 'inventario_estoque_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function contadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contado_por_id')->withTrashed();
    }

    public function getStatusContagemAttribute(): string
    {
        if (! $this->incluido_na_contagem) {
            return 'fora';
        }

        if ($this->quantidade_contada === null) {
            return 'pendente';
        }

        return ((float) $this->diferenca !== 0.0) ? 'ajustado' : 'contado';
    }

    public function getStatusContagemLabelAttribute(): string
    {
        return match ($this->status_contagem) {
            'fora' => 'Fora do Balanço',
            'pendente' => 'Pendente',
            'ajustado' => 'Ajustado',
            default => 'Contado',
        };
    }

    public function getStatusContagemColorAttribute(): string
    {
        return match ($this->status_contagem) {
            'fora' => 'gray',
            'pendente' => 'warning',
            'ajustado' => 'info',
            default => 'success',
        };
    }
}
