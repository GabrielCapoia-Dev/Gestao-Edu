<?php

namespace App\Models\Enums;

enum StatusPedidoMerenda: string
{
    case Aguardando = 'aguardando';
    case Entregue   = 'entregue';

    public function label(): string
    {
        return match ($this) {
            self::Aguardando => 'Aguardando',
            self::Entregue   => 'Entregue',
        };
    }
}