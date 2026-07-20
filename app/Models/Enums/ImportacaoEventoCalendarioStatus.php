<?php

namespace App\Models\Enums;

enum ImportacaoEventoCalendarioStatus: string
{
    case EM_PRE_VISUALIZACAO = 'em_pre_visualizacao';
    case PRONTA = 'pronta';
    case PROCESSANDO = 'processando';
    case CONCLUIDA = 'concluida';
    case CANCELADA = 'cancelada';
    case FALHOU = 'falhou';
}
