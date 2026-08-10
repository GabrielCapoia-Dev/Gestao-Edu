<?php

namespace App\Models;

use App\Models\Enums\InventarioPedidoStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioPedido extends Model
{
    protected $table = 'inventario_pedidos';

    protected $fillable = [
        'inventario_id',
        'escola_id',
        'status',
        'observacao_escola',
        'observacao_gestor',
        'observacao_conferencia',
        'inventario_romaneio_id',
        'solicitado_por_id',
        'aprovado_por_id',
        'entregue_por_id',
        'aprovado_em',
        'em_andamento_em',
        'entregue_em',
    ];

    protected $casts = [
        'status' => InventarioPedidoStatus::class,
        'aprovado_em' => 'datetime',
        'em_andamento_em' => 'datetime',
        'entregue_em' => 'datetime',
    ];

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(Inventario::class);
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(InventarioPedidoItem::class);
    }

    public function romaneio(): BelongsTo
    {
        return $this->belongsTo(InventarioRomaneio::class, 'inventario_romaneio_id');
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por_id')->withTrashed();
    }

    public function aprovadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por_id')->withTrashed();
    }

    public function entreguePor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregue_por_id')->withTrashed();
    }

    public function isPendente(): bool
    {
        return $this->status === InventarioPedidoStatus::Pendente;
    }

    public function isAprovado(): bool
    {
        return $this->status === InventarioPedidoStatus::Aprovado;
    }

    public function isEmAndamento(): bool
    {
        return $this->status === InventarioPedidoStatus::EmAndamento;
    }

    public function isEntregue(): bool
    {
        return $this->status === InventarioPedidoStatus::Entregue;
    }
}
