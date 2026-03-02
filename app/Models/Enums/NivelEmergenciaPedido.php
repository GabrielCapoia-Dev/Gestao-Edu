<?php

namespace App\Models\Enums;

enum NivelEmergenciaPedido: string
{
    case EMERGENCIAL = 'Emergencial';
    case PREVENTIVO = 'Preventivo';
    case CORRETIVO = 'Corretivo';
    case INDEFINIDO = 'Indeterminado';

    public function label(): string
    {
        return match ($this) {
            self::EMERGENCIAL => 'Emergencial',
            self::PREVENTIVO => 'Preventivo',
            self::CORRETIVO => 'Corretivo',
            self::INDEFINIDO => 'Indeterminado',
        };
    }
}