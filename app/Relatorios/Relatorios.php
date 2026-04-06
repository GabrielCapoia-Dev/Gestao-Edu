<?php

namespace App\Relatorios;

use App\Services\Relatorios\RelatorioPdfRenderer;
use Illuminate\Http\Response;

class Relatorios
{
    public const TIPO_FICHA = 'ficha';
    public const TIPO_BULK_LIST = 'bulk_list';
    public const TIPO_BULK_FICHA = 'bulk_ficha';

    /**
     * Gera o PDF e retorna uma Response em stream no navegador.
     */
    public static function pdf(string $view, array $data = [], ?string $tipo = null): Response
    {
        $tipo ??= self::TIPO_FICHA;

        return app(RelatorioPdfRenderer::class)->stream(
            $view,
            self::preparePayload($data, $tipo),
            self::fileNameForTipo($tipo)
        );
    }

    protected static function defaultFileName(string $tipo): string
    {
        $prefix = match ($tipo) {
            self::TIPO_BULK_LIST => 'relatorio-lista',
            self::TIPO_BULK_FICHA => 'relatorio-fichas',
            default => 'relatorio-ficha',
        };

        return sprintf('%s-%s.pdf', $prefix, now()->format('Ymd_His'));
    }

    protected static function fileNameForTipo(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_BULK_LIST => 'relatorio-lista-alunos.pdf',
            self::TIPO_BULK_FICHA => 'relatorio-fichas-alunos.pdf',
            default => 'relatorio.pdf',
        };
    }

    protected static function defaultTitle(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_BULK_LIST => 'Relatorio - Lista de Alunos',
            self::TIPO_BULK_FICHA => 'Relatorio - Fichas de Alunos',
            default => 'Relatorio',
        };
    }

    protected static function preparePayload(array $data, string $tipo): array
    {
        return array_replace([
            'reportTitle' => self::defaultTitle($tipo),
            'showPagination' => true,
        ], $data);
    }

    /**
     * Alias mais semantico pra quem preferir chamar assim.
     */
    public static function gerarRelatorio(string $view, array $data = [], ?string $tipo = null): Response
    {
        return self::pdf($view, $data, $tipo);
    }

    /**
     * Alias semantico de gerarRelatorio().
     */
    public static function download(
        string $view,
        array $data,
        string $tipo = self::TIPO_FICHA,
    ): Response {
        return app(RelatorioPdfRenderer::class)->download(
            $view,
            self::preparePayload($data, $tipo),
            static::defaultFileName($tipo)
        );
    }

    /**
     * Stream com nome customizado quando necessario.
     */
    public static function stream(
        string $view,
        array $data,
        string $tipo = self::TIPO_FICHA,
        ?string $fileName = null,
    ): Response {
        $fileName ??= static::defaultFileName($tipo);

        return app(RelatorioPdfRenderer::class)->stream(
            $view,
            self::preparePayload($data, $tipo),
            $fileName
        );
    }
}
