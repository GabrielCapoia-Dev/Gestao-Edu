<?php

namespace App\Services\Relatorios;

use App\Models\Estoque;
use App\Models\User;
use App\Services\Estoque\GestaoEstoqueDataService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class EstoqueRelatorioService
{
    public function __construct(
        protected RelatorioPdfRenderer $renderer,
        protected GestaoEstoqueDataService $dataService,
    ) {}

    public function gerarPdfGeral(array $params, ?User $usuario): Response
    {
        $filtros = $this->dataService->normalizarFiltros($params);
        $itens = $this->dataService->itens($filtros);
        $movimentacoes = $this->dataService->movimentacoesDosFiltros($filtros);
        $baixas = $this->dataService->baixasDosFiltros($filtros);

        return $this->renderer->download('relatorios.Estoque.gestao-estoque', [
            'metricas' => $this->dataService->metricasGerais($itens, $movimentacoes, $baixas),
            'porCategoria' => $this->dataService->porCategoria($itens),
            'itens' => $itens,
            'movimentacoes' => $movimentacoes,
            'baixas' => $baixas,
            'reportTitle' => 'Relatório Geral de Estoque',
            'reportSubtitle' => 'Visão consolidada dos itens e do histórico de movimentações',
            'reportFilters' => $this->dataService->formatarFiltros($filtros),
            'usuarioExportacao' => $usuario,
            'dataExportacao' => now(),
            'orientation' => 'landscape',
        ], 'relatório-geral-estoque-' . now()->format('Y-m-d_H-i') . '.pdf');
    }

    public function gerarXlsxGeral(array $params, ?User $usuario): Response
    {
        $filtros = $this->dataService->normalizarFiltros($params);
        $itens = $this->dataService->itens($filtros);
        $movimentacoes = $this->dataService->movimentacoesDosFiltros($filtros);
        $baixas = $this->dataService->baixasDosFiltros($filtros);
        $metricas = $this->dataService->metricasGerais($itens, $movimentacoes, $baixas);
        $porCategoria = $this->dataService->porCategoria($itens);

        $spreadsheet = new Spreadsheet();

        $resumo = $spreadsheet->getActiveSheet();
        $resumo->setTitle('Resumo');
        $this->preencherResumoGeralSheet(
            $resumo,
            $metricas,
            $porCategoria,
            $this->dataService->formatarFiltros($filtros),
            $usuario
        );

        $itensSheet = $spreadsheet->createSheet();
        $itensSheet->setTitle('Estoque');
        $this->preencherItensSheet($itensSheet, $itens, $usuario);

        $movimentacoesSheet = $spreadsheet->createSheet();
        $movimentacoesSheet->setTitle('Movimentações');
        $this->preencherMovimentacoesSheet($movimentacoesSheet, $movimentacoes, $usuario);

        $baixasSheet = $spreadsheet->createSheet();
        $baixasSheet->setTitle('Baixas');
        $this->preencherBaixasSheet($baixasSheet, $baixas, $usuario);

        $spreadsheet->setActiveSheetIndex(0);

        return $this->downloadSpreadsheet(
            $spreadsheet,
            'relatório-geral-estoque-' . now()->format('Y-m-d_H-i') . '.xlsx'
        );
    }

    public function gerarPdfItem(Estoque $estoque, ?User $usuario): Response
    {
        $estoque->loadMissing('item');
        $movimentacoes = $this->dataService->movimentacoesPorEstoque($estoque);
        $baixas = $this->dataService->baixasPorEstoque($estoque);

        return $this->renderer->download('relatorios.Estoque.item-estoque', [
            'estoque' => $estoque,
            'resumo' => $this->dataService->resumoItem($estoque),
            'movimentacoes' => $movimentacoes,
            'baixas' => $baixas,
            'reportTitle' => 'Relatório Individual de Estoque',
            'reportSubtitle' => 'Histórico completo de movimentações do item',
            'reportFilters' => [
                'item' => $estoque->item?->nome ?? 'N/A',
                'categoria' => $estoque->item?->tipo_item?->label() ?? 'N/A',
                'unidade' => strtoupper($estoque->item?->unidade_medida?->value ?? 'N/A'),
            ],
            'usuarioExportacao' => $usuario,
            'dataExportacao' => now(),
            'orientation' => 'landscape',
        ], 'relatório-item-estoque-' . $this->slugItem($estoque) . '.pdf');
    }

    public function gerarXlsxItem(Estoque $estoque, ?User $usuario): Response
    {
        $estoque->loadMissing('item');
        $movimentacoes = $this->dataService->movimentacoesPorEstoque($estoque);
        $baixas = $this->dataService->baixasPorEstoque($estoque);
        $resumo = $this->dataService->resumoItem($estoque);

        $spreadsheet = new Spreadsheet();

        $resumoSheet = $spreadsheet->getActiveSheet();
        $resumoSheet->setTitle('Resumo Item');
        $this->preencherResumoItemSheet($resumoSheet, $estoque, $resumo, $usuario);

        $movimentacoesSheet = $spreadsheet->createSheet();
        $movimentacoesSheet->setTitle('Movimentações');
        $this->preencherMovimentacoesSheet($movimentacoesSheet, $movimentacoes, $usuario);

        $baixasSheet = $spreadsheet->createSheet();
        $baixasSheet->setTitle('Baixas');
        $this->preencherBaixasSheet($baixasSheet, $baixas, $usuario);

        $spreadsheet->setActiveSheetIndex(0);

        return $this->downloadSpreadsheet(
            $spreadsheet,
            'relatório-item-estoque-' . $this->slugItem($estoque) . '.xlsx'
        );
    }

    protected function preencherResumoGeralSheet(
        Worksheet $sheet,
        object $metricas,
        Collection $porCategoria,
        array $filtros,
        ?User $usuario
    ): void {
        $linha = $this->preencherCabecalhoSheet(
            $sheet,
            'Relatório Geral de Estoque',
            $filtros,
            $usuario
        );

        $sheet->setCellValue("A{$linha}", 'Indicador');
        $sheet->setCellValue("B{$linha}", 'Valor');
        $this->estilizarHeaderLinha($sheet, "A{$linha}:B{$linha}");
        $linha++;

        $indicadores = [
            'Itens filtrados' => $metricas->total_itens,
            'Quantidade total em estoque' => $metricas->quantidade_total,
            'Itens em estoque baixo' => $metricas->itens_criticos,
            'Itens zerados' => $metricas->itens_zerados,
            'Movimentações' => $metricas->total_movimentacoes,
            'Entradas acumuladas' => $metricas->total_entradas,
            'Saidas acumuladas' => $metricas->total_saidas,
            'Saldo movimentado' => $metricas->saldo_movimentado,
            'Baixas registradas' => $metricas->total_baixas,
            'Quantidade baixada' => $metricas->quantidade_baixada,
        ];

        foreach ($indicadores as $label => $valor) {
            $sheet->setCellValue("A{$linha}", $label);
            $sheet->setCellValue("B{$linha}", $valor);
            $linha++;
        }

        $this->estilizarCorpoTabela($sheet, "A" . ($linha - count($indicadores)) . ":B" . ($linha - 1));
        $linha += 2;

        $sheet->setCellValue("A{$linha}", 'Categoria');
        $sheet->setCellValue("B{$linha}", 'Itens');
        $sheet->setCellValue("C{$linha}", 'Quantidade Total');
        $this->estilizarHeaderLinha($sheet, "A{$linha}:C{$linha}");
        $linha++;

        foreach ($porCategoria as $categoria) {
            $sheet->setCellValue("A{$linha}", $categoria['label']);
            $sheet->setCellValue("B{$linha}", $categoria['total_itens']);
            $sheet->setCellValue("C{$linha}", $categoria['quantidade_total']);
            $linha++;
        }

        if ($porCategoria->isNotEmpty()) {
            $this->estilizarCorpoTabela($sheet, "A" . ($linha - $porCategoria->count()) . ":C" . ($linha - 1));
        }

        $this->autoSizeColumns($sheet, 3);
    }

    protected function preencherResumoItemSheet(Worksheet $sheet, Estoque $estoque, object $resumo, ?User $usuario): void
    {
        $linha = $this->preencherCabecalhoSheet($sheet, 'Relatório Individual de Estoque', [
            'item' => $estoque->item?->nome ?? 'N/A',
            'categoria' => $estoque->item?->tipo_item?->label() ?? 'N/A',
            'unidade' => strtoupper($estoque->item?->unidade_medida?->value ?? 'N/A'),
        ], $usuario);

        $sheet->setCellValue("A{$linha}", 'Indicador');
        $sheet->setCellValue("B{$linha}", 'Valor');
        $this->estilizarHeaderLinha($sheet, "A{$linha}:B{$linha}");
        $linha++;

        $indicadores = [
            'Saldo atual' => $resumo->saldo_atual,
            'Movimentações' => $resumo->total_movimentacoes,
            'Entradas' => $resumo->total_entradas,
            'Saidas' => $resumo->total_saidas,
            'Baixas' => $resumo->total_baixas,
            'Quantidade baixada' => $resumo->quantidade_baixada,
            'Ultima movimentacao' => $resumo->ultima_movimentacao,
            'Primeira movimentacao' => $resumo->primeira_movimentacao,
        ];

        foreach ($indicadores as $label => $valor) {
            $sheet->setCellValue("A{$linha}", $label);
            $sheet->setCellValue("B{$linha}", $valor);
            $linha++;
        }

        $this->estilizarCorpoTabela($sheet, "A" . ($linha - count($indicadores)) . ":B" . ($linha - 1));
        $this->autoSizeColumns($sheet, 2);
    }

    protected function preencherItensSheet(Worksheet $sheet, Collection $itens, ?User $usuario): void
    {
        $linha = $this->preencherCabecalhoSheet($sheet, 'Itens Filtrados do Estoque', [], $usuario);

        $headers = ['Item', 'Descrição', 'Categoria', 'Unidade', 'Quantidade', 'Status', 'Atualizado em'];
        $this->preencherTabelaSimples($sheet, $linha, $headers, $itens->map(fn (array $item) => [
            $item['nome'],
            $item['descricao'],
            $item['tipo_label'],
            $item['unidade'],
            $item['quantidade'],
            ucfirst($item['status']),
            $item['atualizado'],
        ]));
    }

    protected function preencherMovimentacoesSheet(Worksheet $sheet, Collection $movimentacoes, ?User $usuario): void
    {
        $linha = $this->preencherCabecalhoSheet($sheet, 'Histórico de Movimentações', [], $usuario);

        $headers = ['Data', 'Item', 'Categoria', 'Tipo', 'Quantidade', 'Pedido', 'Registrado por', 'Observação'];
        $this->preencherTabelaSimples($sheet, $linha, $headers, $movimentacoes->map(fn (array $mov) => [
            $mov['data'],
            $mov['item_nome'],
            $mov['categoria'],
            $mov['tipo_label'],
            $mov['quantidade'],
            $mov['pedido_id'] ?: '-',
            $mov['registrado_por'],
            $mov['observacao'],
        ]));
    }

    protected function preencherBaixasSheet(Worksheet $sheet, Collection $baixas, ?User $usuario): void
    {
        $linha = $this->preencherCabecalhoSheet($sheet, 'Histórico de Baixas', [], $usuario);

        $headers = ['Data', 'Item', 'Categoria', 'Motivo', 'Descrição', 'Quantidade', 'Saldo Antes', 'Saldo Depois', 'Registrado por'];
        $this->preencherTabelaSimples($sheet, $linha, $headers, $baixas->map(fn (array $baixa) => [
            $baixa['data'],
            $baixa['item_nome'],
            $baixa['categoria'],
            $baixa['motivo'],
            $baixa['descricao'],
            $baixa['quantidade'],
            $baixa['saldo_anterior'],
            $baixa['saldo_posterior'],
            $baixa['registrado_por'],
        ]));
    }

    protected function preencherTabelaSimples(Worksheet $sheet, int $linhaInicial, array $headers, Collection $rows): void
    {
        $ultimaColuna = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->fromArray($headers, null, "A{$linhaInicial}");
        $this->estilizarHeaderLinha($sheet, "A{$linhaInicial}:{$ultimaColuna}{$linhaInicial}");

        $linha = $linhaInicial + 1;

        foreach ($rows as $row) {
            $sheet->fromArray($row, null, "A{$linha}");
            $linha++;
        }

        if ($rows->isNotEmpty()) {
            $this->estilizarCorpoTabela($sheet, "A" . ($linhaInicial + 1) . ":{$ultimaColuna}" . ($linha - 1));
        }

        $sheet->freezePane('A' . ($linhaInicial + 1));
        $this->autoSizeColumns($sheet, count($headers));
    }

    protected function preencherCabecalhoSheet(Worksheet $sheet, string $titulo, array $filtros, ?User $usuario): int
    {
        $sheet->setCellValue('A1', $titulo);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', 'Gerado em ' . now()->format('d/m/Y H:i') . ' por ' . ($usuario?->name ?? 'Sistema'));
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        $linha = 4;

        if ($filtros !== []) {
            $sheet->setCellValue("A{$linha}", 'Filtros aplicados');
            $sheet->getStyle("A{$linha}")->getFont()->setBold(true);
            $linha++;

            foreach ($filtros as $label => $valor) {
                $sheet->setCellValue("A{$linha}", Str::headline((string) $label));
                $sheet->setCellValue("B{$linha}", $valor);
                $sheet->getStyle("A{$linha}")->getFont()->setBold(true);
                $linha++;
            }

            $linha++;
        }

        return $linha;
    }

    protected function estilizarHeaderLinha(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '074F9B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
    }

    protected function estilizarCorpoTabela(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    protected function autoSizeColumns(Worksheet $sheet, int $count): void
    {
        foreach (range(1, $count) as $indice) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($indice))->setAutoSize(true);
        }
    }

    protected function downloadSpreadsheet(Spreadsheet $spreadsheet, string $fileName): Response
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'estoque_relatorio_');

        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    protected function slugItem(Estoque $estoque): string
    {
        return Str::slug($estoque->item?->nome ?? 'item') . '-' . $estoque->getKey();
    }
}
