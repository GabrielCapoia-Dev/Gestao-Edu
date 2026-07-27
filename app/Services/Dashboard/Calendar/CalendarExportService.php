<?php

namespace App\Services\Dashboard\Calendar;

use App\Models\User;
use App\Services\Relatorios\RelatorioPdfRenderer;
use App\Support\Dashboard\Calendar\CalendarEventData;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class CalendarExportService
{
    private const RESUMOS = [
        'manutencao' => [
            'singular' => 'pedido de manutenção',
            'plural' => 'pedidos de manutenção',
            'color' => '#f59e0b',
        ],
        'transporte' => [
            'singular' => 'evento de transporte',
            'plural' => 'eventos de transporte',
            'color' => '#2563eb',
        ],
        'veiculo_escola' => [
            'singular' => 'reserva de veículo para atendimento às escolas',
            'plural' => 'reservas de veículos para atendimento às escolas',
            'color' => '#16a34a',
        ],
        'veiculo_outros' => [
            'singular' => 'reserva de veículo para atendimento em outros locais',
            'plural' => 'reservas de veículos para atendimento em outros locais',
            'color' => '#7c3aed',
        ],
        'pedagogico' => [
            'singular' => 'ação do Pedagógico realizada',
            'plural' => 'ações do Pedagógico realizadas',
            'color' => '#0891b2',
        ],
    ];

    public function __construct(private readonly RelatorioPdfRenderer $pdfRenderer) {}

    /**
     * @param  list<CalendarEventData>  $events
     */
    public function exportarXlsx(
        array $events,
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
        string $visualizacao,
        User $user,
    ): Response {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator($user->name)
            ->setTitle('Calendário de eventos da rede')
            ->setSubject($this->periodoLabel($inicio, $fim, $visualizacao));

        $this->preencherEventos($spreadsheet->getActiveSheet(), $events, $inicio, $fim, $visualizacao, $user);
        $this->preencherResumo(
            $spreadsheet->createSheet(),
            $this->montarCalendarios($events, $inicio, $fim, $visualizacao),
        );
        $spreadsheet->setActiveSheetIndex(0);

        $tempPath = tempnam(sys_get_temp_dir(), 'calendario_eventos_');

        if ($tempPath === false) {
            throw new RuntimeException('Não foi possível criar o arquivo temporário da planilha.');
        }

        (new Xlsx($spreadsheet))->save($tempPath);
        $spreadsheet->disconnectWorksheets();

        return response()
            ->download($tempPath, $this->nomeArquivo($inicio, $fim, $visualizacao, 'xlsx'))
            ->deleteFileAfterSend(true);
    }

    /**
     * @param  list<CalendarEventData>  $events
     */
    public function exportarPdf(
        array $events,
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
        string $visualizacao,
        User $user,
    ): Response {
        $periodo = $this->periodoLabel($inicio, $fim, $visualizacao);

        return $this->pdfRenderer->download(
            'relatorios.Calendario.eventos',
            [
                'reportTitle' => 'Calendário de eventos da rede',
                'reportSubtitle' => $periodo,
                'reportFilters' => [
                    'Visualização' => ucfirst($visualizacao),
                    'Período' => $periodo,
                ],
                'usuarioExportacao' => $user,
                'dataExportacao' => now(),
                'orientation' => $visualizacao === 'ano' ? 'portrait' : 'landscape',
                'paperSize' => 'a4',
                'showPagination' => true,
                'visualizacao' => $visualizacao,
                'calendarios' => $this->montarCalendarios($events, $inicio, $fim, $visualizacao),
            ],
            $this->nomeArquivo($inicio, $fim, $visualizacao, 'pdf'),
        );
    }

    /**
     * @param  list<CalendarEventData>  $events
     */
    private function preencherEventos(
        Worksheet $sheet,
        array $events,
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
        string $visualizacao,
        User $user,
    ): void {
        $headers = [
            'Data inicial',
            'Hora inicial',
            'Data final',
            'Hora final',
            'Dia inteiro',
            'Categoria',
            'Evento',
            'Resumo',
            'Escola',
            'Local',
            'Setor',
            'Status',
            'Solicitante',
            'Origem',
        ];
        $sheet->setTitle('Eventos');
        $sheet->mergeCells('A1:N1');
        $sheet->setCellValue('A1', 'Calendário de eventos da rede');
        $sheet->mergeCells('A2:N2');
        $sheet->setCellValue(
            'A2',
            $this->periodoLabel($inicio, $fim, $visualizacao)
                .' | Exportado por '.$user->name.' em '.now()->format('d/m/Y H:i'),
        );

        foreach ($headers as $index => $header) {
            $sheet->setCellValueExplicit([$index + 1, 4], $header, DataType::TYPE_STRING);
        }

        foreach ($events as $rowIndex => $event) {
            $values = [
                $event->inicio->format('d/m/Y'),
                $event->diaInteiro ? '' : $event->inicio->format('H:i'),
                $event->fim->format('d/m/Y'),
                $event->diaInteiro ? '' : $event->fim->format('H:i'),
                $event->diaInteiro ? 'Sim' : 'Não',
                $event->categoriaLabel,
                $event->titulo,
                $event->resumo,
                $event->escola,
                $event->local,
                $event->setor,
                $event->statusLabel,
                $event->solicitante,
                $event->origem,
            ];

            foreach ($values as $columnIndex => $value) {
                $sheet->setCellValueExplicit(
                    [$columnIndex + 1, $rowIndex + 5],
                    trim((string) $value),
                    DataType::TYPE_STRING,
                );
            }
        }

        $lastRow = max(4, count($events) + 4);
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '123F7D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A2:N2')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '475569']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $this->estilizarCabecalho($sheet, 'A4:N4');
        $sheet->getStyle("A4:N{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A5:N{$lastRow}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);
        $sheet->freezePane('A5');
        $sheet->setAutoFilter("A4:N{$lastRow}");

        $widths = [13, 11, 13, 11, 12, 20, 32, 42, 28, 24, 22, 18, 24, 24];

        foreach ($widths as $index => $width) {
            $sheet->getColumnDimensionByColumn($index + 1)->setWidth($width);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $calendarios
     */
    private function preencherResumo(Worksheet $sheet, array $calendarios): void
    {
        $sheet->setTitle('Resumo mensal');
        $headers = ['Período', ...array_map(
            static fn (array $item): string => ucfirst($item['plural']),
            self::RESUMOS,
        )];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueExplicit([$index + 1, 1], $header, DataType::TYPE_STRING);
        }

        foreach ($calendarios as $rowIndex => $calendario) {
            $sheet->setCellValueExplicit([1, $rowIndex + 2], $calendario['label'], DataType::TYPE_STRING);

            foreach (array_keys(self::RESUMOS) as $columnIndex => $key) {
                $sheet->setCellValue([$columnIndex + 2, $rowIndex + 2], $calendario['resumo'][$key]['count']);
            }
        }

        $lastRow = max(1, count($calendarios) + 1);
        $lastColumn = count($headers);
        $this->estilizarCabecalho($sheet, "A1:{$this->columnLetter($lastColumn)}1");
        $sheet->getStyle("A1:{$this->columnLetter($lastColumn)}{$lastRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->freezePane('B2');

        foreach (range(1, $lastColumn) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }
    }

    private function estilizarCabecalho(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E5A9B']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
    }

    /**
     * @param  list<CalendarEventData>  $events
     * @return list<array<string, mixed>>
     */
    private function montarCalendarios(
        array $events,
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
        string $visualizacao,
    ): array {
        if ($visualizacao === 'semana') {
            return [$this->montarCalendario(
                $events,
                $inicio,
                $fim,
                'Semana de '.$inicio->format('d/m').' a '.$fim->format('d/m/Y'),
            )];
        }

        $calendarios = [];

        for ($month = $inicio->startOfMonth(); $month->lte($fim); $month = $month->addMonth()) {
            $monthEnd = $month->endOfMonth()->min($fim);
            $monthStart = $month->max($inicio);
            $calendarios[] = $this->montarCalendario(
                $events,
                $monthStart,
                $monthEnd,
                ucfirst($month->locale('pt_BR')->translatedFormat('F \d\e Y')),
            );
        }

        return $calendarios;
    }

    /**
     * @param  list<CalendarEventData>  $events
     * @return array<string, mixed>
     */
    private function montarCalendario(
        array $events,
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
        string $label,
    ): array {
        $days = array_fill(0, $inicio->dayOfWeekIso - 1, null);

        for ($date = $inicio->startOfDay(); $date->lte($fim); $date = $date->addDay()) {
            $dayEvents = array_values(array_filter(
                $events,
                static fn (CalendarEventData $event): bool => $event->inicio->lte($date->endOfDay())
                    && $event->fim->gte($date->startOfDay()),
            ));
            $colors = [];

            foreach ($dayEvents as $event) {
                foreach ($this->classificar($event) as $key) {
                    $colors[$key] = self::RESUMOS[$key]['color'];
                }
            }

            $days[] = [
                'date' => $date,
                'eventsCount' => count($dayEvents),
                'colors' => array_values($colors),
            ];
        }

        while (count($days) % 7 !== 0) {
            $days[] = null;
        }

        return [
            'label' => $label,
            'inicio' => $inicio,
            'fim' => $fim,
            'weeks' => array_chunk($days, 7),
            'resumo' => $this->resumir($events, $inicio, $fim),
        ];
    }

    /**
     * @param  list<CalendarEventData>  $events
     * @return array<string, array{singular: string, plural: string, color: string, count: int}>
     */
    private function resumir(array $events, CarbonImmutable $inicio, CarbonImmutable $fim): array
    {
        $resumo = array_map(
            static fn (array $item): array => [...$item, 'count' => 0],
            self::RESUMOS,
        );

        foreach ($events as $event) {
            if ($event->inicio->gt($fim) || $event->fim->lt($inicio)) {
                continue;
            }

            foreach ($this->classificar($event) as $key) {
                $resumo[$key]['count']++;
            }
        }

        return $resumo;
    }

    /** @return list<string> */
    private function classificar(CalendarEventData $event): array
    {
        $keys = [];

        if ($event->categoria === 'manutencao') {
            $keys[] = 'manutencao';
        }

        if ($event->source === 'reservas_veiculos') {
            $keys[] = $event->escolaId !== null || filled($event->escola)
                ? 'veiculo_escola'
                : 'veiculo_outros';
        }

        if ($event->precisaTransporte()) {
            $keys[] = 'transporte';
        }

        if (in_array($event->categoria, ['avaliacao', 'pedagogico'], true)) {
            $keys[] = 'pedagogico';
        }

        return $keys;
    }

    private function periodoLabel(
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
        string $visualizacao,
    ): string {
        return match ($visualizacao) {
            'ano' => 'Ano de '.$inicio->format('Y'),
            'semana' => $inicio->format('d/m/Y').' a '.$fim->format('d/m/Y'),
            default => ucfirst($inicio->locale('pt_BR')->translatedFormat('F \d\e Y')),
        };
    }

    private function nomeArquivo(
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
        string $visualizacao,
        string $extension,
    ): string {
        $periodo = match ($visualizacao) {
            'ano' => 'ano-'.$inicio->format('Y'),
            'semana' => 'semana-'.$inicio->format('Y-m-d').'-a-'.$fim->format('Y-m-d'),
            default => 'mes-'.$inicio->format('Y-m'),
        };

        return "calendario-eventos-{$periodo}.{$extension}";
    }

    private function columnLetter(int $column): string
    {
        return Coordinate::stringFromColumnIndex($column);
    }
}
