<?php

namespace App\Services\Exports\Handlers;

use App\Contracts\Exports\ExportHandler;
use App\Models\ExportRequest;
use App\Models\Lotacao;
use App\Policies\LotacaoPolicy;
use App\Services\Exports\ExportFileResult;
use App\Services\Exports\ExportFileStorage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class LotacaoXlsxExportHandler implements ExportHandler
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
        $policy = app(LotacaoPolicy::class);

        if (! $user || ! $policy->viewAny($user)) {
            throw new RuntimeException('Você não possui permissão para exportar lotações.');
        }

        $exportRequest->updateProgress(10, 100, 'Consultando lotações disponíveis.');

        $lotacoes = $policy->applyViewAnyScope($user, Lotacao::query())
            ->join('escolas', 'escolas.id', '=', 'lotacoes.escola_id')
            ->select('lotacoes.*')
            ->with(['localTrabalho.setor'])
            ->orderBy('escolas.nome')
            ->orderBy('lotacoes.codigo')
            ->get();

        $headers = [
            'Número da lotação',
            'Nome da lotação',
            'Local de trabalho',
            'Tipo do local',
            'Setor',
            'Criada em',
            'Atualizada em',
        ];

        $rows = $lotacoes->map(static fn (Lotacao $lotacao): array => [
            $lotacao->codigo,
            $lotacao->nome,
            $lotacao->localTrabalho?->nome,
            $lotacao->localTrabalho?->tipoLabel(),
            $lotacao->localTrabalho?->setor?->nome_completo,
            $lotacao->created_at?->format('d/m/Y H:i:s'),
            $lotacao->updated_at?->format('d/m/Y H:i:s'),
        ])->all();

        $exportRequest->updateProgress(60, 100, 'Montando planilha XLSX.');
        $contents = $this->xlsxContents($headers, $rows);
        $exportRequest->updateProgress(90, 100, 'Salvando planilha em armazenamento privado.');

        return $this->storage->storeContents(
            $exportRequest,
            $contents,
            'lotacoes-'.now()->format('Y-m-d_H-i').'.xlsx',
            self::MIME_XLSX,
        );
    }

    /** @param list<string> $headers @param list<list<mixed>> $rows */
    private function xlsxContents(array $headers, array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Lotações');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $lastRow = max(2, count($rows) + 1);
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F4E78');
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);
        $sheet->setAutoFilter("A1:{$lastColumn}1");
        $sheet->freezePane('A2');

        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'gestao-edu-lotacoes-xlsx-');

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
