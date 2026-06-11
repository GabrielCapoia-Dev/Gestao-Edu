<?php

namespace App\Models\Enums;

enum SetorAccessCapability: string
{
    case LISTAR = 'pode_listar';
    case EDITAR = 'pode_editar';
    case CANCELAR = 'pode_cancelar';
    case ENCAMINHAR = 'pode_encaminhar';

    public function permiteProprioSetor(): bool
    {
        return $this !== self::ENCAMINHAR;
    }
}
