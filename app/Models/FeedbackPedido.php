<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Validation\ValidationException;
use App\Models\PedidoArquivo;
use App\Models\Enums\TipoArquivoPedido;

class FeedbackPedido extends Model
{
    use HasFactory;

    protected $table = 'feedback_pedidos';

    protected $fillable = [
        'pedido_id',
        'valor',
        'descricao',
    ];

    protected $casts = [
        'valor' => 'integer',
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
