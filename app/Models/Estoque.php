<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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

    public function baixas()
    {
        return $this->hasMany(BaixasEstoques::class);
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

    /**
     * Registra uma baixa operacional de estoque.
     */
    public function registrarBaixa(float $quantidade, string $motivo): BaixasEstoques
    {
        if ($quantidade <= 0) {
            throw new \InvalidArgumentException('Quantidade de baixa invalida.');
        }

        if ($quantidade > (float) $this->quantidade) {
            throw new \DomainException('Quantidade de baixa maior que o saldo em estoque.');
        }

        return DB::transaction(function () use ($quantidade, $motivo) {
            $this->refresh();

            $saldoAnterior = (float) $this->quantidade;

            if ($quantidade > $saldoAnterior) {
                throw new \DomainException('Quantidade de baixa maior que o saldo em estoque.');
            }

            $this->saida(
                quantidade: $quantidade,
                pedidoMerendaId: null,
                observacao: "Baixa de estoque: {$motivo}",
            );

            $this->refresh();

            return $this->baixas()->create([
                'quantidade' => $quantidade,
                'motivo' => $motivo,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => (float) $this->quantidade,
                'registrado_por' => $this->user()?->name,
            ]);
        });
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
