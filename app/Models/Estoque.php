<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Estoque extends Model
{
    protected $table = 'estoque';

    protected $fillable = [
        'item_id',
        'quantidade',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
    ];

    protected function user()
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | Relacionamentos
    |--------------------------------------------------------------------------
    */

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function movimentacoes()
    {
        return $this->hasMany(EstoqueMovimentacao::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Adiciona quantidade ao saldo do estoque e registra a movimentação.
     */
    public function entrada(float $quantidade, ?int $pedidoMerendaId = null, ?string $observacao = null): EstoqueMovimentacao
    {
        $this->increment('quantidade', $quantidade);

        return $this->movimentacoes()->create([
            'tipo'               => \App\Models\Enums\TipoMovimentacao::Entrada,
            'quantidade'         => $quantidade,
            'pedido_merenda_id'  => $pedidoMerendaId,
            'observacao'         => $observacao,
            'registrado_por'     => $this->user()?->name,
        ]);
    }

    /**
     * Remove quantidade do saldo do estoque e registra a movimentação.
     */
    public function saida(float $quantidade, ?int $pedidoMerendaId = null, ?string $observacao = null): EstoqueMovimentacao
    {
        $this->decrement('quantidade', $quantidade);

        return $this->movimentacoes()->create([
            'tipo'               => \App\Models\Enums\TipoMovimentacao::Saida,
            'quantidade'         => $quantidade,
            'pedido_merenda_id'  => $pedidoMerendaId,
            'observacao'         => $observacao,
            'registrado_por'     => $this->user()?->name,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeDoItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }
}