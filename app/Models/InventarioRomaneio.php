<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioRomaneio extends Model
{
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

    protected static function booted(): void
    {
        static::created(function (self $romaneio): void {
            if (filled($romaneio->codigo)) {
                return;
            }

            $romaneio->forceFill([
                'codigo' => 'ROM-' . str_pad((string) $romaneio->getKey(), 6, '0', STR_PAD_LEFT),
            ])->saveQuietly();
        });
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(InventarioPedido::class, 'inventario_romaneio_id');
    }

    public function geradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gerado_por_id');
    }
}
