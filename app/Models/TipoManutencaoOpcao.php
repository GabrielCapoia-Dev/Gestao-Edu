<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TipoManutencaoOpcao extends Model
{
    protected $table = 'tipo_manutencao_opcoes';

    protected $fillable = [
        'tipo_manutencao_id',
        'texto',
        'ativo',
        'alterado_por',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $opcao): void {
            if (Auth::check()) {
                $opcao->alterado_por = Auth::user()->name;
            }
        });
    }

    public function tipoManutencao()
    {
        return $this->belongsTo(TipoManutencao::class);
    }

    public function problemas()
    {
        return $this->hasMany(PedidoProblema::class, 'tipo_manutencao_opcao_id');
    }

    public function scopeAtivas($query)
    {
        return $query->where('ativo', true);
    }
}
