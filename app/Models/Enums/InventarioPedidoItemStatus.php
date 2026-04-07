<?php

namespace App\Models\Enums;

enum InventarioPedidoItemStatus: string
{
    case Pendente = 'pendente';
    case Aprovado = 'aprovado';
    case Recusado = 'recusado';
    case Conferido = 'conferido';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Aprovado => 'Aprovado',
            self::Recusado => 'Recusado',
            self::Conferido => 'Conferido',
        };
    }
}
