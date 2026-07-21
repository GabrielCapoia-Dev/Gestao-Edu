<?php

namespace App\Models\Enums;

enum EventoCalendarioTransporteEscopo: string
{
    case TODA_UNIDADE = 'toda_unidade';
    case SERIES = 'series';
    case TURMAS = 'turmas';

    public function label(): string
    {
        return match ($this) {
            self::TODA_UNIDADE => 'Toda a unidade',
            self::SERIES => 'Séries específicas',
            self::TURMAS => 'Turmas específicas',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $item): array => [$item->value => $item->label()])
            ->all();
    }
}
