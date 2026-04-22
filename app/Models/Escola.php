<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Escola extends Model
{
    protected $fillable = [
        'codigo',
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
        'registro_anterior_id',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    // ================= RELAÇÕES =================

    public function registroAnterior(): BelongsTo
    {
        return $this->belongsTo(Escola::class, 'registro_anterior_id');
    }

    public function historicoPosteriores(): HasMany
    {
        return $this->hasMany(Escola::class, 'registro_anterior_id');
    }

    public function inventario(): HasOne
    {
        return $this->hasOne(Inventario::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'escola_user', 'escola_id', 'user_id')
            ->withTimestamps();
    }

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class, 'id_escola');
    }

    public function avaliacoes(): BelongsToMany
    {
        return $this->belongsToMany(Avaliacao::class, 'avaliacao_escola', 'escola_id', 'avaliacao_id')
            ->withTimestamps();
    }

    // ================= SCOPES =================

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function scopePorCodigo(Builder $query, string $codigo): Builder
    {
        return $query->where('codigo', $codigo);
    }

    // ================= MÉTODOS =================

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
}
