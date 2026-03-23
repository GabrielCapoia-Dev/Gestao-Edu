<?php

namespace App\Models;

use App\Models\Enums\TipoItemContrato;
use Illuminate\Database\Eloquent\Model;

class ContratoItem extends Model
{
    protected $table = 'contrato_item';

    protected $fillable = [
        'contrato_id',
        'item_id',
        'tipo',
        'quantidade_total',
        'quantidade_utilizada',
        'quantidade_reservada',
        'preco_unitario',
    ];

    protected $casts = [
        'tipo'                 => TipoItemContrato::class,
        'quantidade_total'     => 'decimal:3',
        'quantidade_utilizada' => 'decimal:3',
        'quantidade_reservada' => 'decimal:3',
        'preco_unitario'       => 'decimal:2',
        'preco_total'          => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->quantidade_utilizada ??= 0;
            $model->quantidade_reservada ??= 0;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relacionamentos
    |--------------------------------------------------------------------------
    */

    public function contrato()
    {
        return $this->belongsTo(Contrato::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function pedidoMerendaItens()
    {
        return $this->hasMany(PedidoMerendaItem::class, 'contrato_item_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Saldo real disponível para novos pedidos.
     * quantidade_total - quantidade_utilizada - quantidade_reservada
     */
    public function getSaldoDisponivelAttribute(): float
    {
        return (float) $this->quantidade_total
            - (float) $this->quantidade_utilizada
            - (float) $this->quantidade_reservada;
    }

    /*
    |--------------------------------------------------------------------------
    | Labels
    |--------------------------------------------------------------------------
    */

    public function getNomeCompletoAttribute(): string
    {
        return "{$this->item->nome} - {$this->item->unidade_medida->value}";
    }
}