<?php

namespace App\Models;

use App\Models\Enums\BalancoEstoqueEventoTipo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalancoEstoqueEvento extends Model
{
    protected $table = 'balanco_estoque_eventos';

    protected $fillable = [
        'balanco_estoque_id',
        'tipo',
        'descricao',
        'dados',
        'usuario_id',
    ];

    protected $casts = [
        'tipo' => BalancoEstoqueEventoTipo::class,
        'dados' => 'array',
    ];

    public function balanco(): BelongsTo
    {
        return $this->belongsTo(BalancoEstoque::class, 'balanco_estoque_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
