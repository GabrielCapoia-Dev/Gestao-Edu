<?php

namespace App\Models;

use App\Models\Enums\TipoMovimentacao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioMovimentacao extends Model
{
    protected $table = 'inventario_movimentacoes';

    protected $fillable = [
        'inventario_estoque_id',
        'tipo',
        'quantidade',
        'inventario_pedido_id',
        'observacao',
        'registrado_por',
    ];

    protected $casts = [
        'tipo' => TipoMovimentacao::class,
        'quantidade' => 'decimal:3',
    ];

    public function estoque(): BelongsTo
    {
        return $this->belongsTo(InventarioEstoque::class, 'inventario_estoque_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(InventarioPedido::class, 'inventario_pedido_id');
    }
}
