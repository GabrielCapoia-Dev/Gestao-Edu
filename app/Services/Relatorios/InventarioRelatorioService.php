<?php

namespace App\Services\Relatorios;

use App\Models\Inventario;
use App\Models\InventarioEstoque;
use App\Models\User;
use App\Services\Inventario\InventarioDataService;
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

class InventarioRelatorioService
{
    public function __construct(
        protected RelatorioPdfRenderer $renderer,
        protected InventarioDataService $dataService,
    ) {}

    public function gerarPdfGeral(Inventario $inventario, array $params, ?User $usuario): Response
    {
        $filtros = $this->dataService->normalizarFiltros($params);
        $itens = $this->dataService->itens($inventario, $filtros);
        $movimentacoes = $this->dataService->movimentacoesDoInventario($inventario);
        $baixas = $this->dataService->baixasDoInventario($inventario);

        return $this->renderer->download('relatorios.Inventario.gestao-inventario', [
            'inventario' => $inventario,
            'metricas' => $this->dataService->metricasGerais($itens, $movimentacoes, $baixas),
            'porCategoria' => $this->dataService->porCategoria($itens),
            'itens' => $itens,
            'movimentacoes' => $movimentacoes,
            'baixas' => $baixas,
            'reportTitle' => 'Relatório Geral de Inventário',
            'reportSubtitle' => 'Visão consolidada do inventário escolar',
            'reportFilters' => array_merge([
                'escola' => $inventario->escola?->nome ?? 'N/A',
            ], $this->dataService->formatarFiltros($filtros)),
            'usuarioExportacao' => $usuario,
            'dataExportacao' => now(),
            'orientation' => 'landscape',
        ], 'relatório-inventário-' . $this->slugInventario($inventario) . '.pdf');
    }

    public function gerarXlsxGeral(Inventario $inventario, array $params, ?User $usuario): Response
    {
        $filtros = $this->dataService->normalizarFiltros($params);
        $itens = $this->dataService->itens($inventario, $filtros);
        $movimentacoes = $this->dataService->movimentacoesDoInventario($inventario);
        $baixas = $this->dataService->baixasDoInventario($inventario);
        $metricas = $this->dataService->metricasGerais($itens, $movimentacoes, $baixas);
        $porCategoria = $this->dataService->porCategoria($itens);

        $spreadsheet = new Spreadsheet();

        $resumo = $spreadsheet->getActiveSheet();
        $resumo->setTitle('Resumo');
        $this->preencherResumoGeralSheet(
            $resumo,
            $inventario,
            $metricas,
            $porCategoria,
            $this->dataService->formatarFiltros($filtros),
            $usuario
        );

        $itensSheet = $spreadsheet->createSheet();
        $itensSheet->setTitle('Inventário');
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
            'relatório-inventário-' . $this->slugInventario($inventario) . '.xlsx'
        );
    }

    public function gerarPdfEnviosEscolas(array $params, ?User $usuario): Response
    {
        $relatorio = $this->dataService->relatorioEnviosEscolas($params);

        return $this->renderer->download('relatorios.Inventario.envios-escolas', [
            'resumo' => $relatorio->resumo,
            'escolas' => $relatorio->escolas,
            'periodoLabel' => $relatorio->periodo_label,
            'reportTitle' => 'Relatório de Envios para Escolas',
            'reportSubtitle' => 'Consolidado das entregas realizadas para os inventários escolares',
            'reportFilters' => $this->dataService->formatarFiltrosRelatorioEnvios($relatorio->filtros),
            'usuarioExportacao' => $usuario,
            'dataExportacao' => now(),
            'orientation' => 'landscape',
        ], 'relatório-envios-escolas-' . now()->format('Y-m-d_H-i') . '.pdf');
    }

    public function gerarXlsxEnviosEscolas(array $params, ?User $usuario): Response
    {
        $relatorio = $this->dataService->relatorioEnviosEscolas($params);
        $spreadsheet = new Spreadsheet();

        $resumoSheet = $spreadsheet->getActiveSheet();
        $resumoSheet->setTitle('Resumo');
        $this->preencherResumoEnviosSheet($resumoSheet, $relatorio, $usuario);

        $titulosUsados = ['Resumo'];

        foreach ($relatorio->escolas as $escola) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($this->resolverTituloSheetEscola($escola, $titulosUsados));
            $this->preencherEscolaEnviosSheet($sheet, $escola, $relatorio->periodo_label, $usuario);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $this->downloadSpreadsheet(
            $spreadsheet,
            'relatório-envios-escolas-' . now()->format('Y-m-d_H-i') . '.xlsx'
        );
    }

    public function gerarPdfItem(InventarioEstoque $estoque, ?User $usuario): Response
    {
        $estoque->loadMissing(['item', 'inventario.escola']);
        $movimentacoes = $this->dataService->movimentacoesPorEstoque($estoque);
        $baixas = $this->dataService->baixasPorEstoque($estoque);

        return $this->renderer->download('relatorios.Inventario.item-inventario', [
            'estoque' => $estoque,
            'inventario' => $estoque->inventario,
            'resumo' => $this->dataService->resumoItem($estoque),
            'movimentacoes' => $movimentacoes,
            'baixas' => $baixas,
            'reportTitle' => 'Relatório Individual de Inventário',
            'reportSubtitle' => 'Histórico completo do item no inventário escolar',
            'reportFilters' => [
                'escola' => $estoque->inventario?->escola?->nome ?? 'N/A',
                'item' => $estoque->item?->nome ?? 'N/A',
                'categoria' => $estoque->item?->tipo_item?->label() ?? 'N/A',
                'unidade' => strtoupper($estoque->item?->unidade_medida?->value ?? 'N/A'),
            ],
            'usuarioExportacao' => $usuario,
            'dataExportacao' => now(),
            'orientation' => 'landscape',
        ], 'relatório-item-inventário-' . $this->slugItem($estoque) . '.pdf');
    }

    public function gerarXlsxItem(InventarioEstoque $estoque, ?User $usuario): Response
    {
        $estoque->loadMissing(['item', 'inventario.escola']);
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
            'relatório-item-inventário-' . $this->slugItem($estoque) . '.xlsx'
        );
    }

    protected function preencherResumoGeralSheet(
        Worksheet $sheet,
        Inventario $inventario,
        object $metricas,
        Collection $porCategoria,
        array $filtros,
        ?User $usuario
    ): void {
        $linha = $this->preencherCabecalhoSheet(
            $sheet,
            'Relatório Geral de Inventário',
            array_merge(['escola' => $inventario->escola?->nome ?? 'N/A'], $filtros),
            $usuario
        );

        $sheet->setCellValue("A{$linha}", 'Indicador');
        $sheet->setCellValue("B{$linha}", 'Valor');
        $this->estilizarHeaderLinha($sheet, "A{$linha}:B{$linha}");
        $linha++;

        $indicadores = [
            'Itens filtrados' => $metricas->total_itens,
            'Quantidade total em inventário' => $metricas->quantidade_total,
            'Valor total estimado' => $metricas->valor_total,
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

        $this->estilizarCorpoTabela($sheet, 'A' . ($linha - count($indicadores)) . ':B' . ($linha - 1));
        $linha += 2;

        $sheet->setCellValue("A{$linha}", 'Categoria');
        $sheet->setCellValue("B{$linha}", 'Itens');
        $sheet->setCellValue("C{$linha}", 'Quantidade Total');
        $sheet->setCellValue("D{$linha}", 'Valor Total');
        $this->estilizarHeaderLinha($sheet, "A{$linha}:D{$linha}");
        $linha++;

        foreach ($porCategoria as $categoria) {
            $sheet->setCellValue("A{$linha}", $categoria['label']);
            $sheet->setCellValue("B{$linha}", $categoria['total_itens']);
            $sheet->setCellValue("C{$linha}", $categoria['quantidade_total']);
            $sheet->setCellValue("D{$linha}", $categoria['valor_total']);
            $linha++;
        }

        if ($porCategoria->isNotEmpty()) {
            $this->estilizarCorpoTabela($sheet, 'A' . ($linha - $porCategoria->count()) . ':D' . ($linha - 1));
        }

        $this->autoSizeColumns($sheet, 4);
    }

    protected function preencherResumoItemSheet(Worksheet $sheet, InventarioEstoque $estoque, object $resumo, ?User $usuario): void
    {
        $linha = $this->preencherCabecalhoSheet($sheet, 'Relatório Individual de Inventário', [
            'escola' => $estoque->inventario?->escola?->nome ?? 'N/A',
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

        $this->estilizarCorpoTabela($sheet, 'A' . ($linha - count($indicadores)) . ':B' . ($linha - 1));
        $this->autoSizeColumns($sheet, 2);
    }

    protected function preencherItensSheet(Worksheet $sheet, Collection $itens, ?User $usuario): void
    {
        $linha = $this->preencherCabecalhoSheet($sheet, 'Itens do Inventário', [], $usuario);

        $headers = ['Item', 'Descrição', 'Categoria', 'Unidade', 'Quantidade', 'Valor Unitário', 'Valor Total', 'Status', 'Atualizado em'];
        $this->preencherTabelaSimples($sheet, $linha, $headers, $itens->map(fn (array $item) => [
            $item['nome'],
            $item['descricao'],
            $item['tipo_label'],
            $item['unidade'],
            $item['quantidade'],
            $item['valor_unitario_referencia'],
            $item['valor_total'],
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
            $this->estilizarCorpoTabela($sheet, 'A' . ($linhaInicial + 1) . ":{$ultimaColuna}" . ($linha - 1));
        }

        $sheet->freezePane('A' . ($linhaInicial + 1));
        $this->autoSizeColumns($sheet, count($headers));
    }

    protected function preencherResumoEnviosSheet(Worksheet $sheet, object $relatorio, ?User $usuario): void
    {
        $linha = $this->preencherCabecalhoSheet(
            $sheet,
            'Relatório de Envios para Escolas',
            $this->dataService->formatarFiltrosRelatorioEnvios($relatorio->filtros),
            $usuario
        );

        $sheet->setCellValue("A{$linha}", 'Indicador');
        $sheet->setCellValue("B{$linha}", 'Valor');
        $this->estilizarHeaderLinha($sheet, "A{$linha}:B{$linha}");
        $linha++;

        $indicadores = [
            'Escolas com entregas' => $relatorio->resumo->total_escolas,
            'Pedidos atendidos' => $relatorio->resumo->total_pedidos,
            'Entregas registradas' => $relatorio->resumo->total_entregas,
            'Itens consolidados' => $relatorio->resumo->total_itens,
            'Quantidade total enviada' => $relatorio->resumo->quantidade_total,
            'Valor total estimado' => $relatorio->resumo->valor_total,
            'Período considerado' => $relatorio->periodo_label,
        ];

        foreach ($indicadores as $label => $valor) {
            $sheet->setCellValue("A{$linha}", $label);
            $sheet->setCellValue("B{$linha}", $valor);
            $linha++;
        }

        $this->estilizarCorpoTabela($sheet, 'A' . ($linha - count($indicadores)) . ':B' . ($linha - 1));
        $linha += 2;

        $sheet->setCellValue("A{$linha}", 'Escola');
        $sheet->setCellValue("B{$linha}", 'Pedidos');
        $sheet->setCellValue("C{$linha}", 'Itens');
        $sheet->setCellValue("D{$linha}", 'Quantidade');
        $sheet->setCellValue("E{$linha}", 'Valor');
        $sheet->setCellValue("F{$linha}", 'Ultimo envio');
        $this->estilizarHeaderLinha($sheet, "A{$linha}:F{$linha}");
        $linha++;

        foreach ($relatorio->escolas as $escola) {
            $sheet->setCellValue("A{$linha}", $escola['escola_nome']);
            $sheet->setCellValue("B{$linha}", $escola['total_pedidos']);
            $sheet->setCellValue("C{$linha}", $escola['total_itens']);
            $sheet->setCellValue("D{$linha}", $escola['quantidade_total']);
            $sheet->setCellValue("E{$linha}", $escola['valor_total']);
            $sheet->setCellValue("F{$linha}", $escola['ultimo_envio']);
            $linha++;
        }

        if ($relatorio->escolas->isNotEmpty()) {
            $this->estilizarCorpoTabela($sheet, 'A' . ($linha - $relatorio->escolas->count()) . ':F' . ($linha - 1));
        }

        $this->autoSizeColumns($sheet, 6);
    }

    protected function preencherEscolaEnviosSheet(
        Worksheet $sheet,
        array $escola,
        string $periodoLabel,
        ?User $usuario
    ): void {
        $linha = $this->preencherCabecalhoSheet($sheet, 'Envios para ' . $escola['escola_nome'], [
            'escola' => $escola['escola_nome'],
            'inventario' => $escola['inventario_nome'],
            'periodo' => $periodoLabel,
        ], $usuario);

        $sheet->setCellValue("A{$linha}", 'Indicador');
        $sheet->setCellValue("B{$linha}", 'Valor');
        $this->estilizarHeaderLinha($sheet, "A{$linha}:B{$linha}");
        $linha++;

        $indicadores = [
            'Pedidos atendidos' => $escola['total_pedidos'],
            'Entregas registradas' => $escola['total_entregas'],
            'Itens consolidados' => $escola['total_itens'],
            'Quantidade total enviada' => $escola['quantidade_total'],
            'Valor total estimado' => $escola['valor_total'],
            'Primeiro envio' => $escola['primeiro_envio'],
            'Ultimo envio' => $escola['ultimo_envio'],
        ];

        foreach ($indicadores as $label => $valor) {
            $sheet->setCellValue("A{$linha}", $label);
            $sheet->setCellValue("B{$linha}", $valor);
            $linha++;
        }

        $this->estilizarCorpoTabela($sheet, 'A' . ($linha - count($indicadores)) . ':B' . ($linha - 1));
        $linha += 2;

        $headers = [
            'Item',
            'Categoria',
            'Unidade',
            'Entregas',
            'Quantidade Enviada',
            'Valor Unitário',
            'Valor Total',
            'Ultima Entrega',
            'Romaneios',
        ];

        $this->preencherTabelaSimples($sheet, $linha, $headers, $escola['itens']->map(fn (array $item) => [
            $item['nome'],
            $item['categoria'],
            $item['unidade'],
            $item['total_entregas'],
            $item['quantidade_total'],
            $item['valor_unitario_referencia'],
            $item['valor_total'],
            $item['ultima_entrega'],
            $item['romaneios'] !== '' ? $item['romaneios'] : '-',
        ]));
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
        $tempPath = tempnam(sys_get_temp_dir(), 'inventario_relatorio_');

        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    protected function slugInventario(Inventario $inventario): string
    {
        return Str::slug($inventario->escola?->nome ?? $inventario->nome ?? 'inventario') . '-' . $inventario->getKey();
    }

    protected function slugItem(InventarioEstoque $estoque): string
    {
        return Str::slug($estoque->item?->nome ?? 'item') . '-' . $estoque->getKey();
    }

    protected function resolverTituloSheetEscola(array $escola, array &$titulosUsados): string
    {
        $base = trim((string) preg_replace('/[\\\\\\/?*:\\[\\]]/', ' ', $escola['escola_nome']));
        $base = $base !== '' ? $base : 'Escola';
        $base = Str::limit($base, 31, '');
        $titulo = $base;
        $contador = 2;

        while (in_array($titulo, $titulosUsados, true)) {
            $sufixo = ' ' . $contador;
            $titulo = Str::limit($base, 31 - strlen($sufixo), '') . $sufixo;
            $contador++;
        }

        $titulosUsados[] = $titulo;

        return $titulo;
    }
}
