<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VeiculoTransporte extends Model
{
    protected $table = 'veiculos_transporte';

    protected $fillable = [
        'placa',
        'identificacao',
        'capacidade_passageiros',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'capacidade_passageiros' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public static function normalizarPlaca(?string $placa): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', (string) $placa) ?? '');
    }

    public function setPlacaAttribute(?string $value): void
    {
        $this->attributes['placa'] = static::normalizarPlaca($value);
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
