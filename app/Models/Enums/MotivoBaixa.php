<?php

namespace App\Models\Enums;

enum MotivoBaixa: string
{
    case Vencimento = 'vencimento';
    case Avaria = 'avaria';
    case Perda = 'perda';
    case Extravio = 'extravio';
    case Contaminacao = 'contaminacao';
    case AjusteInventario = 'ajuste_inventario';
    case ConsumoInterno = 'consumo_interno';
    case Outro = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::Vencimento => 'Vencimento',
            self::Avaria => 'Avaria',
            self::Perda => 'Perda',
            self::Extravio => 'Extravio',
            self::Contaminacao => 'Contaminação',
            self::AjusteInventario => 'Ajuste de Inventário',
            self::ConsumoInterno => 'Consumo Interno',
            self::Outro => 'Outro',
        };
    }
}
