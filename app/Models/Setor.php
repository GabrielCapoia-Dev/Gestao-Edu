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
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->alterado_por = Auth::user()->name;
            }
        });
    }

    public function registroAnterior()
    {
        return $this->belongsTo(Setor::class, 'registro_anterior_id');
    }

    public function historico()
    {
        return $this->hasMany(Setor::class, 'registro_anterior_id');
    }

    public function historicoCompleto()
    {
        $raiz = $this;

        while ($raiz->registro_anterior_id) {
            $raiz = $raiz->registroAnterior;
        }

        $historico = collect();
        $atual = $raiz;

        while ($atual) {
            $historico->push($atual);
            $atual = self::where('registro_anterior_id', $atual->id)
                ->orderByDesc('created_at')
                ->first();
        }

        return $historico->sortByDesc('created_at');
    }

    public function tiposStatus()
    {
        return $this->hasMany(TipoStatus::class, 'id_setor');
    }
}
