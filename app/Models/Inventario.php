<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Inventario extends Model
{
    protected $table = 'inventarios';

    protected $fillable = [
        'escola_id',
        'setor_id',
        'nome',
        'ativo',
        'criado_por_id',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $inventario): void {
            if (filled($inventario->setor_id) || blank($inventario->escola_id)) {
                return;
            }

            $inventario->setor_id = Escola::query()
                ->whereKey($inventario->escola_id)
                ->value('setor_id');
        });

        static::creating(function (self $inventario): void {
            if (filled($inventario->nome) || ! $inventario->escola_id) {
                return;
            }

            $escola = Escola::query()->find($inventario->escola_id);

            if ($escola) {
                $inventario->nome = 'Inventario - ' . $escola->nome;
            }
        });
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class);
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_id');
    }

    public function estoques(): HasMany
    {
        return $this->hasMany(InventarioEstoque::class);
    }

    public function movimentacoes(): HasManyThrough
    {
        return $this->hasManyThrough(
            InventarioMovimentacao::class,
            InventarioEstoque::class,
            'inventario_id',
            'inventario_estoque_id'
        );
    }

    public function baixas(): HasManyThrough
    {
        return $this->hasManyThrough(
            InventarioBaixa::class,
            InventarioEstoque::class,
            'inventario_id',
            'inventario_estoque_id'
        );
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(InventarioPedido::class);
    }

    public function balancos(): HasMany
    {
        return $this->hasMany(BalancoInventario::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }
}
