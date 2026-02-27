<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Validation\ValidationException;

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

    protected static function booted()
    {
        static::creating(function ($feedback) {

            $pedido = $feedback->pedido()->first();

            if (! $pedido || $pedido->tipoStatus?->nome !== 'Concluído') {
                throw ValidationException::withMessages([
                    'pedido_id' => 'Somente pedidos concluídos podem ser avaliados.',
                ]);
            }
        });
    }
}
