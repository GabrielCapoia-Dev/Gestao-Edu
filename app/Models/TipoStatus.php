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
    | Activity Log
    |--------------------------------------------------------------------------
    */

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'nome',
                'cor',
                'finaliza_pedido',
                'cancela_pedido',
                'ativo',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Relacionamentos
    |--------------------------------------------------------------------------
    */

    public function setores()
    {
        return $this->belongsToMany(
            Setor::class,
            'setor_tipo_status',
            'tipo_status_id',
            'setor_id'
        );
    }

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
