<?php

namespace App\Models;

use App\Models\Enums\MotivoBaixa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioBaixa extends Model
{
    protected $table = 'inventario_baixas';

    protected $fillable = [
        'inventario_estoque_id',
        'quantidade',
        'motivo',
        'descricao',
        'saldo_anterior',
        'saldo_posterior',
        'registrado_por',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
        'saldo_anterior' => 'decimal:3',
        'saldo_posterior' => 'decimal:3',
        'motivo' => MotivoBaixa::class,
    ];

    public function estoque(): BelongsTo
    {
        return $this->belongsTo(InventarioEstoque::class, 'inventario_estoque_id');
    }
}
