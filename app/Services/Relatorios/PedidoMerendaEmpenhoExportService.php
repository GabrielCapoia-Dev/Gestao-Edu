<?php

namespace App\Services\Relatorios;

use App\Models\PedidoMerenda;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class PedidoMerendaEmpenhoExportService
{
    public function exportar(PedidoMerenda $pedido, ?User $usuario): Response
    {
        $pedido->loadMissing([
            'itens.contratoItem.item',
            'itens.contratoItem.contrato.empresaContratada',
        ]);

        $spreadsheet = new Spreadsheet();

        $resumo = $spreadsheet->getActiveSheet();
        $resumo->setTitle('Resumo');
        $this->preencherResumo($resumo, $pedido, $usuario);

        $empresas = $spreadsheet->createSheet();
        $empresas->setTitle('Empresas');
        $this->preencherEmpresas($empresas, $pedido, $usuario);

        $itens = $spreadsheet->createSheet();
        $itens->setTitle('Itens');
        $this->preencherItens($itens, $pedido, $usuario);

        $spreadsheet->setActiveSheetIndex(0);

        return $this->downloadSpreadsheet(
            $spreadsheet,
            'empenho-pedido-merenda-' . $pedido->id . '-' . now()->format('Y-m-d_H-i') . '.xlsx'
        );
    }

    protected function preencherResumo(Worksheet $sheet, PedidoMerenda $pedido, ?User $usuario): void
    {
        $linha = $this->preencherCabecalho($sheet, 'Empenho do Pedido de Merenda', $usuario);
        $empresas = $pedido->itens
            ->map(fn ($item) => $item->contratoItem?->contrato?->empresaContratada?->nome)
            ->filter()
            ->unique()
            ->values();

        $dados = [
            ['Pedido', $pedido->id],
            ['Status', $pedido->status?->label() ?? '-'],
            ['Criado em', $pedido->created_at?->format('d/m/Y H:i') ?? '-'],
            ['Criado por', $pedido->criado_por ?: '-'],
            ['Itens', $pedido->itens->count()],
            ['Quantidade pedida', (float) $pedido->itens->sum('quantidade_pedida')],
            ['Quantidade entregue', (float) $pedido->itens->sum('quantidade_entregue')],
            ['Empresas relacionadas', $empresas->implode(', ') ?: '-'],
            ['Observacoes', $pedido->observacoes ?: '-'],
        ];

        $sheet->fromArray(['Campo', 'Valor'], null, "A{$linha}");
        $this->estilizarHeader($sheet, "A{$linha}:B{$linha}");
        $linha++;

        foreach ($dados as $row) {
            $sheet->fromArray($row, null, "A{$linha}");
            $linha++;
        }

        $this->estilizarCorpo($sheet, 'A' . ($linha - count($dados)) . ':B' . ($linha - 1));
        $this->autoSizeColumns($sheet, 2);
    }

    protected function preencherEmpresas(Worksheet $sheet, PedidoMerenda $pedido, ?User $usuario): void
    {
        $linha = $this->preencherCabecalho($sheet, 'Empresas Relacionadas ao Pedido', $usuario);

        $empresas = $pedido->itens
            ->map(function ($item) {
                $contrato = $item->contratoItem?->contrato;
                $empresa = $contrato?->empresaContratada;

                return [
                    'empresa' => $empresa?->nome ?: '-',
                    'cnpj' => $empresa?->cnpj ?: '-',
                    'responsavel' => $empresa?->responsavel ?: '-',
                    'telefone' => $empresa?->telefone ?: '-',
                    'email' => $empresa?->email ?: '-',
                    'contrato' => $contrato?->numero_contrato ?: '-',
                ];
            })
            ->unique(fn (array $empresa) => $empresa['empresa'] . '|' . $empresa['contrato'])
            ->values();

        $headers = ['Empresa', 'CNPJ', 'Responsavel', 'Telefone', 'Email', 'Contrato'];
        $sheet->fromArray($headers, null, "A{$linha}");
        $this->estilizarHeader($sheet, "A{$linha}:F{$linha}");
        $linha++;

        foreach ($empresas as $empresa) {
            $sheet->fromArray(array_values($empresa), null, "A{$linha}");
            $linha++;
        }

        if ($empresas->isNotEmpty()) {
            $this->estilizarCorpo($sheet, 'A' . ($linha - $empresas->count()) . ':F' . ($linha - 1));
        }

        $sheet->freezePane('A' . ($linha > 1 ? 6 : 1));
        $this->autoSizeColumns($sheet, 6);
    }

    protected function preencherItens(Worksheet $sheet, PedidoMerenda $pedido, ?User $usuario): void
    {
        $linha = $this->preencherCabecalho($sheet, 'Itens do Pedido', $usuario);

        $headers = ['Item', 'Unidade', 'Empresa', 'Contrato', 'Qtd. Pedida', 'Qtd. Entregue', 'Qtd. Pendente', 'Saldo Contrato'];
        $sheet->fromArray($headers, null, "A{$linha}");
        $this->estilizarHeader($sheet, "A{$linha}:H{$linha}");
        $linha++;

        foreach ($pedido->itens as $item) {
            $contratoItem = $item->contratoItem;

            $sheet->fromArray([
                $contratoItem?->item?->nome ?: '-',
                $contratoItem?->item?->unidade_medida?->value ?: '-',
                $contratoItem?->contrato?->empresaContratada?->nome ?: '-',
                $contratoItem?->contrato?->numero_contrato ?: '-',
                (float) $item->quantidade_pedida,
                (float) $item->quantidade_entregue,
                (float) $item->quantidade_pendente,
                (float) ($contratoItem?->saldo_disponivel ?? 0),
            ], null, "A{$linha}");

            $linha++;
        }

        if ($pedido->itens->isNotEmpty()) {
            $this->estilizarCorpo($sheet, 'A' . ($linha - $pedido->itens->count()) . ':H' . ($linha - 1));
        }

        $sheet->freezePane('A' . ($linha > 1 ? 6 : 1));
        $this->autoSizeColumns($sheet, 8);
    }

    protected function preencherCabecalho(Worksheet $sheet, string $titulo, ?User $usuario): int
    {
        $sheet->setCellValue('A1', $titulo);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', 'Gerado em ' . now()->format('d/m/Y H:i') . ' por ' . ($usuario?->name ?? 'Sistema'));
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        return 4;
    }

    protected function estilizarHeader(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1D4ED8'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
    }

    protected function estilizarCorpo(Worksheet $sheet, string $range): void
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
        $tempPath = tempnam(sys_get_temp_dir(), 'pedido_merenda_');

        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }
}
