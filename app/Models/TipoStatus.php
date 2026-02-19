<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TipoStatus extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'tipo_status';

    protected $fillable = [
        'nome',
        'cor',

        'finaliza_pedido',
        'cancela_pedido',
        'id_setor',
        'ativo',
        'registro_anterior_id',
    ];

    protected $casts = [
        'finaliza_pedido' => 'boolean',
        'cancela_pedido' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nome', 'cor', 'finaliza_pedido', 'cancela_pedido', 'ativo']);
    }

    public function registroAnterior()
    {
        return $this->belongsTo(TipoStatus::class, 'registro_anterior_id');
    }

    public function historico()
    {
        return $this->hasMany(TipoStatus::class, 'registro_anterior_id');
    }

    // public function pedidos()
    // {
    //     return $this->hasMany(Pedido::class, 'tipo_status_id');
    // }

    public function setor()
    {
        return $this->belongsTo(Setor::class, 'id_setor');
    }
}