<?php

// app/Models/Enums/TipoItemContrato.php
namespace App\Models\Enums;

enum TipoItemContrato: string
{
    case Compra      = 'compra';
    case Aditivo     = 'aditivo';
    case Reequilibrio = 'reequilibrio';

    public function label(): string
    {
        return match($this) {
            self::Compra       => 'Compra',
            self::Aditivo      => 'Aditivo',
            self::Reequilibrio => 'Reequilíbrio',
        };
    }
}