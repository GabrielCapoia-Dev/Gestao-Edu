<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pauta extends Model
{
    use HasFactory;

    protected $table = 'pautas';

    protected $fillable = [
        'texto',
        'componente_curricular_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(ComponenteCurricular::class, 'componente_curricular_id');
    }

    public function alternativas(): BelongsToMany
    {
        return $this->belongsToMany(Alternativa::class, 'alternativa_pauta')
            ->withTimestamps();
    }

    public function avaliacoes(): BelongsToMany
    {
        return $this->belongsToMany(Avaliacao::class, 'avaliacao_pauta')
            ->withTimestamps();
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(AvaliacaoResposta::class, 'pauta_id');
    }
}
