<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Contrato extends Model
{
    protected $table = 'contratos';

    protected $fillable = [
        'id_empresa_contratada',
        'numero_contrato',
        'data_inicio',
        'data_vencimento',
        'observacoes',
        'ativo',
        'alterado_por',
    ];

    protected $casts = [
        'data_inicio'     => 'date',
        'data_vencimento' => 'date',
        'ativo'           => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function ($model) {
            if (Auth::check()) {
                $model->alterado_por = Auth::user()->name;
            }
        });
    }

    public function empresaContratada()
    {
        return $this->belongsTo(EmpresaContratada::class, 'id_empresa_contratada');
    }

    public function itens()
    {
        return $this->belongsToMany(Item::class, 'contrato_item')
            ->using(ContratoItem::class)
            ->withPivot('quantidade_total', 'quantidade_utilizada', 'preco_unitario', 'preco_total')
            ->withTimestamps();
    }
}
