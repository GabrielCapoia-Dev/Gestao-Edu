<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Enums\UnidadeMedida;
use App\Models\Enums\TipoItem;

class Item extends Model
{
    protected $table = 'itens';

    protected $fillable = [
        'nome',
        'descricao',
        'tipo_item',
        'unidade_medida',
        'ativo',
    ];

    protected $casts = [
        'nome'          => 'string',
        'descricao'     => 'string',
        'tipo_item'     => TipoItem::class,
        'unidade_medida' => UnidadeMedida::class,
        'ativo'         => 'boolean',
    ];

    public function contratos()
    {
        return $this->belongsToMany(Contrato::class, 'contrato_item')
            ->using(ContratoItem::class)
            ->withPivot('quantidade_total', 'quantidade_utilizada', 'preco_unitario', 'preco_total', 'tipo')
            ->withTimestamps();
    }

    public function getNomeComUnidadeAttribute(): string
    {
        return "{$this->nome} - {$this->unidade_medida->value}";
    }
}
