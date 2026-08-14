<?php

namespace App\Services\Exports\Handlers;

use App\Contracts\Exports\ExportHandler;
use App\Models\ExportRequest;
use App\Models\LocalTrabalho;
use App\Policies\LocalTrabalhoPolicy;
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
        $policy = app(LocalTrabalhoPolicy::class);

        if (! $user || ! $policy->viewAny($user)) {
            throw new RuntimeException('Você não possui permissão para exportar locais de trabalho.');
        }

        $exportRequest->updateProgress(10, 100, 'Consultando locais de trabalho disponíveis.');

        $locais = $policy->applyViewAnyScope($user, LocalTrabalho::query())
            ->with(['setor', 'lotacoes'])
            ->orderBy('nome')
            ->get();

        $headers = [
            'Código', 'Nome', 'Tipo', 'E-mail', 'Telefone', 'Setor', 'Logradouro', 'Número',
            'Bairro', 'CEP', 'Cidade', 'UF', 'Complemento', 'Lotações', 'Status',
            'Criado em', 'Atualizado em',
        ];
        $rows = $locais->map(static fn (LocalTrabalho $local): array => [
            $local->codigo,
            $local->nome,
            $local->tipoLabel(),
            $local->email,
            $local->telefone,
            $local->setor?->nome_completo,
            $local->logradouro,
            $local->numero,
            $local->bairro,
            $local->cep,
            $local->cidade,
            $local->estado,
            $local->complemento,
            $local->lotacoes
                ->sortBy('codigo')
                ->map(static fn ($lotacao): string => "{$lotacao->codigo} - {$lotacao->nome}")
                ->implode('; '),
            $local->ativo ? 'Ativo' : 'Inativo',
            $local->created_at?->format('d/m/Y H:i:s'),
            $local->updated_at?->format('d/m/Y H:i:s'),
        ])->all();

        $exportRequest->updateProgress(60, 100, 'Montando planilha XLSX.');
        $contents = $this->xlsxContents($headers, $rows);
        $exportRequest->updateProgress(90, 100, 'Salvando planilha em armazenamento privado.');

        return $this->storage->storeContents(
            $exportRequest,
            $contents,
            'locais-de-trabalho-' . now()->format('Y-m-d_H-i') . '.xlsx',
            self::MIME_XLSX,
        );
    }

    /** @param list<string> $headers @param list<list<mixed>> $rows */
    private function xlsxContents(array $headers, array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Locais de trabalho');
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
