<?php

namespace App\Models\Enums;

enum BalancoEstoqueStatus: string
{
    case Agendado = 'agendado';
    case EmAndamento = 'em_andamento';
    case Concluido = 'concluido';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Agendado => 'Agendado',
            self::EmAndamento => 'Em Andamento',
            self::Concluido => 'Concluído',
            self::Cancelado => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Agendado => 'info',
            self::EmAndamento => 'warning',
            self::Concluido => 'success',
            self::Cancelado => 'danger',
        };
    }
}
