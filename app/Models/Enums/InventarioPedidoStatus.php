<?php

namespace App\Models\Enums;

enum InventarioPedidoStatus: string
{
    case Pendente = 'pendente';
    case Aprovado = 'aprovado';
    case EmAndamento = 'em_andamento';
    case Entregue = 'entregue';
    case Recusado = 'recusado';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Aprovado => 'Aprovado',
            self::EmAndamento => 'Em Andamento',
            self::Entregue => 'Entregue',
            self::Recusado => 'Recusado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendente => 'warning',
            self::Aprovado => 'info',
            self::EmAndamento => 'primary',
            self::Entregue => 'success',
            self::Recusado, self::Cancelado => 'danger',
        };
    }
}
