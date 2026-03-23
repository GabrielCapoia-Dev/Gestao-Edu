<?php

namespace App\Models\Enums;

enum UnidadeMedida: string
{
    // Volume
    case Litro          = 'L';
    case Mililitro      = 'mL';

    // Massa
    case Quilograma     = 'kg';
    case Grama          = 'g';

    // Unidade
    case Unidade        = 'un';
    case Pacote         = 'pct';
    case Caixa          = 'cx';
    case Fardo          = 'frd';
    case Saco           = 'sc';
    case Lata           = 'lt';
    case Frasco         = 'fr';
    case Garrafa        = 'grf';
    case Galao          = 'gal';
    case Balde          = 'bld';

    // Contagem
    case Duzia          = 'dz';
    case Cento          = 'cto';

    public function label(): string
    {
        return match ($this) {
            self::Litro         => 'Litro (L)',
            self::Mililitro     => 'Mililitro (mL)',
            self::Quilograma    => 'Quilograma (kg)',
            self::Grama         => 'Grama (g)',
            self::Unidade       => 'Unidade',
            self::Pacote        => 'Pacote',
            self::Caixa         => 'Caixa',
            self::Fardo         => 'Fardo',
            self::Saco          => 'Saco',
            self::Lata          => 'Lata',
            self::Frasco        => 'Frasco',
            self::Garrafa       => 'Garrafa',
            self::Galao         => 'Galão',
            self::Balde         => 'Balde',
            self::Duzia         => 'Dúzia',
            self::Cento         => 'Cento',
        };
    }
}