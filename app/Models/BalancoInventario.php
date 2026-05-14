<?php

namespace App\Models;

use App\Models\Concerns\HasUuidCodigo;
use App\Models\Enums\BalancoInventarioStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BalancoInventario extends Model
{
    use HasUuidCodigo;

    protected $table = 'balancos_inventario';

    protected $fillable = [
        'inventario_id',
        'codigo',
        'status',
        'data_agendada',
        'observacao_inicial',
        'iniciado_em',
        'concluido_em',
        'cancelado_em',
        'criado_por_id',
        'iniciado_por_id',
        'concluido_por_id',
        'cancelado_por_id',
    ];

    protected $casts = [
        'status' => BalancoInventarioStatus::class,
        'data_agendada' => 'datetime',
        'iniciado_em' => 'datetime',
        'concluido_em' => 'datetime',
        'cancelado_em' => 'datetime',
    ];

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(Inventario::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_id');
    }

    public function iniciadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iniciado_por_id');
    }

    public function concluidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluido_por_id');
    }

    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(BalancoInventarioItem::class)->orderBy('id');
    }

    public function itensContagem(): HasMany
    {
        return $this->itens()->where('incluido_na_contagem', true);
    }

    public function itensFora(): HasMany
    {
        return $this->itens()->where('incluido_na_contagem', false);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(BalancoInventarioEvento::class)->orderByDesc('created_at');
    }

    public function isAgendado(): bool
    {
        return $this->status === BalancoInventarioStatus::Agendado;
    }

    public function isEmAndamento(): bool
    {
        return $this->status === BalancoInventarioStatus::EmAndamento;
    }

    public function isConcluido(): bool
    {
        return $this->status === BalancoInventarioStatus::Concluido;
    }

    public function isCancelado(): bool
    {
        return $this->status === BalancoInventarioStatus::Cancelado;
    }

    public function getImpactoFinanceiroTotalAttribute(): float
    {
        if (array_key_exists('impacto_financeiro_total', $this->attributes)) {
            return round((float) $this->attributes['impacto_financeiro_total'], 2);
        }

        return round((float) $this->itensContagem()->sum('valor_impacto'), 2);
    }
}
