<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoCalendarioPublicoExcecao extends Model
{
    protected $table = 'evento_calendario_publico_excecoes';

    public $incrementing = false;

    protected $fillable = ['evento_calendario_id', 'user_id'];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoCalendario::class, 'evento_calendario_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
