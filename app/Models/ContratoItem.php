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
        'preco_unitario',
    ];

    protected $casts = [
        'tipo'                 => TipoItemContrato::class,
        'quantidade_total'     => 'decimal:3',
        'quantidade_utilizada' => 'decimal:3',
        'preco_unitario'       => 'decimal:2',
        'preco_total'          => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->quantidade_utilizada ??= 0;
        });
    }

    public function contrato()
    {
        return $this->belongsTo(Contrato::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    // Opcional: label pronto pro Filament
    public function getNomeCompletoAttribute(): string
    {
        return "{$this->item->nome} - {$this->item->unidade_medida->value}";
    }
}