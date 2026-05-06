<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoProblema extends Model
{
    protected $table = 'pedido_problemas';

    protected $fillable = [
        'pedido_id',
        'tipo_manutencao_id',
        'tipo_manutencao_opcao_id',
        'texto_problema',
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function tipoManutencao()
    {
        return $this->belongsTo(TipoManutencao::class);
    }

    public function opcao()
    {
        return $this->belongsTo(TipoManutencaoOpcao::class, 'tipo_manutencao_opcao_id');
    }

    public function feedbackItens()
    {
        return $this->hasMany(FeedbackPedidoItem::class);
    }
}
