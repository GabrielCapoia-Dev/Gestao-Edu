<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ServidorFuncaoTurma extends Pivot
{
    public const STATUS_ATIVO = 'ativo';
    public const STATUS_INATIVO = 'inativo';

    protected $table = 'servidor_funcao_turma';

    public $incrementing = true;

    protected $fillable = [
        'servidor_funcao_administrativa_id',
        'turma_id',
        'principal',
        'status',
        'data_inicio',
        'data_fim',
    ];

    protected function casts(): array
    {
        return [
            'servidor_funcao_administrativa_id' => 'integer',
            'turma_id' => 'integer',
            'principal' => 'boolean',
            'data_inicio' => 'date',
            'data_fim' => 'date',
        ];
    }

    public function servidorFuncaoAdministrativa(): BelongsTo
    {
        return $this->belongsTo(ServidorFuncaoAdministrativa::class, 'servidor_funcao_administrativa_id');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
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
}
