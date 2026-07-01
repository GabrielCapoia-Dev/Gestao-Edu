<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Alternativa extends Model
{
    use HasFactory;

    protected $table = 'alternativas';

    protected $fillable = [
        'tipo_avaliacao_id',
        'nome',
        'tem_observacao',
        'observacao',
        'vai_no_documento',
        'descricao_documento',
        'ordem_documento',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tem_observacao' => 'boolean',
            'vai_no_documento' => 'boolean',
            'ordem_documento' => 'integer',
            'status' => 'boolean',
        ];
    }

    public function scopeOrderedForDocumento(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN ordem_documento IS NULL THEN 1 ELSE 0 END')
            ->orderBy('ordem_documento')
            ->orderBy('nome');
    }

    /**
     * @param  Collection<int, self>  $alternativas
     * @return Collection<int, self>
     */
    public static function sortCollectionForDocumento(Collection $alternativas): Collection
    {
        return $alternativas
            ->sort(function (self $a, self $b): int {
                $ordem = ($a->ordem_documento ?? PHP_INT_MAX) <=> ($b->ordem_documento ?? PHP_INT_MAX);

                if ($ordem !== 0) {
                    return $ordem;
                }

                $nome = strnatcasecmp((string) $a->nome, (string) $b->nome);

                if ($nome !== 0) {
                    return $nome;
                }

                return (int) $a->getKey() <=> (int) $b->getKey();
            })
            ->values();
    }

    public function pautas(): BelongsToMany
    {
        return $this->belongsToMany(Pauta::class, 'alternativa_pauta')
            ->withTimestamps();
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoAvaliacao::class, 'tipo_avaliacao_id');
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(AvaliacaoResposta::class, 'alternativa_id');
    }

    public function avaliacoesComOverride(): BelongsToMany
    {
        return $this->belongsToMany(Avaliacao::class, 'avaliacao_pauta_alternativa')
            ->withPivot('pauta_id')
            ->withTimestamps();
    }
}
