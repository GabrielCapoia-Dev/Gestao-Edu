<?php

namespace App\Models\Enums;

enum EventoCalendarioOrigem: string
{
    case MANUAL = 'manual';
    case PLANILHA = 'planilha';
}
