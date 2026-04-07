<?php

namespace App\Models;

use App\Models\Enums\BalancoInventarioEventoTipo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalancoInventarioEvento extends Model
{
    protected $table = 'balanco_inventario_eventos';

    protected $fillable = [
        'balanco_inventario_id',
        'tipo',
        'descricao',
        'dados',
        'usuario_id',
    ];

    protected $casts = [
        'tipo' => BalancoInventarioEventoTipo::class,
        'dados' => 'array',
    ];

    public function balanco(): BelongsTo
    {
        return $this->belongsTo(BalancoInventario::class, 'balanco_inventario_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
