<?php

namespace App\Models;

use App\Models\Concerns\HasUuidCodigo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class LocalTrabalho extends Model
{
    use HasUuidCodigo;

    protected $table = 'escolas';

    protected $fillable = [
        'codigo',
        'setor_id',
        'nome',
        'email',
        'telefone',
        'logradouro',
        'numero',
        'bairro',
        'cep',
        'cidade',
        'estado',
        'complemento',
        'ativo',
        'nao_e_escola',
        'registro_anterior_id',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'nao_e_escola' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function (LocalTrabalho $local): void {
            if (! $local->isDirty('nao_e_escola')) {
                return;
            }

            throw ValidationException::withMessages([
                'nao_e_escola' => 'A classificação do local de trabalho não pode ser alterada após o cadastro.',
            ]);
        });
    }

    public function registroAnterior(): BelongsTo
    {
        return $this->belongsTo(static::class, 'registro_anterior_id');
    }

    public function historicoPosteriores(): HasMany
    {
        return $this->hasMany(static::class, 'registro_anterior_id');
    }

    public function inventario(): HasOne
    {
        return $this->hasOne(Inventario::class, 'escola_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'escola_user', 'escola_id', 'user_id')
            ->withTimestamps();
    }

    public function vinculosAssessoriaPedagogica(): BelongsToMany
    {
        return $this->belongsToMany(
            ServidorFuncaoAdministrativa::class,
            'assessoria_pedagogica_escola',
            'escola_id',
            'servidor_funcao_administrativa_id',
        )->withTimestamps();
    }

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class, 'id_escola');
    }

    public function lotacoes(): HasMany
    {
        return $this->hasMany(Lotacao::class, 'escola_id');
    }

    public function reservasVeiculos(): HasMany
    {
        return $this->hasMany(ReservaVeiculo::class, 'escola_id');
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function avaliacoes(): BelongsToMany
    {
        return $this->belongsToMany(Avaliacao::class, 'avaliacao_escola', 'escola_id', 'avaliacao_id')
            ->withTimestamps();
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function scopeEscolas(Builder $query): Builder
    {
        return $query->where('nao_e_escola', false);
    }

    public function scopeNaoEscolares(Builder $query): Builder
    {
        return $query->where('nao_e_escola', true);
    }

    public function scopePorCodigo(Builder $query, string $codigo): Builder
    {
        return $query->where('codigo', $codigo);
    }

    public function getHistoricoCompleto()
    {
        return static::where('codigo', $this->codigo)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function desativar(): void
    {
        $this->update(['ativo' => false]);
    }

    public function tipoLabel(): string
    {
        return $this->nao_e_escola ? 'Local não escolar' : 'Escola';
    }
}
