<?php

namespace App\Models;

use App\Models\Enums\StatusPedidoMerenda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PedidoMerenda extends Model
{
    protected $table = 'pedidos_merenda';

    protected $fillable = [
        'status',
        'observacoes',
        'criado_por',
    ];

    protected $casts = [
        'status' => StatusPedidoMerenda::class,
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->criado_por = Auth::user()->name;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relacionamentos
    |--------------------------------------------------------------------------
    */

    public function itens()
    {
        return $this->hasMany(PedidoMerendaItem::class, 'pedido_merenda_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeAguardando($query)
    {
        return $query->where('status', StatusPedidoMerenda::Aguardando);
    }

    public function scopeParcialmenteEntregue($query)
    {
        return $query->where('status', StatusPedidoMerenda::ParcialmenteEntregue);
    }

    public function scopeEntregue($query)
    {
        return $query->where('status', StatusPedidoMerenda::Entregue);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Recalcula e persiste o status do pedido com base na entrega dos itens.
     * Deve ser chamado após qualquer alteração em quantidade_entregue dos itens.
     */
    public function recalcularStatus(): void
    {
        $itens = $this->itens()->get();

        $totalEntregue = $itens->sum(fn($i) => (float) $i->quantidade_entregue);
        $totalPedido   = $itens->sum(fn($i) => (float) $i->quantidade_pedida);

        if ($totalEntregue <= 0) {
            $novoStatus = StatusPedidoMerenda::Aguardando;
        } elseif ($totalEntregue >= $totalPedido) {
            $novoStatus = StatusPedidoMerenda::Entregue;
        } else {
            $novoStatus = StatusPedidoMerenda::ParcialmenteEntregue;
        }

        $this->update(['status' => $novoStatus]);
    }
}