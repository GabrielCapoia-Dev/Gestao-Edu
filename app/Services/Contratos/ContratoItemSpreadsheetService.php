<?php

namespace App\Services\Contratos;

use App\Models\Contrato;
use App\Models\Enums\TipoItemContrato;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class ContratoItemSpreadsheetService
{
    private const HEADER_CODIGO = 'codigo do item';
    private const HEADER_QUANTIDADE = 'quantidade';
    private const HEADER_PRECO = 'preco unitario';

    public function exportarModelo(): Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Modelo');

        $sheet->setCellValue('A1', 'Modelo de Importacao de Itens do Contrato');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', 'Preencha uma linha por item usando codigos ja cadastrados no sistema.');
        $sheet->setCellValue('A4', 'Codigo do Item');
        $sheet->setCellValue('B4', 'Quantidade');
        $sheet->setCellValue('C4', 'Preco unitario');

        $this->estilizarCabecalho($sheet, 'A4:C4');

        foreach (range(1, 3) as $indice) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($indice))->setAutoSize(true);
        }

        $sheet->freezePane('A5');

        return $this->downloadSpreadsheet(
            $spreadsheet,
            'modelo-itens-contrato-' . now()->format('Y-m-d_H-i') . '.xlsx'
        );
    }

    public function importar(Contrato $contrato, string $caminhoArquivo, string $disk = 'local'): array
    {
        $caminhoCompleto = Storage::disk($disk)->path($caminhoArquivo);

        try {
            $rows = $this->carregarLinhas($caminhoCompleto);
            $linhasValidadas = $this->validarLinhas($rows);

            DB::transaction(function () use ($contrato, $linhasValidadas): void {
                foreach ($linhasValidadas as $linha) {
                    $contrato->contratoItens()->create([
                        'item_id' => $linha['item']->id,
                        'tipo' => TipoItemContrato::Compra,
                        'quantidade_total' => $linha['quantidade'],
                        'quantidade_utilizada' => 0,
                        'preco_unitario' => $linha['preco_unitario'],
                    ]);
                }
            });

            return [
                'total_importado' => count($linhasValidadas),
                'codigos' => array_map(fn (array $linha) => $linha['item']->codigo, $linhasValidadas),
            ];
        } finally {
            Storage::disk($disk)->delete($caminhoArquivo);
        }
    }

    private function carregarLinhas(string $caminhoCompleto): array
    {
        $spreadsheet = IOFactory::load($caminhoCompleto);
        $sheet = $spreadsheet->getActiveSheet();

        return $sheet->toArray(null, true, true, false);
    }

    private function validarLinhas(array $rows): array
    {
        if ($rows === [] || count($rows) < 2) {
            throw new InvalidArgumentException('O arquivo precisa conter cabecalho e ao menos uma linha de dados.');
        }

        $headers = array_map(fn ($header) => $this->normalizarCabecalho($header), $rows[0]);

        $esperados = [
            self::HEADER_CODIGO,
            self::HEADER_QUANTIDADE,
            self::HEADER_PRECO,
        ];

        if ($headers !== $esperados) {
            throw new InvalidArgumentException('Cabecalho invalido. Use exatamente: Codigo do Item, Quantidade, Preco unitario.');
        }

        $linhas = collect(array_slice($rows, 1))
            ->map(fn (array $row, int $index) => [
                'numero_linha' => $index + 2,
                'codigo' => $this->normalizarCodigo($row[0] ?? null),
                'quantidade' => $row[1] ?? null,
                'preco_unitario' => $row[2] ?? null,
            ])
            ->reject(fn (array $linha) => $linha['codigo'] === null
                && blank($linha['quantidade'])
                && blank($linha['preco_unitario']))
            ->values();

        if ($linhas->isEmpty()) {
            throw new InvalidArgumentException('O arquivo nao possui linhas preenchidas para importacao.');
        }

        $erros = [];
        $codigos = [];
        $itens = Item::query()
            ->whereIn('codigo', $linhas->pluck('codigo')->filter()->all())
            ->get()
            ->keyBy('codigo');

        $linhasValidadas = $linhas->map(function (array $linha) use (&$erros, &$codigos, $itens): ?array {
            $codigo = $linha['codigo'];
            $numeroLinha = $linha['numero_linha'];

            if ($codigo === null) {
                $erros[] = "Linha {$numeroLinha}: informe o Codigo do Item.";
                return null;
            }

            if (isset($codigos[$codigo])) {
                $erros[] = "Linha {$numeroLinha}: o codigo {$codigo} aparece mais de uma vez no arquivo.";
                return null;
            }

            $codigos[$codigo] = true;
            $item = $itens->get($codigo);

            if (! $item) {
                $erros[] = "Linha {$numeroLinha}: o codigo {$codigo} nao foi encontrado no cadastro de itens.";
                return null;
            }

            if (! $item->ativo) {
                $erros[] = "Linha {$numeroLinha}: o item {$codigo} esta inativo e nao pode ser importado.";
                return null;
            }

            $quantidade = $this->normalizarNumero($linha['quantidade']);
            $precoUnitario = $this->normalizarNumero($linha['preco_unitario']);

            if ($quantidade === null || $quantidade <= 0) {
                $erros[] = "Linha {$numeroLinha}: a Quantidade deve ser um numero maior que zero.";
            }

            if ($precoUnitario === null || $precoUnitario <= 0) {
                $erros[] = "Linha {$numeroLinha}: o Preco unitario deve ser um numero maior que zero.";
            }

            if ($quantidade === null || $precoUnitario === null || $quantidade <= 0 || $precoUnitario <= 0) {
                return null;
            }

            return [
                'item' => $item,
                'quantidade' => round($quantidade, 3),
                'preco_unitario' => round($precoUnitario, 2),
            ];
        })->filter()->values()->all();

        if ($erros !== []) {
            throw new InvalidArgumentException(implode(PHP_EOL, $erros));
        }

        return $linhasValidadas;
    }

    private function normalizarCabecalho(mixed $valor): string
    {
        return Str::of((string) $valor)
            ->ascii()
            ->lower()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
    }

    private function normalizarCodigo(mixed $valor): ?string
    {
        $codigo = trim((string) $valor);

        return $codigo !== '' ? $codigo : null;
    }

    private function normalizarNumero(mixed $valor): ?float
    {
        if ($valor === null) {
            return null;
        }

        if (is_numeric($valor)) {
            return (float) $valor;
        }

        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        $valor = str_replace('R$', '', $valor);
        $valor = preg_replace('/\s+/', '', $valor);

        if (str_contains($valor, ',') && str_contains($valor, '.')) {
            $ultimaVirgula = strrpos($valor, ',');
            $ultimoPonto = strrpos($valor, '.');

            if ($ultimaVirgula > $ultimoPonto) {
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            } else {
                $valor = str_replace(',', '', $valor);
            }
        } elseif (str_contains($valor, ',')) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        }

        return is_numeric($valor) ? (float) $valor : null;
    }

    private function estilizarCabecalho(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F766E'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $fileName): Response
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'contrato_itens_');

        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }
}
