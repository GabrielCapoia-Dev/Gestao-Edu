<?php

namespace App\Models;

use App\Models\Enums\ReservaVeiculoStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservaVeiculo extends Model
{
    protected $table = 'reservas_veiculos';

    protected $fillable = [
        'veiculo_transporte_id',
        'usuario_id',
        'escola_id',
        'local_nome',
        'atividade',
        'data_inicio',
        'data_fim',
        'status',
        'grupo_recorrencia',
        'criado_por_id',
        'atualizado_por_id',
        'cancelado_por_id',
        'cancelado_em',
        'motivo_cancelamento',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'datetime',
            'data_fim' => 'datetime',
            'status' => ReservaVeiculoStatus::class,
            'cancelado_em' => 'datetime',
        ];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(VeiculoTransporte::class, 'veiculo_transporte_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_id');
    }

    public function atualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizado_por_id');
    }

    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por_id');
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('status', ReservaVeiculoStatus::ATIVA->value);
    }

    public function estaAtiva(): bool
    {
        return $this->status === ReservaVeiculoStatus::ATIVA;
    }
}
