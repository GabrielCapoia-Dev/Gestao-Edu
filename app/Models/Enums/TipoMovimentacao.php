<?php

namespace App\Models\Enums;

enum TipoMovimentacao: string
{
    case Entrada = 'entrada';
    case Saida   = 'saida';
    case Transferencia   = 'transferencia';
}