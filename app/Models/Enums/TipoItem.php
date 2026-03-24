<?php

namespace App\Models\Enums;

enum TipoItem: string
{
    case CerealDerivado      = 'cereal_derivado';
    case Leguminosa          = 'leguminosa';
    case Hortalica           = 'hortalica';
    case Fruta               = 'fruta';
    case CarneBovina         = 'carne_bovina';
    case CarneSuina          = 'carne_suina';
    case Ave                 = 'ave';
    case Pescado             = 'pescado';
    case Laticinios          = 'laticinios';
    case Bebida              = 'bebida';
    case Industrializado     = 'industrializado';

    public function label(): string
    {
        return match ($this) {
            self::CerealDerivado    => 'Cereal e Derivado',
            self::Leguminosa        => 'Leguminosa',
            self::Hortalica         => 'Hortaliça',
            self::Fruta             => 'Fruta',
            self::CarneBovina       => 'Carne Bovina',
            self::CarneSuina        => 'Carne Suína',
            self::Ave               => 'Ave',
            self::Pescado           => 'Pescado',
            self::Laticinios        => 'Laticínios',
            self::Bebida            => 'Bebida',
            self::Industrializado   => 'Industrializado',
        };
    }
}