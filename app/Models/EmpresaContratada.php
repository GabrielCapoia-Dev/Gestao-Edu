<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Services\UserSetorAccessService;

class EmpresaContratada extends Model
{
    protected $table = 'empresas_contratadas';

    protected $fillable = [

        'nome', // Nome da empresa
        'cnpj', // CNPJ
        'email', // Email corporativo
        'responsavel', // Nome do responsável pelo contrato
        'telefone', // Telefone principal
        'setor_id',

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

                if (blank($model->setor_id)) {
                    $model->setor_id = app(UserSetorAccessService::class)->primarySetorId(Auth::user());
                }

                if (filled($model->setor_id)) {
                    app(UserSetorAccessService::class)->assertCanUseSetor(Auth::user(), (int) $model->setor_id);
                }
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

    public function contratos()
    {
        return $this->hasMany(Contrato::class, 'id_empresa_contratada');
    }

    public function setor()
    {
        return $this->belongsTo(Setor::class);
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

    public function scopeDoSetorDoUsuario(Builder $query, ?User $user = null): Builder
    {
        return app(UserSetorAccessService::class)->applySetorScope($query, $user ?? Auth::user());
    }
}
