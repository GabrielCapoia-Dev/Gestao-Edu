<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoHistorico extends Model
{
    protected $table = 'pedido_historicos';

    protected $fillable = [

        'pedido_id', // Pedido que sofreu alteração

        'status_anterior_id', // Status antes da alteração
        'status_novo_id', // Status após alteração

        'usuario_id', // Último usuário responsável que realizou a alteração
        'setor_id', // Setor que realizou a alteração

        'descricao_alteracao', // Descrição detalhando o que aconteceu
    ];

    /*
    |--------------------------------------------------------------------------
    | Relacionamentos
    |--------------------------------------------------------------------------
    */

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function statusAnterior()
    {
        return $this->belongsTo(TipoStatus::class, 'status_anterior_id');
    }

    public function statusNovo()
    {
        return $this->belongsTo(TipoStatus::class, 'status_novo_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function setor()
    {
        return $this->belongsTo(Setor::class, 'setor_id');
    }
}
