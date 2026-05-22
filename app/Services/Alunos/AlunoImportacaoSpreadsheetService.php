<?php

namespace App\Services\Alunos;

use App\Exceptions\MatriculaAlunoBloqueadaException;
use App\Models\Aluno;
use App\Models\Escola;
use App\Models\ExportRequest;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use App\Services\AlunoMovimentacaoService;
use App\Services\AlunoService;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class AlunoImportacaoSpreadsheetService
{
    public const SHEET_NAME = 'Matriculados';

    public const CHUNK_SIZE = 100;

    private const HEADER_ALIASES = [
        'escola' => ['escola'],
        'seriacao' => ['seriacao', 'serie'],
        'turma' => ['turma'],
        'turno' => ['turno'],
        'cgm' => ['cgm'],
        'nome' => ['nome do aluno', 'aluno', 'nome'],
        'data_nascimento' => ['data de nasc', 'data de nascimento', 'nascimento'],
        'sexo' => ['sexo'],
        'data_matricula' => ['data matricula', 'data de matricula'],
    ];

    private const REQUIRED_HEADERS = [
        'escola',
        'seriacao',
        'turma',
        'turno',
        'cgm',
        'nome',
        'data_nascimento',
        'sexo',
    ];

    public function __construct(
        private readonly AlunoMovimentacaoService $movimentacaoService,
        private readonly AlunoService $alunoService
    ) {}

    public function exportarModelo(): Response
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);

        $sheet->setCellValue('A1', 'Escola');
        $sheet->setCellValue('B1', 'Seriacao');
        $sheet->setCellValue('C1', 'Turma');
        $sheet->setCellValue('D1', 'Turno');
        $sheet->setCellValue('E1', 'CGM');
        $sheet->setCellValue('F1', 'Nome do Aluno');
        $sheet->setCellValue('G1', 'Data de Nascimento');
        $sheet->setCellValue('H1', 'Sexo');
        $sheet->setCellValue('I1', 'Data Matricula');

        $sheet->fromArray([
            'CMEI - Cecilia Meireles',
            'INFANTIL 4',
            'A',
            'Manha',
            '1035708266',
            'NOME DO ALUNO',
            '07/02/2022',
            'F',
            '15/02/2026',
        ], null, 'A2');

        $this->estilizarCabecalho($sheet, 'A1:I1');
        $sheet->freezePane('A2');

        foreach (range(1, 9) as $indice) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($indice))->setAutoSize(true);
        }

        return $this->downloadSpreadsheet(
            $spreadsheet,
            'modelo-importacao-alunos-'.now()->format('Y-m-d_H-i').'.xlsx'
        );
    }

    public function importar(
        string $caminhoArquivo,
        ?User $usuario = null,
        string $disk = 'local',
        ?string $processRequestId = null,
    ): array {
        $caminhoCompleto = Storage::disk($disk)->path($caminhoArquivo);

        try {
            $validacao = $this->validarLinhas($this->carregarLinhas($caminhoCompleto));
            $linhas = $validacao['linhas'];
            $totalLinhas = count($linhas);

            $resultado = [
                'total_importado' => 0,
                'total_pendente' => 0,
                'series_criadas' => 0,
                'turmas_criadas' => 0,
                'duplicados_ignorados' => $validacao['duplicados_ignorados'],
            ];

            $processado = 0;

            foreach (array_chunk($linhas, self::CHUNK_SIZE) as $chunk) {
                $chunkResultado = DB::transaction(function () use ($chunk, $usuario): array {
                    $series = $this->seriesPorNome();
                    $turmas = $this->turmasPorChave();
                    $seriesCriadas = 0;
                    $turmasCriadas = 0;
                    $turmasPermitidas = [];
                    $dadosMatriculas = [];

                    foreach ($chunk as $linha) {
                        [$serie, $serieCriada] = $this->resolverSerie($linha['seriacao'], $series);
                        [$turma, $turmaCriada] = $this->resolverTurma($linha, $serie, $turmas);

                        $seriesCriadas += $serieCriada ? 1 : 0;
                        $turmasCriadas += $turmaCriada ? 1 : 0;

                        $turmaId = (int) $turma->id;

                        if (! isset($turmasPermitidas[$turmaId])) {
                            $this->alunoService->validarTurmaPermitida($turmaId, $usuario);
                            $turmasPermitidas[$turmaId] = true;
                        }

                        $dadosMatriculas[] = [
                            'nome' => $linha['nome'],
                            'cgm' => $linha['cgm'],
                            'data_nascimento' => $linha['data_nascimento'],
                            'sexo' => $linha['sexo'],
                            'data_matricula' => $linha['data_matricula'],
                            'id_turma' => $turmaId,
                            'status_motivo' => 'Matricula criada por importacao de planilha.',
                        ];
                    }

                    $loteResultado = $this->movimentacaoService->criarMatriculaEmLote($dadosMatriculas, $usuario);

                    return [
                        'total_importado' => $loteResultado['total_importado'],
                        'total_pendente' => $loteResultado['total_pendente'],
                        'series_criadas' => $seriesCriadas,
                        'turmas_criadas' => $turmasCriadas,
                    ];
                });

                $resultado['total_importado'] += $chunkResultado['total_importado'];
                $resultado['total_pendente'] += $chunkResultado['total_pendente'];
                $resultado['series_criadas'] += $chunkResultado['series_criadas'];
                $resultado['turmas_criadas'] += $chunkResultado['turmas_criadas'];

                $processado += count($chunk);

                if ($processRequestId) {
                    ExportRequest::query()
                        ->whereKey($processRequestId)
                        ->update([
                            'progress_current' => $processado,
                            'progress_total' => $totalLinhas,
                            'status_message' => "Processando alunos... {$processado} de {$totalLinhas}.",
                            'updated_at' => now(),
                        ]);
                }
            }

            return $resultado;
        } catch (MatriculaAlunoBloqueadaException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), previous: $exception);
        } finally {
            Storage::disk($disk)->delete($caminhoArquivo);
        }
    }

    private function carregarLinhas(string $caminhoCompleto): array
    {
        $reader = IOFactory::createReaderForFile($caminhoCompleto);
        $sheetNames = $reader->listWorksheetNames($caminhoCompleto);

        if (! in_array(self::SHEET_NAME, $sheetNames, true)) {
            throw new InvalidArgumentException('A planilha precisa conter uma aba chamada Matriculados.');
        }

        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([self::SHEET_NAME]);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $worksheetName === AlunoImportacaoSpreadsheetService::SHEET_NAME
                    && Coordinate::columnIndexFromString($columnAddress) <= 14;
            }
        });

        $spreadsheet = $reader->load($caminhoCompleto);
        $sheet = $spreadsheet->getSheetByName(self::SHEET_NAME);

        return $sheet?->toArray(null, true, true, true) ?? [];
    }

    private function validarLinhas(array $rows): array
    {
        if ($rows === []) {
            throw new InvalidArgumentException('O arquivo precisa conter cabecalho e ao menos uma linha de dados.');
        }

        [$headerRow, $headers] = $this->mapearCabecalho($rows);

        $linhas = collect($rows)
            ->filter(fn (array $row, int $numeroLinha): bool => $numeroLinha > $headerRow)
            ->map(fn (array $row, int $numeroLinha): array => [
                'numero_linha' => $numeroLinha,
                'escola' => $this->normalizarValor($row[$headers['escola']] ?? null),
                'seriacao' => $this->normalizarValor($row[$headers['seriacao']] ?? null),
                'turma' => $this->normalizarValor($row[$headers['turma']] ?? null),
                'turno' => $this->normalizarTurno($row[$headers['turno']] ?? null),
                'cgm' => $this->normalizarCgm($row[$headers['cgm']] ?? null),
                'nome' => $this->normalizarValor($row[$headers['nome']] ?? null),
                'data_nascimento' => $this->normalizarData($row[$headers['data_nascimento']] ?? null),
                'sexo' => $this->normalizarSexo($row[$headers['sexo']] ?? null),
                'data_matricula' => $this->normalizarData(isset($headers['data_matricula']) ? ($row[$headers['data_matricula']] ?? null) : null),
            ])
            ->reject(fn (array $linha): bool => collect([
                $linha['escola'],
                $linha['seriacao'],
                $linha['turma'],
                $linha['turno'],
                $linha['cgm'],
                $linha['nome'],
                $linha['data_nascimento'],
                $linha['sexo'],
                $linha['data_matricula'],
            ])->every(fn ($valor): bool => blank($valor)))
            ->values();

        if ($linhas->isEmpty()) {
            throw new InvalidArgumentException('O arquivo nao possui linhas preenchidas para importacao.');
        }

        $escolas = $this->escolasPorNome();
        $erros = [];

        $linhas->each(function (array $linha) use (&$erros, $escolas): void {
            $numeroLinha = $linha['numero_linha'];

            foreach ([
                'escola' => 'Escola',
                'seriacao' => 'Seriacao',
                'turma' => 'Turma',
                'turno' => 'Turno',
                'cgm' => 'CGM',
                'nome' => 'Nome do Aluno',
            ] as $campo => $label) {
                if (blank($linha[$campo])) {
                    $erros[] = "Linha {$numeroLinha}: informe {$label}.";
                }
            }

            if (! $linha['data_nascimento']) {
                $erros[] = "Linha {$numeroLinha}: informe uma Data de Nascimento valida.";
            }

            if (! $linha['sexo']) {
                $erros[] = "Linha {$numeroLinha}: informe Sexo como M ou F.";
            }

            $chaveEscola = $this->normalizarTexto($linha['escola']);

            if (filled($linha['escola']) && ! $escolas->has($chaveEscola)) {
                $erros[] = "Linha {$numeroLinha}: a escola {$linha['escola']} nao foi encontrada no cadastro.";
            }

        });

        if ($erros !== []) {
            throw new InvalidArgumentException(implode(PHP_EOL, $erros));
        }

        $cgms = [];
        $duplicadosIgnorados = 0;

        $linhasValidas = $linhas
            ->reject(function (array $linha) use (&$cgms, &$duplicadosIgnorados): bool {
                if (isset($cgms[$linha['cgm']])) {
                    $duplicadosIgnorados++;

                    return true;
                }

                $cgms[$linha['cgm']] = true;

                return false;
            })
            ->map(function (array $linha) use ($escolas): array {
                $linha['escola'] = $escolas->get($this->normalizarTexto($linha['escola']));

                return $linha;
            })
            ->all();

        return [
            'linhas' => $linhasValidas,
            'duplicados_ignorados' => $duplicadosIgnorados,
        ];
    }

    private function mapearCabecalho(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, true) as $numeroLinha => $row) {
            $headers = collect($row)
                ->mapWithKeys(fn ($valor, string $coluna): array => [$coluna => $this->normalizarTexto($valor)])
                ->filter()
                ->all();

            $mapa = [];

            foreach (self::HEADER_ALIASES as $campo => $aliases) {
                foreach ($headers as $coluna => $header) {
                    if (in_array($header, $aliases, true)) {
                        $mapa[$campo] = $coluna;
                        break;
                    }
                }
            }

            if (collect(self::REQUIRED_HEADERS)->every(fn (string $campo): bool => isset($mapa[$campo]))) {
                return [$numeroLinha, $mapa];
            }
        }

        throw new InvalidArgumentException(
            'Cabecalho invalido. Use as colunas: Escola, Seriacao, Turma, Turno, CGM, Nome do Aluno, Data de Nascimento e Sexo. Data Matricula e opcional.'
        );
    }

    private function resolverSerie(string $nome, Collection &$series): array
    {
        $chave = $this->normalizarTexto($nome);
        $serie = $series->get($chave);

        if ($serie) {
            return [$serie, false];
        }

        $serie = Serie::query()->create(['nome' => $nome]);
        $series->put($chave, $serie);

        return [$serie, true];
    }

    private function resolverTurma(array $linha, Serie $serie, Collection &$turmas): array
    {
        /** @var Escola $escola */
        $escola = $linha['escola'];
        $chave = $this->chaveTurma((int) $escola->id, (int) $serie->id, $linha['turma'], $linha['turno']);
        $turma = $turmas->get($chave);

        if ($turma) {
            return [$turma, false];
        }

        $turma = Turma::query()->create([
            'nome' => $linha['turma'],
            'turno' => $linha['turno'],
            'id_serie' => (int) $serie->id,
            'id_escola' => (int) $escola->id,
        ]);

        $turmas->put($chave, $turma);

        return [$turma, true];
    }

    private function escolasPorNome(): Collection
    {
        return Escola::query()
            ->get()
            ->keyBy(fn (Escola $escola): string => $this->normalizarTexto($escola->nome));
    }

    private function seriesPorNome(): Collection
    {
        return Serie::query()
            ->get()
            ->keyBy(fn (Serie $serie): string => $this->normalizarTexto($serie->nome));
    }

    private function turmasPorChave(): Collection
    {
        return Turma::query()
            ->get()
            ->keyBy(fn (Turma $turma): string => $this->chaveTurma(
                (int) $turma->id_escola,
                (int) $turma->id_serie,
                (string) $turma->nome,
                (string) $turma->turno
            ));
    }

    private function chaveTurma(int $escolaId, int $serieId, string $turma, string $turno): string
    {
        return implode('|', [
            $escolaId,
            $serieId,
            $this->normalizarTexto($turma),
            $this->normalizarTexto($turno),
        ]);
    }

    private function normalizarTurno(mixed $valor): ?string
    {
        $turno = $this->normalizarTexto($valor);

        return match ($turno) {
            'manha', 'matutino', 'm' => 'manha',
            'tarde', 'vespertino', 'v' => 'tarde',
            'noite', 'noturno', 'n' => 'noite',
            'integral', 'i' => 'integral',
            default => null,
        };
    }

    private function normalizarCgm(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (is_float($valor) || is_int($valor)) {
            $valor = number_format((float) $valor, 0, '', '');
        }

        $cgm = Aluno::normalizarCgm((string) $valor);
        $cgm = preg_replace('/\.0$/', '', $cgm);

        return $cgm !== '' ? $cgm : null;
    }

    private function normalizarSexo(mixed $valor): ?string
    {
        $sexo = $this->normalizarTexto($valor);

        return match ($sexo) {
            'm', 'masculino' => 'M',
            'f', 'feminino' => 'F',
            default => null,
        };
    }

    private function normalizarData(mixed $valor): ?string
    {
        if ($valor instanceof DateTimeInterface) {
            return Carbon::instance($valor)->toDateString();
        }

        if (is_numeric($valor)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $valor))->toDateString();
        }

        $valor = $this->normalizarValor($valor);

        if ($valor === null) {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'd-m-y'] as $formato) {
            try {
                $data = Carbon::createFromFormat($formato, $valor);

                if ($data !== false) {
                    return $data->toDateString();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizarValor(mixed $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : null;
    }

    private function normalizarTexto(mixed $valor): string
    {
        return Str::of((string) $valor)
            ->ascii()
            ->lower()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
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
                'startColor' => ['rgb' => '2563EB'],
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
        $tempPath = tempnam(sys_get_temp_dir(), 'alunos_importacao_');

        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }
}
