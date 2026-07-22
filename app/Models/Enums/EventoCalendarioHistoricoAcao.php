<?php

namespace App\Models\Enums;

enum EventoCalendarioHistoricoAcao: string
{
    case CRIADO = 'criado';
    case ATUALIZADO = 'atualizado';
    case APROVADO = 'aprovado';
    case PUBLICADO = 'publicado';
    case REPUBLICADO = 'republicado';
    case DESATIVADO = 'desativado';
    case REJEITADO = 'rejeitado';
    case REENVIADO_ANALISE = 'reenviado_analise';

    public function label(): string
    {
        return match ($this) {
            self::CRIADO => 'Criado',
            self::ATUALIZADO => 'Atualizado',
            self::APROVADO => 'Aprovado',
            self::PUBLICADO => 'Publicado',
            self::REPUBLICADO => 'Republicado',
            self::DESATIVADO => 'Desativado',
            self::REJEITADO => 'Rejeitado',
            self::REENVIADO_ANALISE => 'Reenviado para análise',
        };
    }
}
