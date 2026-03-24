<?php

namespace App\Models;

use App\Models\Enums\TipoMovimentacao;
use Illuminate\Database\Eloquent\Model;

class EstoqueMovimentacao extends Model
{
    protected $table = 'estoque_movimentacoes';

    protected $fillable = [
        'estoque_id',
        'tipo',
        'quantidade',
        'pedido_merenda_id',
        'observacao',
        'registrado_por',
    ];

    protected $casts = [
        'tipo'       => TipoMovimentacao::class,
        'quantidade' => 'decimal:3',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relacionamentos
    |--------------------------------------------------------------------------
    */

    public function estoque()
    {
        return $this->belongsTo(Estoque::class);
    }

    public function pedidoMerenda()
    {
        return $this->belongsTo(PedidoMerenda::class);
    }
}