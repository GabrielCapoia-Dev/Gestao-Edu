<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaldoEleitoral extends Model
{
    public const TIPO_ADICAO = 'adicao';

    public const TIPO_USO = 'uso';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_APROVADO = 'aprovado';

    public const STATUS_REJEITADO = 'rejeitado';

    protected $table = 'saldos_eleitorais';

    protected $fillable = [
        'servidor_id', 'solicitante_id', 'aprovador_id', 'tipo', 'dias', 'status',
        'lancamento_manual', 'observacao', 'decidido_em',
    ];

    protected function casts(): array
    {
        return ['dias' => 'integer', 'lancamento_manual' => 'boolean', 'decidido_em' => 'datetime'];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class)->withTrashed();
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id')->withTrashed();
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovador_id')->withTrashed();
    }

    public function scopePendentes(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDENTE);
    }
}
