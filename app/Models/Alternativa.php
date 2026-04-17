<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alternativa extends Model
{
    use HasFactory;

    protected $table = 'alternativas';

    protected $fillable = [
        'nome',
        'tem_observacao',
        'observacao',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tem_observacao' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function pautas(): BelongsToMany
    {
        return $this->belongsToMany(Pauta::class, 'alternativa_pauta')
            ->withTimestamps();
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(AvaliacaoResposta::class, 'alternativa_id');
    }
}
