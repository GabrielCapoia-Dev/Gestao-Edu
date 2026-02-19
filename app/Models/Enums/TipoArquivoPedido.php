<?php

namespace App\Models\Enums;

enum TipoArquivoPedido: string
{
    case FOTOS_PROBLEMA = 'fotos_problema';
    case LAUDO = 'laudo';
    case ORCAMENTO = 'orcamento';
    case FOTOS_CONCLUIDO = 'fotos_concluido';

    public function label(): string
    {
        return match ($this) {
            self::FOTOS_PROBLEMA => 'Fotos do Problema',
            self::LAUDO => 'Laudo',
            self::ORCAMENTO => 'Orçamento',
            self::FOTOS_CONCLUIDO => 'Fotos Concluído',
        };
    }
}