<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoCalendarioParticipanteSnapshot extends Model
{
    protected $table = 'evento_calendario_participantes_snapshot';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['escola_ids' => 'array'];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoCalendario::class, 'evento_calendario_id');
    }
}
