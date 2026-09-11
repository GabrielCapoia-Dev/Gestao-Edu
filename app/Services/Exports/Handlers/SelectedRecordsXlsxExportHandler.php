<?php

namespace App\Services\Exports\Handlers;

use App\Contracts\Exports\ExportHandler;
use App\Models\Aluno;
use App\Models\ExportRequest;
use App\Models\Servidor;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Policies\ServidorPolicy;
use App\Policies\TurmaPolicy;
use App\Services\AlunoService;
use App\Services\Exports\ExportFileResult;
use App\Services\Exports\ExportFileStorage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class SelectedRecordsXlsxExportHandler implements ExportHandler
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
        $ids = collect($exportRequest->filters['ids'] ?? [])
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if (! $user || $ids->isEmpty()) {
            throw new RuntimeException('A exportação não possui usuário ou registros válidos.');
        }

        $exportRequest->updateProgress(10, 100, 'Validando registros selecionados.');

        [$headers, $rows, $fileName, $sheetName, $recordCount] = match ($exportRequest->type) {
            'turmas_selecionadas' => $this->turmas($user, $ids->all()),
            'turmas_selecionadas_detalhado' => $this->turmasDetalhadas($user, $ids->all()),
            'servidores_selecionados' => $this->servidores($user, $ids->all()),
            'alunos_selecionados' => $this->alunos($user, $ids->all()),
            default => throw new RuntimeException('Tipo de exportação de registros não suportado.'),
        };

        if ($recordCount !== $ids->count()) {
            throw new RuntimeException('Um ou mais registros selecionados não estão mais disponíveis no seu escopo de acesso.');
        }

        $exportRequest->updateProgress(60, 100, 'Montando planilha XLSX.');
        $contents = $this->xlsxContents($headers, $rows, $sheetName);
        $exportRequest->updateProgress(90, 100, 'Salvando planilha em armazenamento privado.');

        return $this->storage->storeContents(
            $exportRequest,
            $contents,
            $fileName,
            self::MIME_XLSX,
        );
    }

    /** @return array{0:list<string>,1:list<list<mixed>>,2:string,3:string,4:int} */
    private function turmas($user, array $ids): array
    {
        $policy = app(TurmaPolicy::class);

        if (! $policy->export($user)) {
            throw new RuntimeException('Você não possui permissão para exportar turmas.');
        }

        /** @var Collection<int, Turma> $records */
        $records = $policy->applyViewAnyScope($user, Turma::query())
            ->whereKey($ids)
            ->with(['escola', 'serie'])
            ->withCount('alunos')
            ->orderBy('id')
            ->get();

        return [[
            'Escola', 'Série', 'Turma', 'Turno', 'Número de alunos',
        ], $records->map(fn (Turma $record): array => [
            $record->escola?->nome,
            $record->serie?->nome,
            $record->nome,
            $this->turnoLabel($record->turno),
            $record->alunos_count,
        ])->all(), 'turmas-selecionadas.xlsx', 'Turmas', $records->count()];
    }

    /** @return array{0:list<string>,1:list<list<mixed>>,2:string,3:string,4:int} */
    private function turmasDetalhadas($user, array $ids): array
    {
        $policy = app(TurmaPolicy::class);

        if (! $policy->export($user)) {
            throw new RuntimeException('Você não possui permissão para exportar turmas.');
        }

        /** @var Collection<int, Turma> $records */
        $records = $policy->applyViewAnyScope($user, Turma::query())
            ->whereKey($ids)
            ->with(['escola', 'serie'])
            ->withCount('alunos')
            ->orderBy('id')
            ->get();

        $vinculos = TurmaComponenteProfessor::query()
            ->whereIn('turma_id', $records->modelKeys())
            ->with(['componente', 'professor'])
            ->orderBy('turma_id')
            ->orderBy('componente_curricular_id')
            ->get()
            ->groupBy('turma_id');

        $rows = $records->flatMap(function (Turma $record) use ($vinculos): array {
            $base = [
                $record->escola?->nome,
                $record->serie?->nome,
                $record->nome,
                $this->turnoLabel($record->turno),
                $record->alunos_count,
            ];
            $itens = $vinculos->get($record->id, collect());

            if ($itens->isEmpty()) {
                return [[...$base, null, null]];
            }

            return $itens->map(fn (TurmaComponenteProfessor $vinculo): array => [
                ...$base,
                $vinculo->componente?->nome,
                $vinculo->tem_professor ? $vinculo->professor?->nome : null,
            ])->all();
        })->values()->all();

        return [[
            'Escola', 'Série', 'Turma', 'Turno', 'Número de alunos', 'Componente', 'Professor',
        ], $rows, 'turmas-selecionadas-detalhado.xlsx', 'Turmas detalhadas', $records->count()];
    }

    /** @return array{0:list<string>,1:list<list<mixed>>,2:string,3:string,4:int} */
    private function servidores($user, array $ids): array
    {
        $policy = app(ServidorPolicy::class);

        if (! $policy->viewAny($user)) {
            throw new RuntimeException('Você não possui permissão para exportar servidores.');
        }

        /** @var Collection<int, Servidor> $records */
        $records = $policy->applyViewAnyScope($user, Servidor::query()->withTrashed())
            ->whereKey($ids)
            ->with([
                'escola', 'user', 'matriculas', 'lotacao.escola',
                'professores.escola', 'professores.professorMatricula',
                'vinculosAtivos.funcaoAdministrativa', 'vinculosAtivos.escola',
            ])
            ->orderBy('id')
            ->get();

        return [[
            'Escola', 'Matrícula', 'Nome', 'Turno', 'Carga horária', 'Jornada',
            'Lotação', 'Escola da lotação', 'E-mail', 'Cargo', 'Status',
        ], $records->map(fn (Servidor $record): array => [
            collect([$record->escola?->nome])
                ->merge($record->professores->where('ativo', true)->pluck('escola.nome'))
                ->merge($record->vinculosAtivos->pluck('escola.nome'))
                ->filter()->unique()->sort()->values()->implode('; '),
            collect([$record->matricula])
                ->merge($record->matriculas->pluck('matricula'))
                ->merge($record->vinculosAtivos->pluck('matricula'))
                ->filter()->unique()->sort()->values()->implode('; '),
            $record->nome,
            $record->matriculas->pluck('turno')
                ->merge($record->professores->where('ativo', true)->map(
                    static fn ($professor): mixed => $professor->professorMatricula?->turno ?? $professor->turno,
                ))
                ->filter()
                ->map(fn (mixed $turno): mixed => $this->turnoLabel((string) $turno))
                ->unique()->sort()->values()->implode('; '),
            $record->cargaHorariaLabel(),
            $record->jornadaLabel(),
            $record->lotacaoLabel(),
            $record->lotacao?->escola?->nome ?? 'Não informada',
            collect([$record->email, $record->user?->email])
                ->merge($record->professores->where('ativo', true)->pluck('email'))
                ->first(static fn (mixed $email): bool => filled($email)),
            $record->vinculosAtivos->pluck('funcaoAdministrativa.nome')
                ->when(
                    $record->professores->where('ativo', true)->isNotEmpty(),
                    static fn ($cargos) => $cargos->push('Professor'),
                )
                ->filter()->unique()->sort()->values()->implode('; '),
            Servidor::statusOptions()[$record->status] ?? $record->status,
        ])->all(), 'servidores-selecionados.xlsx', 'Servidores', $records->count()];
    }

    /** @return array{0:list<string>,1:list<list<mixed>>,2:string,3:string,4:int} */
    private function alunos($user, array $ids): array
    {
        if (! app(\App\Policies\AlunoPolicy::class)->export($user)) {
            throw new RuntimeException('Você não possui permissão para exportar alunos.');
        }

        /** @var Collection<int, Aluno> $records */
        $records = app(AlunoService::class)->queryVisivel($user)
            ->whereKey($ids)
            ->with(['turma.escola', 'turma.serie'])
            ->orderBy('id')
            ->get();

        $principaisIds = $records
            ->filter(fn (Aluno $record): bool => $record->isPrincipal())
            ->modelKeys();
        $contraTurnos = app(AlunoService::class)->queryVisivel($user)
            ->whereIn('aluno_origem_id', $principaisIds)
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_CONTRA_TURNO)
            ->where('status', Aluno::STATUS_MATRICULADO)
            ->with(['turma.escola', 'turma.serie'])
            ->get()
            ->keyBy('aluno_origem_id');

        return [[
            'Escola', 'Série', 'Turma', 'Turno', 'CGM', 'Nome do aluno', 'Status', 'Sexo',
            'Data de nascimento', 'Contraturno', 'Escola do contraturno', 'Série do contraturno',
            'Turma do contraturno', 'Turno do contraturno',
        ], $records->map(function (Aluno $record) use ($contraTurnos): array {
            $contraTurno = $record->isContraTurno()
                ? $record
                : $contraTurnos->get($record->id);

            return [
                $record->turma?->escola?->nome,
                $record->turma?->serie?->nome,
                $record->turma?->nome,
                $this->turnoLabel($record->turma?->turno),
                $record->cgm,
                $record->nome,
                $record->statusLabel(),
                match ($record->sexo) { 'F' => 'Feminino', 'M' => 'Masculino', default => $record->sexo },
                $record->data_nascimento?->format('d/m/Y'),
                $contraTurno ? 'Sim' : 'Não',
                $contraTurno?->turma?->escola?->nome,
                $contraTurno?->turma?->serie?->nome,
                $contraTurno?->turma?->nome,
                $this->turnoLabel($contraTurno?->turma?->turno),
            ];
        })->all(), 'alunos-selecionados.xlsx', 'Alunos', $records->count()];
    }

    /** @param list<string> $headers @param list<list<mixed>> $rows */
    private function xlsxContents(array $headers, array $rows, string $sheetName): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F4E78');
        $sheet->getStyle("A1:{$lastColumn}".max(2, count($rows) + 1))->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);
        $sheet->setAutoFilter("A1:{$lastColumn}1");
        $sheet->freezePane('A2');

        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'gestao-edu-xlsx-');

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

    private function dateTime(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->format('d/m/Y H:i:s') : null;
    }

    private function turnoLabel(?string $turno): ?string
    {
        return match ($turno) {
            'manha' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            'integral' => 'Integral',
            default => $turno,
        };
    }
}
