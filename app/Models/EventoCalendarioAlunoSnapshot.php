<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoCalendarioAlunoSnapshot extends Model
{
    protected $table = 'evento_calendario_alunos_snapshot';

    protected $guarded = [];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoCalendario::class, 'evento_calendario_id');
    }
}
