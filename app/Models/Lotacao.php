<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lotacao extends Model
{
    protected $table = 'lotacoes';

    protected $fillable = [
        'codigo',
        'nome',
    ];

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class);
    }

    public function localTrabalho(): BelongsTo
    {
        return $this->belongsTo(LocalTrabalho::class, 'escola_id');
    }
}
