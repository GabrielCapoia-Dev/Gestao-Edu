<?php

namespace App\Models\Enums;

enum DashboardPrioridade: string
{
    case Baixa = 'baixa';
    case Normal = 'normal';
    case Alta = 'alta';
    case Urgente = 'urgente';

    public function label(): string
    {
        return match ($this) {
            self::Baixa => 'Baixa',
            self::Normal => 'Normal',
            self::Alta => 'Alta',
            self::Urgente => 'Urgente',
        };
    }

    public function peso(): int
    {
        return match ($this) {
            self::Baixa => 10,
            self::Normal => 20,
            self::Alta => 30,
            self::Urgente => 40,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $prioridade): array => [$prioridade->value => $prioridade->label()])
            ->all();
    }
}
