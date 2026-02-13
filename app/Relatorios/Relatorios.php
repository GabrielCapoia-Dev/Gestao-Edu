<?php

namespace App\Relatorios;

use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class Relatorios
{
    public const TIPO_FICHA      = 'ficha';
    public const TIPO_BULK_LIST  = 'bulk_list';
    public const TIPO_BULK_FICHA = 'bulk_ficha';

    /**
     * Gera o PDF e retorna uma Response (stream no navegador).
     */
    public static function pdf(string $view, array $data = [], ?string $tipo = null): Response
    {
        $tipo = $tipo ?? self::TIPO_FICHA;

        $fileName = match ($tipo) {
            self::TIPO_BULK_LIST  => 'relatorio-lista-alunos.pdf',
            self::TIPO_BULK_FICHA => 'relatorio-fichas-alunos.pdf',
            default               => 'relatorio.pdf',
        };

        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4', 'portrait'); // ajuste se precisar landscape

        // IMPORTANTE: devolver uma Response, não o objeto PDF
        return $pdf->stream($fileName);

        // Se preferir download automático:
        // return $pdf->download($fileName);
    }

    /**
     * Nome padrão de arquivo baseado no tipo de relatório.
     */
    protected static function defaultFileName(string $tipo): string
    {
        $prefix = match ($tipo) {
            self::TIPO_BULK_LIST  => 'relatorio-lista',
            self::TIPO_BULK_FICHA => 'relatorio-fichas',
            default               => 'relatorio-ficha',
        };

        return sprintf('%s-%s.pdf', $prefix, now()->format('Ymd_His'));
    }

    /**
     * Alias mais semântico pra quem preferir chamar assim.
     */
    public static function gerarRelatorio(string $view, array $data = [], ?string $tipo = null): Response
    {
        return self::pdf($view, $data, $tipo);
    }

    /**
     * Alias semântico de gerarRelatorio().
     */
    public static function download(
        string $view,
        array $data,
        string $tipo = self::TIPO_FICHA,
    ) {
        return static::gerarRelatorio($view, $data, $tipo);
    }

    /**
     * Stream em vez de download.
     */
    public static function stream(
        string $view,
        array $data,
        string $tipo = self::TIPO_FICHA,
        ?string $fileName = null,
    ) {
        $fileName ??= static::defaultFileName($tipo);

        return static::pdf($view, $data)
            ->stream($fileName);
    }
}
