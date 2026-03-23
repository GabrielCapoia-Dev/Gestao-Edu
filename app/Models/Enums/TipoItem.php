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
    case Ovo                 = 'ovo';
    case Laticinios          = 'laticinios';
    case OleoGordura         = 'oleo_gordura';
    case AcucarDoce          = 'acucar_doce';
    case Bebida              = 'bebida';
    case Condimento          = 'condimento';
    case SementeOleaginosa   = 'semente_oleaginosa';
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
            self::Ovo               => 'Ovo',
            self::Laticinios        => 'Laticínios',
            self::OleoGordura       => 'Óleo e Gordura',
            self::AcucarDoce        => 'Açúcar e Doce',
            self::Bebida            => 'Bebida',
            self::Condimento        => 'Condimento e Tempero',
            self::SementeOleaginosa => 'Semente e Oleaginosa',
            self::Industrializado   => 'Industrializado',
        };
    }
}