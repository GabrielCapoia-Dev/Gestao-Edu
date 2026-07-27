<?php

namespace App\Models\Enums;

enum ReservaVeiculoStatus: string
{
    case ATIVA = 'ativa';
    case CANCELADA = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::ATIVA => 'Reservada',
            self::CANCELADA => 'Cancelada',
        };
    }
}
