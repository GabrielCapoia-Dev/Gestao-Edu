<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Builder;

class ServidorFuncaoAdministrativa extends Pivot
{
    public const STATUS_ATIVO = 'ativo';
    public const STATUS_INATIVO = 'inativo';

    protected $table = 'servidor_funcao_administrativa';

    public $incrementing = true;

    protected $fillable = [
        'servidor_id',
        'funcao_administrativa_id',
        'matricula',
        'id_escola',
        'setor_id',
        'status',
        'origem',
        'portaria',
        'principal',
        'data_inicio',
        'data_fim',
    ];

    protected function casts(): array
    {
        return [
            'servidor_id' => 'integer',
            'funcao_administrativa_id' => 'integer',
            'id_escola' => 'integer',
            'setor_id' => 'integer',
            'principal' => 'boolean',
            'data_inicio' => 'date',
            'data_fim' => 'date',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'servidor_id')->withTrashed();
    }

    public function funcaoAdministrativa(): BelongsTo
    {
        return $this->belongsTo(FuncaoAdministrativa::class, 'funcao_administrativa_id');
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class, 'id_escola');
    }

    public function escolasAssessoradas(): BelongsToMany
    {
        return $this->belongsToMany(
            Escola::class,
            'assessoria_pedagogica_escola',
            'servidor_funcao_administrativa_id',
            'escola_id',
        )->withTimestamps();
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function professor(): HasOne
    {
        return $this->hasOne(Professor::class, 'servidor_funcao_administrativa_id');
    }

    public function professores(): HasMany
    {
        return $this->hasMany(Professor::class, 'servidor_funcao_administrativa_id');
    }

    public function isAtivo(): bool
    {
        return $this->status === self::STATUS_ATIVO;
    }

    public function isVigente(): bool
    {
        $hoje = now()->startOfDay();

        return $this->isAtivo()
            && ($this->data_inicio === null || $this->data_inicio->startOfDay()->lte($hoje))
            && ($this->data_fim === null || $this->data_fim->endOfDay()->gte($hoje));
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ATIVO);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        $hoje = now()->toDateString();

        return $query
            ->where('status', self::STATUS_ATIVO)
            ->where(function (Builder $inicio) use ($hoje): void {
                $inicio->whereNull('data_inicio')->orWhereDate('data_inicio', '<=', $hoje);
            })
            ->where(function (Builder $fim) use ($hoje): void {
                $fim->whereNull('data_fim')->orWhereDate('data_fim', '>=', $hoje);
            });
    }

    public function vinculosTurma(): HasMany
    {
        return $this->hasMany(ServidorFuncaoTurma::class, 'servidor_funcao_administrativa_id');
    }

    public function vinculosTurmaAtivos(): HasMany
    {
        return $this->vinculosTurma()->where('status', ServidorFuncaoTurma::STATUS_ATIVO);
    }

    public function turmas(): BelongsToMany
    {
        return $this->belongsToMany(
            Turma::class,
            'servidor_funcao_turma',
            'servidor_funcao_administrativa_id',
            'turma_id',
        )
            ->using(ServidorFuncaoTurma::class)
            ->wherePivot('status', ServidorFuncaoTurma::STATUS_ATIVO)
            ->withPivot(['id', 'principal', 'status', 'data_inicio', 'data_fim'])
            ->withTimestamps();
    }
}
