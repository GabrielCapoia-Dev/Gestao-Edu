<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaixasEstoques extends Model
{
    protected $table = 'baixas_estoques';

    protected $fillable = [
        'estoque_id',
        'quantidade',
        'motivo',
        'saldo_anterior',
        'saldo_posterior',
        'registrado_por',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
        'saldo_anterior' => 'decimal:3',
        'saldo_posterior' => 'decimal:3',
    ];

    public function estoque(): BelongsTo
    {
        return $this->belongsTo(Estoque::class);
    }
}
