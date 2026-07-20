<?php

namespace App\Models\Enums;

enum ImportacaoEventoCalendarioAcao: string
{
    case CRIAR = 'criar';
    case ATUALIZAR = 'atualizar';
    case INVALIDA = 'invalida';
    case IGNORAR = 'ignorar';
}
