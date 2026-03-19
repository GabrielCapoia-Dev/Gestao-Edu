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

    public function scopeEntregue($query)
    {
        return $query->where('status', StatusPedidoMerenda::Entregue);
    }
}