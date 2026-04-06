<?php

namespace Tests\Unit\Relatorios;

use App\Relatorios\Relatorios;
use App\Services\Relatorios\RelatorioPdfRenderer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class RelatorioPdfRendererTest extends TestCase
{
    public function test_generic_relatorios_entrypoint_streams_existing_pdf(): void
    {
        $response = Relatorios::gerarRelatorio(
            'relatorios.BulkList.alunos-bulklist',
            ['alunos' => []],
            Relatorios::TIPO_BULK_LIST
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_renderer_throws_controlled_exception_for_missing_view(): void
    {
        $this->expectException(NotFoundHttpException::class);

        app(RelatorioPdfRenderer::class)->stream('relatorios.inexistente', [], 'inexistente.pdf');
    }
}
