<?php

namespace App\Models\Enums;

enum NivelEmergenciaPedido: string
{
    case EMERGENCIAL = 'emergencial';
    case PREVENTIVO = 'preventivo';
    case CORRETIVO = 'corretivo';

    case INDEFINIDO = 'indeterminado';

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