<?php

namespace App\Models\Enums;

enum EventoCalendarioCategoria: string
{
    case ADMINISTRATIVO = 'administrativo';
    case AVALIACAO = 'avaliacao';
    case MANUTENCAO = 'manutencao';
    case PEDAGOGICO = 'pedagogico';
    case INVENTARIO = 'inventario';
    case CONTRATO = 'contrato';
    case PRAZO = 'prazo';
    case OUTRO = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRATIVO => 'Administrativo',
            self::AVALIACAO => 'Avaliação',
            self::MANUTENCAO => 'Manutenção',
            self::PEDAGOGICO => 'Pedagógico',
            self::INVENTARIO => 'Inventário',
            self::CONTRATO => 'Contrato',
            self::PRAZO => 'Prazo',
            self::OUTRO => 'Outro',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ADMINISTRATIVO => 'azul',
            self::AVALIACAO => 'verde',
            self::MANUTENCAO => 'ambar',
            self::PEDAGOGICO => 'violeta',
            self::INVENTARIO => 'ciano',
            self::CONTRATO => 'cinza',
            self::PRAZO => 'vermelho',
            self::OUTRO => 'cinza',
        };
    }
}
