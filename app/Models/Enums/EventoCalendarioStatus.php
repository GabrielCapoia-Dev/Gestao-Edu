<?php

namespace App\Models\Enums;

enum EventoCalendarioStatus: string
{
    case PENDENTE_APROVACAO = 'pendente_aprovacao';
    case PUBLICADO = 'publicado';
    case INATIVO = 'inativo';
    case REJEITADO = 'rejeitado';

    public function label(): string
    {
        return match ($this) {
            self::PENDENTE_APROVACAO => 'Pendente de aprovação',
            self::PUBLICADO => 'Publicado',
            self::INATIVO => 'Inativo',
            self::REJEITADO => 'Rejeitado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDENTE_APROVACAO => 'warning',
            self::PUBLICADO => 'success',
            self::INATIVO => 'gray',
            self::REJEITADO => 'danger',
        };
    }
}
