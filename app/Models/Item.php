<?php

namespace App\Models;

use App\Models\Concerns\HasUuidCodigo;
use Illuminate\Database\Eloquent\Model;
use App\Models\Enums\UnidadeMedida;
use App\Models\Enums\TipoItem;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Item extends Model
{
    use HasUuidCodigo;

    protected $table = 'itens';

    protected $fillable = [
        'nome',
        'codigo',
        'descricao',
        'tipo_item',
        'unidade_medida',
        'ativo',
    ];

    protected $casts = [
        'nome'           => 'string',
        'codigo'         => 'string',
        'descricao'      => 'string',
        'tipo_item'      => TipoItem::class,
        'unidade_medida' => UnidadeMedida::class,
        'ativo'          => 'boolean',
    ];

    // 🔥 relação correta agora
    public function contratoItens(): HasMany
    {
        return $this->hasMany(ContratoItem::class);
    }

    public function estoque(): HasOne
    {
        return $this->hasOne(Estoque::class, 'item_id');
    }

    public function inventarioEstoques(): HasMany
    {
        return $this->hasMany(InventarioEstoque::class, 'item_id');
    }

    public function inventarioPedidoItens(): HasMany
    {
        return $this->hasMany(InventarioPedidoItem::class, 'item_id');
    }

    public function balancoItens(): HasMany
    {
        return $this->hasMany(BalancoEstoqueItem::class);
    }

    public function balancoInventarioItens(): HasMany
    {
        return $this->hasMany(BalancoInventarioItem::class);
    }

    public function getNomeComUnidadeAttribute(): string
    {
        return "{$this->nome} - {$this->unidade_medida->value}";
    }

    public static function gerarProximoCodigo(): string
    {
        return static::gerarCodigoUuid();
    }
}
