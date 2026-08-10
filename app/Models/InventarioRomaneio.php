<?php

namespace App\Models;

use App\Models\Concerns\HasUuidCodigo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioRomaneio extends Model
{
    use HasUuidCodigo;

    protected $table = 'inventario_romaneios';

    protected $fillable = [
        'codigo',
        'observacoes',
        'gerado_por_id',
        'gerado_em',
    ];

    protected $casts = [
        'gerado_em' => 'datetime',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(InventarioPedido::class, 'inventario_romaneio_id');
    }

    public function geradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gerado_por_id')->withTrashed();
    }
}
