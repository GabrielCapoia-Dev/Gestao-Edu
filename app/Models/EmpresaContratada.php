<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EmpresaContratada extends Model
{
    protected $table = 'empresas_contratadas';

    protected $fillable = [

        'nome', // Nome da empresa
        'cnpj', // CNPJ
        'email', // Email corporativo
        'responsavel', // Nome do responsável pelo contrato
        'telefone', // Telefone principal

        // Endereço
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'estado',

        // Controle
        'ativo',
        'registro_anterior_id',
        'alterado_por',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

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

    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'empresa_contratada_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Histórico (caso mantenha versionamento)
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

    public function scopeAtivas($query)
    {
        return $query->where('ativo', true);
    }
}
