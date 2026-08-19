<?php

namespace App\Services\Avaliacoes;

use App\Models\Pauta;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PautaXlsxExportService
{
    /**
     * @param  Collection<int, Pauta>|iterable<Pauta>  $records
     */
    public function download(Collection|iterable $records): StreamedResponse
    {
        $ids = collect($records)
            ->map(fn (Pauta $pauta): int => (int) $pauta->getKey())
            ->filter()
            ->unique()
            ->values();

        $pautas = Pauta::query()
            ->with([
                'tipo:id,nome',
                'serie:id,nome',
                'componente:id,nome',
                'alternativas:id,nome',
            ])
            ->withCount('avaliacoes')
            ->whereIn('id', $ids->all())
            ->get()
            ->sortBy(fn (Pauta $pauta): int => $ids->search((int) $pauta->getKey()))
            ->values();

        $nomeArquivo = 'pautas-selecionadas-'.now()->format('Y-m-d_H-i-s').'.xlsx';

        return response()->streamDownload(function () use ($pautas): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Pautas');

            $cabecalhos = [
                'ID',
                'Pauta',
                'Tipo',
                'Série',
                'Componente',
                'Alternativas',
                'Qtd. Avaliações',
                'Status',
                'Atualizada em',
            ];

            $sheet->fromArray($cabecalhos, null, 'A1');

            $linha = 2;

            foreach ($pautas as $pauta) {
                $sheet->fromArray([
                    (int) $pauta->id,
                    (string) $pauta->texto,
                    $pauta->tipo?->nome ?: 'Sem tipo',
                    $pauta->serie?->nome ?: 'Sem série',
                    $pauta->componente?->nome ?: 'Geral',
                    $pauta->alternativas->pluck('nome')->implode(' | '),
                    (int) $pauta->avaliacoes_count,
                    $pauta->status ? 'Ativa' : 'Inativa',
                    $pauta->updated_at?->format('d/m/Y H:i') ?? '',
                ], null, "A{$linha}");

                $linha++;
            }

            $ultimaLinha = max($linha - 1, 1);

            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:I{$ultimaLinha}");
            $sheet->getRowDimension(1)->setRowHeight(24);

            $sheet->getStyle('A1:I1')->applyFromArray([
                'font' => [
                    'bold' => true,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9EAF7'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ]);

            if ($ultimaLinha >= 2) {
                $sheet->getStyle("A2:I{$ultimaLinha}")->getAlignment()
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true);
            }

            $larguras = [
                'A' => 10,
                'B' => 70,
                'C' => 24,
                'D' => 20,
                'E' => 28,
                'F' => 55,
                'G' => 16,
                'H' => 14,
                'I' => 20,
            ];

            foreach ($larguras as $coluna => $largura) {
                $sheet->getColumnDimension($coluna)->setWidth($largura);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $nomeArquivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
