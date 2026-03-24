<?php

namespace App\Models\Enums;

enum StatusPedidoMerenda: string
{
    case Aguardando = 'aguardando';
    case Entregue   = 'entregue';
    case Cancelado  = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Aguardando => 'Aguardando',
            self::Entregue   => 'Entregue',
            self::Cancelado  => 'Cancelado',
        };
    }
}