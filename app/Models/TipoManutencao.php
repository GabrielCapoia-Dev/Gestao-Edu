<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TipoManutencao extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'tipo_manutencao';

    protected $fillable = [
        'nome',
        'descricao',
        'ativo',
        'registro_anterior_id',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nome', 'descricao', 'ativo']);
    }

    public function registroAnterior()
    {
        return $this->belongsTo(TipoManutencao::class, 'registro_anterior_id');
    }

    public function historico()
    {
        return $this->hasMany(TipoManutencao::class, 'registro_anterior_id');
    }

    // public function pedidos()
    // {
    //     return $this->hasMany(Pedido::class, 'tipo_manutencao_id');
    // }
}