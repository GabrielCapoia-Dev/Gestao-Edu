<?php

namespace App\Services\Relatorios;

use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RelatorioPdfRenderer
{
    /**
     * @var array<string, mixed>
     */
    protected array $defaultOptions = [
        'dpi' => 96,
        'defaultFont' => 'DejaVu Sans',
        'isRemoteEnabled' => false,
        'isHtml5ParserEnabled' => true,
        'isFontSubsettingEnabled' => true,
        'isPhpEnabled' => false,
    ];

    public function stream(string $view, array $data = [], string $fileName = 'relatorio.pdf'): Response
    {
        return $this->respond('inline', $view, $data, $fileName);
    }

    public function download(string $view, array $data = [], string $fileName = 'relatorio.pdf'): Response
    {
        return $this->respond('attachment', $view, $data, $fileName);
    }

    protected function respond(string $disposition, string $view, array $data, string $fileName): Response
    {
        $this->assertViewExists($view);

        $payload = $this->normalizePayload($data);

        $pdf = Pdf::loadView($view, $payload)
            ->setPaper($payload['paperSize'], $payload['orientation'])
            ->setOptions(array_replace($this->defaultOptions, $payload['pdfOptions']));

        if (isset($payload['chroot'])) {
            $pdf->setOption('chroot', $payload['chroot']);
        }

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        if ($payload['showPagination']) {
            $this->applyPagination($dompdf);
        }

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, addslashes($fileName)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizePayload(array $data): array
    {
        $payload = array_replace([
            'reportTitle' => 'Relatório',
            'reportSubtitle' => null,
            'reportFilters' => $data['filtros'] ?? [],
            'usuarioExportacao' => Auth::user(),
            'dataExportacao' => now(),
            'orientation' => 'portrait',
            'paperSize' => 'a4',
            'showPagination' => true,
            'pdfOptions' => [],
            'chroot' => public_path(),
        ], $data);

        if (! is_array($payload['reportFilters'])) {
            $payload['reportFilters'] = [];
        }

        $payload['reportFilters'] = array_filter(
            $payload['reportFilters'],
            static fn (mixed $value): bool => ! ($value === null || $value === '' || $value === [])
        );

        return $payload;
    }

    protected function assertViewExists(string $view): void
    {
        if (! view()->exists($view)) {
            throw new NotFoundHttpException("A view de relatório [{$view}] não foi encontrada.");
        }
    }

    protected function applyPagination(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $size = 8;

        $canvas->page_script(static function (
            int $pageNumber,
            int $pageCount,
            $pageCanvas,
            $fontMetrics,
        ) use ($size): void {
            $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
            $text = "Página {$pageNumber} de {$pageCount}";
            $textWidth = $fontMetrics->getTextWidth($text, $font, $size);
            $fontHeight = $fontMetrics->getFontHeight($font, $size);
            $x = ($pageCanvas->get_width() - $textWidth) / 2;
            $y = $pageCanvas->get_height() - 32 - $fontHeight;

            $pageCanvas->text(
                $x,
                $y,
                $text,
                $font,
                $size,
                [0.42, 0.45, 0.50],
            );
        });
    }
}
