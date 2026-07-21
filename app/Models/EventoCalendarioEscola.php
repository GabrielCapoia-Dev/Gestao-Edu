<?php

namespace App\Models;

use App\Models\Enums\EventoCalendarioTransporteEscopo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EventoCalendarioEscola extends Model
{
    protected $table = 'evento_calendario_escolas';

    protected $fillable = [
        'evento_calendario_id',
        'escola_id',
        'hora_inicio',
        'hora_fim',
        'precisa_transporte',
        'escopo_transporte',
        'quantidade_estimada_transporte',
    ];

    protected function casts(): array
    {
        return [
            'precisa_transporte' => 'boolean',
            'escopo_transporte' => EventoCalendarioTransporteEscopo::class,
            'quantidade_estimada_transporte' => 'integer',
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoCalendario::class, 'evento_calendario_id');
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class);
    }

    public function series(): BelongsToMany
    {
        return $this->belongsToMany(
            Serie::class,
            'evento_calendario_escola_serie',
            'evento_calendario_escola_id',
            'serie_id',
        );
    }

    public function turmas(): BelongsToMany
    {
        return $this->belongsToMany(
            Turma::class,
            'evento_calendario_escola_turma',
            'evento_calendario_escola_id',
            'turma_id',
        );
    }
}
