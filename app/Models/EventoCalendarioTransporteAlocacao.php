<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoCalendarioTransporteAlocacao extends Model
{
    protected $table = 'evento_calendario_transporte_alocacoes';

    protected $fillable = [
        'evento_calendario_id', 'veiculo_transporte_id', 'motorista_id',
        'criado_por_id', 'removido_por_id', 'removido_em',
    ];

    protected function casts(): array
    {
        return [
            'evento_calendario_id' => 'integer',
            'veiculo_transporte_id' => 'integer',
            'motorista_id' => 'integer',
            'criado_por_id' => 'integer',
            'removido_por_id' => 'integer',
            'removido_em' => 'datetime',
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoCalendario::class, 'evento_calendario_id');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(VeiculoTransporte::class, 'veiculo_transporte_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'motorista_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_id');
    }

    public function removidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removido_por_id');
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->whereNull('removido_em');
    }

    public function scopeRemovidas(Builder $query): Builder
    {
        return $query->whereNotNull('removido_em');
    }

    public function estaAtiva(): bool
    {
        return $this->removido_em === null;
    }
}
