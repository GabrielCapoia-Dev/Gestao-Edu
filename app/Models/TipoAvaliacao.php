<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoAvaliacao extends Model
{
    use HasFactory;

    protected $table = 'tipos_avaliacao';

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

    public function alternativas(): HasMany
    {
        return $this->hasMany(Alternativa::class, 'tipo_avaliacao_id');
    }

    public function pautas(): HasMany
    {
        return $this->hasMany(Pauta::class, 'tipo_avaliacao_id');
    }

    public function avaliacoes(): HasMany
    {
        return $this->hasMany(Avaliacao::class, 'tipo_avaliacao_id');
    }
}
