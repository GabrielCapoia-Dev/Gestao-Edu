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
    case ALOCACAO_TRANSPORTE_ADICIONADA = 'alocacao_transporte_adicionada';
    case ALOCACAO_TRANSPORTE_REMOVIDA = 'alocacao_transporte_removida';
    case ALOCACOES_TRANSPORTE_REMOVIDAS = 'alocacoes_transporte_removidas';

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
            self::ALOCACAO_TRANSPORTE_ADICIONADA => 'Alocação de transporte adicionada',
            self::ALOCACAO_TRANSPORTE_REMOVIDA => 'Alocação de transporte removida',
            self::ALOCACOES_TRANSPORTE_REMOVIDAS => 'Alocações de transporte removidas',
        };
    }
}
