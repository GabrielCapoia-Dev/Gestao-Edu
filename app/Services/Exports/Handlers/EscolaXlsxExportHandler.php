<?php

namespace App\Services\Exports\Handlers;

use App\Contracts\Exports\ExportHandler;
use App\Models\Escola;
use App\Models\ExportRequest;
use App\Policies\EscolaPolicy;
use App\Services\Exports\ExportFileResult;
use App\Services\Exports\ExportFileStorage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class EscolaXlsxExportHandler implements ExportHandler
{
    private const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(
        private readonly ExportFileStorage $storage,
    ) {}

    public function handle(ExportRequest $exportRequest): ExportFileResult
    {
        ini_set('memory_limit', (string) config('exports.xlsx_memory_limit', '512M'));

        if ($exportRequest->format !== 'xlsx') {
            throw new RuntimeException('O formato solicitado para esta exportação não é suportado.');
        }

        $user = $exportRequest->user;
        $policy = app(EscolaPolicy::class);

        if (! $user || ! $policy->viewAny($user)) {
            throw new RuntimeException('Você não possui permissão para exportar escolas.');
        }

        $exportRequest->updateProgress(10, 100, 'Consultando escolas disponíveis.');

        $escolas = $policy->applyViewAnyScope($user, Escola::query())
            ->with(['setor', 'lotacoes'])
            ->orderBy('nome')
            ->get();

        $headers = [
            'Código', 'Nome', 'E-mail', 'Telefone', 'Setor', 'Logradouro', 'Número',
            'Bairro', 'CEP', 'Cidade', 'UF', 'Complemento', 'Lotações', 'Status',
            'Criado em', 'Atualizado em',
        ];
        $rows = $escolas->map(static fn (Escola $escola): array => [
            $escola->codigo,
            $escola->nome,
            $escola->email,
            $escola->telefone,
            $escola->setor?->nome_completo,
            $escola->logradouro,
            $escola->numero,
            $escola->bairro,
            $escola->cep,
            $escola->cidade,
            $escola->estado,
            $escola->complemento,
            $escola->lotacoes
                ->sortBy('codigo')
                ->map(static fn ($lotacao): string => "{$lotacao->codigo} - {$lotacao->nome}")
                ->implode('; '),
            $escola->ativo ? 'Ativa' : 'Inativa',
            $escola->created_at?->format('d/m/Y H:i:s'),
            $escola->updated_at?->format('d/m/Y H:i:s'),
        ])->all();

        $exportRequest->updateProgress(60, 100, 'Montando planilha XLSX.');
        $contents = $this->xlsxContents($headers, $rows);
        $exportRequest->updateProgress(90, 100, 'Salvando planilha em armazenamento privado.');

        return $this->storage->storeContents(
            $exportRequest,
            $contents,
            'escolas-' . now()->format('Y-m-d_H-i') . '.xlsx',
            self::MIME_XLSX,
        );
    }

    /** @param list<string> $headers @param list<list<mixed>> $rows */
    private function xlsxContents(array $headers, array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Escolas');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F4E78');
        $sheet->getStyle("A1:{$lastColumn}" . max(2, count($rows) + 1))->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);
        $sheet->setAutoFilter("A1:{$lastColumn}1");
        $sheet->freezePane('A2');

        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'gestao-edu-escolas-xlsx-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Não foi possível criar o arquivo temporário da exportação.');
        }

        try {
            (new Xlsx($spreadsheet))->save($temporaryPath);
            $contents = file_get_contents($temporaryPath);

            if ($contents === false || $contents === '') {
                throw new RuntimeException('A planilha XLSX foi gerada sem conteúdo.');
            }

            return $contents;
        } finally {
            $spreadsheet->disconnectWorksheets();

            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }
}
