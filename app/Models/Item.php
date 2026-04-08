<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Enums\UnidadeMedida;
use App\Models\Enums\TipoItem;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Item extends Model
{
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

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (filled($item->codigo)) {
                return;
            }

            $item->codigo = self::gerarProximoCodigo();
        });
    }

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
        $ultimoCodigo = self::query()
            ->where('codigo', 'like', 'ITM-%')
            ->orderByDesc('codigo')
            ->value('codigo');

        $ultimoNumero = (int) preg_replace('/\D/', '', (string) $ultimoCodigo);

        return 'ITM-' . str_pad((string) ($ultimoNumero + 1), 6, '0', STR_PAD_LEFT);
    }
}
