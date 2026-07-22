<?php

namespace App\Models;

use App\Models\Enums\EventoCalendarioHistoricoAcao;
use App\Models\Enums\EventoCalendarioStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoCalendarioHistorico extends Model
{
    protected $table = 'evento_calendario_historicos';

    protected $fillable = [
        'evento_calendario_id',
        'usuario_id',
        'acao',
        'status_anterior',
        'status_novo',
        'motivo',
    ];

    protected function casts(): array
    {
        return [
            'acao' => EventoCalendarioHistoricoAcao::class,
            'status_anterior' => EventoCalendarioStatus::class,
            'status_novo' => EventoCalendarioStatus::class,
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoCalendario::class, 'evento_calendario_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
