<?php

namespace App\Models\Enums;

enum SetorContexto: string
{
    case Central = 'central';
    case Escolar = 'escolar';
    case Cmei = 'cmei';

    public function label(): string
    {
        return match ($this) {
            self::Central => 'Central / Administrativo',
            self::Escolar => 'Escola',
            self::Cmei => 'CMEI',
        };
    }

    public function exigeVinculoEscola(): bool
    {
        return match ($this) {
            self::Central => false,
            self::Escolar, self::Cmei => true,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}