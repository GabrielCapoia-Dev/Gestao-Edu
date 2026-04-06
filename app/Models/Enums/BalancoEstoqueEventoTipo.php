<?php

namespace App\Models\Enums;

enum BalancoEstoqueEventoTipo: string
{
    case Criado = 'criado';
    case Adiado = 'adiado';
    case Iniciado = 'iniciado';
    case Cancelado = 'cancelado';
    case Concluido = 'concluido';

    public function label(): string
    {
        return match ($this) {
            self::Criado => 'Criado',
            self::Adiado => 'Adiado',
            self::Iniciado => 'Iniciado',
            self::Cancelado => 'Cancelado',
            self::Concluido => 'Concluído',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Criado => 'gray',
            self::Adiado => 'info',
            self::Iniciado => 'warning',
            self::Cancelado => 'danger',
            self::Concluido => 'success',
        };
    }
}
