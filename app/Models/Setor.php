<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Setor extends Model
{
    protected $table = 'setor';

    protected $fillable = [
        'nome',
        'status',
        'alterado_por',
        'ativo',
        'registro_anterior_id'
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    protected static function booted()
    {
        static::saving(function ($model) {
            if (Auth::check()) {
                $model->alterado_por = Auth::user()->name;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relacionamentos
    |--------------------------------------------------------------------------
    */

    public function tiposStatus()
    {
        return $this->belongsToMany(
            TipoStatus::class,
            'setor_tipo_status',
            'setor_id',
            'tipo_status_id'
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
