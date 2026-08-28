<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvaliacaoTurmaTokenEscrita extends Model
{
    protected $table = 'avaliacao_turma_tokens_escrita';
    protected $guarded = [];

    public function ciclo(): BelongsTo { return $this->belongsTo(AvaliacaoTurmaCiclo::class, 'ciclo_id'); }
}
