<?php

namespace App\Models;

use App\Services\SetorHierarchyService;
use App\Services\UserSetorAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Contrato extends Model
{
    protected $table = 'contratos';

    protected $fillable = [
        'id_empresa_contratada',
        'setor_id',
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

            if (blank($model->setor_id) && filled($model->id_empresa_contratada)) {
                $model->setor_id = EmpresaContratada::query()
                    ->whereKey($model->id_empresa_contratada)
                    ->value('setor_id');
            }

            if (Auth::check() && filled($model->setor_id)) {
                app(UserSetorAccessService::class)->assertCanUseSetor(Auth::user(), (int) $model->setor_id);
            }

            $empresaSetorId = filled($model->id_empresa_contratada)
                ? EmpresaContratada::query()->whereKey($model->id_empresa_contratada)->value('setor_id')
                : null;

            if (
                filled($empresaSetorId)
                && filled($model->setor_id)
                && ! app(SetorHierarchyService::class)->isAncestorOf((int) $empresaSetorId, (int) $model->setor_id)
            ) {
                throw ValidationException::withMessages([
                    'setor_id' => 'O setor do contrato precisa estar dentro da arvore do setor da empresa contratada.',
                ]);
            }
        });
    }

    public function empresaContratada()
    {
        return $this->belongsTo(EmpresaContratada::class, 'id_empresa_contratada');
    }

    public function setor()
    {
        return $this->belongsTo(Setor::class);
    }

    // 🔥 RELAÇÃO PRINCIPAL
    public function contratoItens()
    {
        return $this->hasMany(ContratoItem::class);
    }
}
