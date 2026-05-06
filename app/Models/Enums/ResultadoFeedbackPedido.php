<?php

namespace App\Models\Enums;

enum ResultadoFeedbackPedido: string
{
    case Atendido = 'atendido';
    case ParcialmenteAtendido = 'parcialmente_atendido';
    case NaoAtendido = 'nao_atendido';

    public function label(): string
    {
        return match ($this) {
            self::Atendido => 'Atendido',
            self::ParcialmenteAtendido => 'Parcialmente atendido',
            self::NaoAtendido => 'Nao atendido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Atendido => 'success',
            self::ParcialmenteAtendido => 'warning',
            self::NaoAtendido => 'danger',
        };
    }
}
