<?php

namespace App\Models\Enums;

enum EventoCalendarioStatus: string
{
    case AGENDADO = 'agendado';
    case EM_ANDAMENTO = 'em_andamento';
    case CONCLUIDO = 'concluido';
    case CANCELADO = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::AGENDADO => 'Agendado',
            self::EM_ANDAMENTO => 'Em andamento',
            self::CONCLUIDO => 'Concluído',
            self::CANCELADO => 'Cancelado',
        };
    }
}
