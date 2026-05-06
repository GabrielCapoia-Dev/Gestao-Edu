<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Enums\TipoArquivoPedido;

class FeedbackPedido extends Model
{
    use HasFactory;

    protected $table = 'feedback_pedidos';

    protected $fillable = [
        'pedido_id',
        'valor',
        'descricao',
        'reabrir_pedido',
    ];

    protected $casts = [
        'valor' => 'integer',
        'reabrir_pedido' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relacionamentos
    |--------------------------------------------------------------------------
    */

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function itens()
    {
        return $this->hasMany(FeedbackPedidoItem::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Regra: só pode avaliar pedido concluído
    |--------------------------------------------------------------------------
    */


    public function fotos()
    {
        return $this->hasMany(PedidoArquivo::class, 'pedido_id', 'pedido_id')
            ->where('tipo_arquivo', TipoArquivoPedido::FOTOS_CONCLUSAO);
    }
}
