<?php

namespace App\Models\Enums;

enum PublicoAlvoModoCorrespondencia: string
{
    case Qualquer = 'qualquer';
    case Todos = 'todos';

    public function label(): string
    {
        return match ($this) {
            self::Qualquer => 'Corresponder a qualquer critério',
            self::Todos => 'Corresponder a todos os critérios',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $modo): array => [$modo->value => $modo->label()])
            ->all();
    }
}
