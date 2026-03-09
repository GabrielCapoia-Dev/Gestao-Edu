<?php

namespace App\Models\Enums;

use function Symfony\Component\String\s;

enum UnidadeMedida: string
{
    case PACOTE = 'Pacote';
    case UNIDADE = 'Unidade';
    case QUILOGRAMA = 'Quilograma';
    case LITRO = 'Litro';
    case BANDEJA = 'Bandeja';
    case CAIXA = 'Caixa';
    case DEZENA = 'Dezena';
    case DUZIA = 'Duzia';


    public function label(): string
    {
        return match ($this) {
            self::PACOTE => 'Pacote',
            self::UNIDADE => 'Unidade',
            self::QUILOGRAMA => 'Quilograma',
            self::LITRO => 'Litro',
            self::BANDEJA => 'Bandeja',
            self::CAIXA => 'Caixa',
            self::DEZENA => 'Dezena',
            self::DUZIA => 'Duzia',
        };
    }
}
