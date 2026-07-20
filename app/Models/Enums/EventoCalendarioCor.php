<?php

namespace App\Models\Enums;

enum EventoCalendarioCor: string
{
    case AZUL = 'azul';
    case VERDE = 'verde';
    case AMBAR = 'ambar';
    case VERMELHO = 'vermelho';
    case VIOLETA = 'violeta';
    case CIANO = 'ciano';
    case CINZA = 'cinza';

    public function label(): string
    {
        return match ($this) {
            self::AZUL => 'Azul',
            self::VERDE => 'Verde',
            self::AMBAR => 'Âmbar',
            self::VERMELHO => 'Vermelho',
            self::VIOLETA => 'Violeta',
            self::CIANO => 'Ciano',
            self::CINZA => 'Cinza',
        };
    }
}
