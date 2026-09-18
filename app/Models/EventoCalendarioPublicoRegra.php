<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoCalendarioPublicoRegra extends Model
{
    protected $table = 'evento_calendario_publico_regras';

    protected $fillable = ['evento_calendario_id', 'filtros'];

    protected function casts(): array
    {
        return ['filtros' => 'array'];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoCalendario::class, 'evento_calendario_id');
    }
}
