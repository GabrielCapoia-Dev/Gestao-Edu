<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodoAvaliacao extends Model
{
    use HasFactory;

    protected $table = 'periodos_avaliacao';

    protected $fillable = [
        'nome',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function avaliacoes(): HasMany
    {
        return $this->hasMany(Avaliacao::class, 'periodo_avaliacao_id');
    }
}
