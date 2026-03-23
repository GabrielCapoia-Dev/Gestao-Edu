<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoStatus extends Model
{
    use HasFactory;

    protected $table = 'tipo_status';

    protected $fillable = [
        'nome',
        'cor',
        'finaliza_pedido',
        'cancela_pedido',
        'ativo',
        'registro_anterior_id',
    ];

    protected $casts = [
        'finaliza_pedido' => 'boolean',
        'cancela_pedido' => 'boolean',
        'ativo' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Histórico
    |--------------------------------------------------------------------------
    */

    public function registroAnterior()
    {
        return $this->belongsTo(self::class, 'registro_anterior_id');
    }

    public function historico()
    {
        return $this->hasMany(self::class, 'registro_anterior_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }
}
