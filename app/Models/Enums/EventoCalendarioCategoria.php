<?php

namespace App\Models\Enums;

enum EventoCalendarioCategoria: string
{
    case ADMINISTRATIVO = 'administrativo';
    case PEDAGOGICO = 'pedagogico';
    case RH = 'rh';
    case DOCUMENTACAO_ESCOLAR = 'documentacao_escolar';
    case EDUCACAO_ESPECIAL = 'educacao_especial';
    case EDUCACAO_INFANTIL = 'educacao_infantil';
    case AGE = 'age';
    case PALESTRA = 'palestra';
    case CURSO = 'curso';
    case PREMIACAO = 'premiacao';
    case OUTRO = 'outro';
    case AVALIACAO = 'avaliacao';
    case MANUTENCAO = 'manutencao';
    case INVENTARIO = 'inventario';
    case CONTRATO = 'contrato';
    case PRAZO = 'prazo';

    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRATIVO => 'Administrativo',
            self::PEDAGOGICO => 'Pedagógico',
            self::RH => 'RH',
            self::DOCUMENTACAO_ESCOLAR => 'Documentação Escolar',
            self::EDUCACAO_ESPECIAL => 'Educação Especial',
            self::EDUCACAO_INFANTIL => 'Educação Infantil',
            self::AGE => 'AGE',
            self::PALESTRA => 'Palestra',
            self::CURSO => 'Curso',
            self::PREMIACAO => 'Premiação',
            self::OUTRO => 'Outro',
            self::AVALIACAO => 'Avaliação',
            self::MANUTENCAO => 'Manutenção',
            self::INVENTARIO => 'Inventário',
            self::CONTRATO => 'Contrato',
            self::PRAZO => 'Prazo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ADMINISTRATIVO => 'azul',
            self::PEDAGOGICO => 'violeta',
            self::RH => 'azul',
            self::DOCUMENTACAO_ESCOLAR => 'ciano',
            self::EDUCACAO_ESPECIAL => 'verde',
            self::EDUCACAO_INFANTIL => 'violeta',
            self::AGE => 'cinza',
            self::PALESTRA => 'ambar',
            self::CURSO => 'ciano',
            self::PREMIACAO => 'vermelho',
            self::OUTRO => 'cinza',
            self::AVALIACAO => 'verde',
            self::MANUTENCAO => 'ambar',
            self::INVENTARIO => 'ciano',
            self::CONTRATO => 'cinza',
            self::PRAZO => 'vermelho',
        };
    }
}
