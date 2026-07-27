<?php

namespace Tests\Feature\Dashboard;

use App\Models\Enums\DashboardPrioridade;
use App\Models\User;
use App\Services\Dashboard\Calendar\CalendarExportService;
use App\Services\Relatorios\RelatorioPdfRenderer;
use App\Support\Dashboard\Calendar\CalendarEventData;
use Carbon\CarbonImmutable;
use Mockery;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class CalendarExportServiceTest extends TestCase
{
    public function test_planilha_exporta_eventos_detalhados_e_resumo_mensal(): void
    {
        $service = new CalendarExportService(Mockery::mock(RelatorioPdfRenderer::class));
        $user = (new User)->forceFill(['name' => 'Gestor Teste']);
        $inicio = CarbonImmutable::parse('2026-07-01')->startOfDay();
        $fim = CarbonImmutable::parse('2026-07-31')->endOfDay();
        $response = $service->exportarXlsx(
            $this->eventosDeTodasAsCategorias(),
            $inicio,
            $fim,
            'mes',
            $user,
        );
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $path = $response->getFile()->getPathname();

        try {
            $spreadsheet = IOFactory::load($path);

            $this->assertSame(['Eventos', 'Resumo mensal'], $spreadsheet->getSheetNames());
            $this->assertSame('Calendário de eventos da rede', $spreadsheet->getSheetByName('Eventos')?->getCell('A1')->getValue());
            $this->assertSame('Pedido manutenção', $spreadsheet->getSheetByName('Eventos')?->getCell('G5')->getValue());
            $this->assertSame('Julho de 2026', $spreadsheet->getSheetByName('Resumo mensal')?->getCell('A2')->getValue());
            $this->assertSame(1, $spreadsheet->getSheetByName('Resumo mensal')?->getCell('B2')->getValue());
            $this->assertSame(1, $spreadsheet->getSheetByName('Resumo mensal')?->getCell('C2')->getValue());
            $this->assertSame(1, $spreadsheet->getSheetByName('Resumo mensal')?->getCell('D2')->getValue());
            $this->assertSame(1, $spreadsheet->getSheetByName('Resumo mensal')?->getCell('E2')->getValue());
            $this->assertSame(1, $spreadsheet->getSheetByName('Resumo mensal')?->getCell('F2')->getValue());
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_pdf_usa_layout_padrao_e_recebe_os_cinco_resumos_por_mes(): void
    {
        $user = (new User)->forceFill(['name' => 'Gestor Teste']);
        $renderer = Mockery::mock(RelatorioPdfRenderer::class);
        $renderer
            ->shouldReceive('download')
            ->once()
            ->with(
                'relatorios.Calendario.eventos',
                Mockery::on(function (array $data) use ($user): bool {
                    $resumo = $data['calendarios'][6]['resumo'] ?? [];

                    return $data['usuarioExportacao'] === $user
                        && $data['orientation'] === 'portrait'
                        && $data['showPagination'] === true
                        && ($resumo['manutencao']['count'] ?? 0) === 1
                        && ($resumo['transporte']['count'] ?? 0) === 1
                        && ($resumo['veiculo_escola']['count'] ?? 0) === 1
                        && ($resumo['veiculo_outros']['count'] ?? 0) === 1
                        && ($resumo['pedagogico']['count'] ?? 0) === 1;
                }),
                'calendario-eventos-ano-2026.pdf',
            )
            ->andReturn(response('pdf'));
        $service = new CalendarExportService($renderer);

        $response = $service->exportarPdf(
            $this->eventosDeTodasAsCategorias(),
            CarbonImmutable::parse('2026-01-01')->startOfDay(),
            CarbonImmutable::parse('2026-12-31')->endOfDay(),
            'ano',
            $user,
        );

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $path = $response->getFile()->getPathname();

        try {
            $this->assertSame('pdf', file_get_contents($path));
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /** @return list<CalendarEventData> */
    private function eventosDeTodasAsCategorias(): array
    {
        return [
            $this->evento('manutencao', 'Pedido manutenção', 'pedidos_manutencao', 'manutencao'),
            $this->evento('transporte', 'Evento transporte', 'manual', 'administrativo', transporteEstimado: 20),
            $this->evento('veiculo-escola', 'Reserva escola', 'reservas_veiculos', 'veiculo', escolaId: 10, escola: 'Escola A'),
            $this->evento('veiculo-outros', 'Reserva outro local', 'reservas_veiculos', 'veiculo'),
            $this->evento('pedagogico', 'Avaliação', 'avaliacoes', 'avaliacao'),
        ];
    }

    private function evento(
        string $id,
        string $titulo,
        string $source,
        string $categoria,
        ?int $escolaId = null,
        ?string $escola = null,
        ?int $transporteEstimado = null,
    ): CalendarEventData {
        $inicio = CarbonImmutable::parse('2026-07-15 08:00:00');

        return new CalendarEventData(
            id: $id,
            source: $source,
            reference: $id,
            titulo: $titulo,
            resumo: 'Resumo do evento',
            inicio: $inicio,
            fim: $inicio->addHour(),
            diaInteiro: false,
            categoria: $categoria,
            categoriaLabel: ucfirst($categoria),
            assunto: null,
            status: 'ativo',
            statusLabel: 'Ativo',
            prioridade: DashboardPrioridade::Normal,
            progresso: null,
            cor: 'azul',
            escolaId: $escolaId,
            escola: $escola,
            setorId: null,
            setor: null,
            origem: 'Teste',
            transporteEstimado: $transporteEstimado,
        );
    }
}
