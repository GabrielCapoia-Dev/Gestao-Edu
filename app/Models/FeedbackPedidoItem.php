<?php

namespace App\Models;

use App\Models\Enums\ResultadoFeedbackPedido;
use Illuminate\Database\Eloquent\Model;

class FeedbackPedidoItem extends Model
{
    protected $table = 'feedback_pedido_itens';

    protected $fillable = [
        'feedback_pedido_id',
        'pedido_id',
        'pedido_problema_id',
        'valor',
        'resultado',
        'comentario',
    ];

    protected $casts = [
        'valor' => 'integer',
        'resultado' => ResultadoFeedbackPedido::class,
    ];

    public function feedback()
    {
        return $this->belongsTo(FeedbackPedido::class, 'feedback_pedido_id');
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function problema()
    {
        return $this->belongsTo(PedidoProblema::class, 'pedido_problema_id');
    }
}
