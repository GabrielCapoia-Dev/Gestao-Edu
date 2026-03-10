<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TipoManutencao extends Model
{
    use HasFactory;

    protected $table = 'tipo_manutencao';

    protected $fillable = [
        'nome',
        'descricao',
        'ativo',
        'registro_anterior_id',
        'alterado_por',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];
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
