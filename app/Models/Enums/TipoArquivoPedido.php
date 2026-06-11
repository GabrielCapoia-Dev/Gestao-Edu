<?php

namespace App\Models\Enums;

enum TipoArquivoPedido: string
{
    case FOTOS_PROBLEMA = 'fotos_problema';
    case LAUDO = 'laudo';
    case ORCAMENTO = 'orcamento';
    case FOTOS_CONCLUSAO = 'fotos_conclusao';
    case PRINTS = 'prints';
    case OUTROS = 'outros';

    public function label(): string
    {
        return match ($this) {
            self::FOTOS_PROBLEMA => 'Fotos do Problema',
            self::LAUDO => 'Laudo',
            self::ORCAMENTO => 'Orçamento',
            self::FOTOS_CONCLUSAO => 'Fotos Conclusão',
            self::PRINTS => 'Prints',
            self::OUTROS => 'Outros',
        };
    }

    public function exigeImagem(): bool
    {
        return in_array($this, [
            self::FOTOS_PROBLEMA,
            self::FOTOS_CONCLUSAO,
            self::PRINTS,
        ], true);
    }
}
