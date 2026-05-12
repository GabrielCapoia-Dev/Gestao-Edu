<?php

namespace App\Filament\Admin\Pages\Relatorios;

use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RelatorioComponenteProfessorFaltando extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBar;
    protected static ?string $title = 'Componentes com Falta de Professores';
    protected string $view = 'filament.pages.relatorios.relatorio-componente-professor-faltando';
    protected static ?string $slug = 'relatorio-componentes-com-professores-faltando';
    protected static bool $shouldRegisterNavigation = false;

    public ?int $escola_id = null;
    public ?int $serie_id = null;
    public string $turno = '';
    public string $search = '';
    public string $situacao = '';

    public int $perPage = 10;
    public int $page = 1;

    public int $perPageTurmas = 10;
    public int $pageTurmas = 1;

    public string $componentesSortField = 'sem_professor';
    public string $componentesSortDirection = 'desc';
    public string $turmasSortField = 'componentes_sem_professor';
    public string $turmasSortDirection = 'desc';

    public int $totalProfessores = 0;
    public int $totalTurmas = 0;
    public int $totalComponentes = 0;
    public int $totalEscolas = 0;
    public int $vinculos = 0;
    public int $comProfessor = 0;
    public int $semProfessor = 0;

    public array $registros = [];
    public int $totalRegistros = 0;

    public array $turmas = [];
    public int $totalTurmasFaltando = 0;

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.resources.turmas.pages.manage-turmas-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Relatórios',
            'title' => "Relatório de Componentes com Professores Faltando",
            'description' => 'Visualize o relatório detalhado de componentes com falta de professores, com opções de filtragem e exportação.',
        ]);
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user->hasPermissionTo('Listar Relatórios: Componentes com Professores Faltando');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportar_relatorio_geral')
                ->label('Exportar Relatório Geral')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn(): bool => Auth::user()?->hasPermissionTo('Exportar Relatórios') ?? false)
                ->modalHeading('Exportar Relatório Geral')
                ->modalDescription(fn(): string => $this->getExportModalDescription())
                ->modalSubmitActionLabel('Exportar XLSX')
                ->action(fn() => $this->exportarRelatorioGeral()),
        ];
    }

    public function mount(): void
    {
        $this->totalProfessores = Professor::count();
        $this->totalTurmas = Turma::count();
        $this->totalComponentes = ComponenteCurricular::count();
        $this->totalEscolas = Escola::count();

        $this->recarregarPagina();
    }

    public function carregarDados(): void
    {
        $this->totalRegistros = $this->countSubquery($this->componentesQuery(false));

        $this->registros = $this->componentesQuery()
            ->offset(($this->page - 1) * $this->perPage)
            ->limit($this->perPage)
            ->get()
            ->map(fn(object $registro): array => (array) $registro)
            ->all();
    }

    public function carregarTurmas(): void
    {
        $this->totalTurmasFaltando = $this->countSubquery($this->turmasQuery(false));

        $this->turmas = $this->turmasQuery()
            ->offset(($this->pageTurmas - 1) * $this->perPageTurmas)
            ->limit($this->perPageTurmas)
            ->get()
            ->map(fn(object $turma): array => (array) $turma)
            ->all();
    }

    public function updatedSearch(): void
    {
        $this->resetarPaginas();
        $this->recarregarPagina();
    }

    public function updatedEscolaId(): void
    {
        $this->resetarPaginas();
        $this->recarregarPagina();
    }

    public function updatedSerieId(): void
    {
        $this->resetarPaginas();
        $this->recarregarPagina();
    }

    public function updatedTurno(): void
    {
        $this->resetarPaginas();
        $this->recarregarPagina();
    }

    public function updatedSituacao(): void
    {
        $this->page = 1;
        $this->carregarDados();
    }

    public function updatedPerPage(): void
    {
        $this->page = 1;
        $this->carregarDados();
    }

    public function updatedPerPageTurmas(): void
    {
        $this->pageTurmas = 1;
        $this->carregarTurmas();
    }

    public function irParaPagina(int $pagina): void
    {
        $this->page = $pagina;
        $this->carregarDados();
    }

    public function irParaPaginaTurmas(int $pagina): void
    {
        $this->pageTurmas = $pagina;
        $this->carregarTurmas();
    }

    public function ordenarComponentes(string $field): void
    {
        if (! array_key_exists($field, $this->componentesSortLabels())) {
            return;
        }

        if ($this->componentesSortField === $field) {
            $this->componentesSortDirection = $this->toggleSortDirection($this->componentesSortDirection);
        } else {
            $this->componentesSortField = $field;
            $this->componentesSortDirection = $this->defaultComponentesSortDirection($field);
        }

        $this->page = 1;
        $this->carregarDados();
    }

    public function ordenarTurmas(string $field): void
    {
        if (! array_key_exists($field, $this->turmasSortLabels())) {
            return;
        }

        if ($this->turmasSortField === $field) {
            $this->turmasSortDirection = $this->toggleSortDirection($this->turmasSortDirection);
        } else {
            $this->turmasSortField = $field;
            $this->turmasSortDirection = $this->defaultTurmasSortDirection($field);
        }

        $this->pageTurmas = 1;
        $this->carregarTurmas();
    }

    public function getTotalPaginasProperty(): int
    {
        return max(1, (int) ceil($this->totalRegistros / $this->perPage));
    }

    public function getTotalPaginasTurmasProperty(): int
    {
        return max(1, (int) ceil($this->totalTurmasFaltando / $this->perPageTurmas));
    }

    public function getEscolasProperty(): \Illuminate\Support\Collection
    {
        return Escola::orderBy('nome')->get();
    }

    public function getSeriesProperty(): \Illuminate\Support\Collection
    {
        return Serie::orderBy('nome')->get();
    }

    public function getSituacoesProperty(): array
    {
        return [
            '' => 'Todos com falta',
            'sem_professor' => 'Sem nenhum professor',
            'com_professor' => 'Cobertura parcial',
        ];
    }

    public function getTurnosProperty(): array
    {
        return [
            '' => 'Todos os turnos',
            'manha' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            'integral' => 'Integral',
        ];
    }

    public function getComponentesSortIndicator(string $field): string
    {
        if ($this->componentesSortField !== $field) {
            return '↕';
        }

        return $this->componentesSortDirection === 'asc' ? '↑' : '↓';
    }

    public function getTurmasSortIndicator(string $field): string
    {
        if ($this->turmasSortField !== $field) {
            return '↕';
        }

        return $this->turmasSortDirection === 'asc' ? '↑' : '↓';
    }

    public function exportarRelatorioGeral()
    {
        $componentes = $this->componentesQuery()->get();
        $turmas = $this->turmasQuery()->get();

        $spreadsheet = new Spreadsheet();

        $sheetComponentes = $spreadsheet->getActiveSheet();
        $sheetComponentes->setTitle('Componentes');
        $this->preencherAbaComponentes($sheetComponentes, $componentes->all());

        $sheetTurmas = $spreadsheet->createSheet();
        $sheetTurmas->setTitle('Turmas');
        $this->preencherAbaTurmas($sheetTurmas, $turmas->all());

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'relatorio_componentes_falta_professores_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        $path = storage_path("app/public/{$filename}");

        (new Xlsx($spreadsheet))->save($path);

        Notification::make()
            ->title('Relatório exportado com sucesso!')
            ->body('Componentes: ' . $componentes->count() . ' | Turmas: ' . $turmas->count())
            ->success()
            ->send();

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    protected function recarregarPagina(): void
    {
        $this->carregarKpis();
        $this->carregarDados();
        $this->carregarTurmas();
    }

    protected function resetarPaginas(): void
    {
        $this->page = 1;
        $this->pageTurmas = 1;
    }

    protected function carregarKpis(): void
    {
        $query = $this->baseVinculosQuery();

        $this->vinculos = (clone $query)->count();
        $this->comProfessor = (clone $query)->whereNotNull('tcp.professor_id')->count();
        $this->semProfessor = (clone $query)->whereNull('tcp.professor_id')->count();
    }

    protected function baseVinculosQuery(): QueryBuilder
    {
        $query = DB::table('turma_componente_professor as tcp')
            ->join('componentes_curriculares as cc', 'cc.id', '=', 'tcp.componente_curricular_id')
            ->join('turmas', 'turmas.id', '=', 'tcp.turma_id')
            ->join('escolas', 'escolas.id', '=', 'turmas.id_escola')
            ->join('series', 'series.id', '=', 'turmas.id_serie');

        if ($this->escola_id) {
            $query->where('turmas.id_escola', $this->escola_id);
        }

        if ($this->serie_id) {
            $query->where('turmas.id_serie', $this->serie_id);
        }

        if ($this->turno !== '') {
            $query->where('turmas.turno', $this->turno);
        }

        if ($this->search !== '') {
            $search = '%' . trim($this->search) . '%';

            $query->where(function (QueryBuilder $subQuery) use ($search): void {
                $subQuery
                    ->where('cc.nome', 'like', $search)
                    ->orWhere('turmas.nome', 'like', $search)
                    ->orWhere('escolas.nome', 'like', $search)
                    ->orWhere('series.nome', 'like', $search);
            });
        }

        return $query;
    }

    protected function componentesQuery(bool $applySorting = true): QueryBuilder
    {
        $query = $this->baseVinculosQuery()
            ->selectRaw('
                cc.id as componente_id,
                cc.nome as componente_nome,
                COUNT(*) as total,
                SUM(CASE WHEN tcp.professor_id IS NOT NULL THEN 1 ELSE 0 END) as com_professor,
                SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) as sem_professor,
                ROUND(
                    COALESCE(
                        (
                            SUM(CASE WHEN tcp.professor_id IS NOT NULL THEN 1 ELSE 0 END) * 100.0
                        ) / NULLIF(COUNT(*), 0),
                        0
                    ),
                    2
                ) as cobertura_percentual
            ')
            ->groupBy('cc.id', 'cc.nome')
            ->havingRaw('SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) > 0');

        if ($this->situacao === 'sem_professor') {
            $query->havingRaw('SUM(CASE WHEN tcp.professor_id IS NOT NULL THEN 1 ELSE 0 END) = 0');
        } elseif ($this->situacao === 'com_professor') {
            $query->havingRaw('SUM(CASE WHEN tcp.professor_id IS NOT NULL THEN 1 ELSE 0 END) > 0');
        }

        if ($applySorting) {
            $this->applyComponentesOrdering($query);
        }

        return $query;
    }

    protected function turmasQuery(bool $applySorting = true): QueryBuilder
    {
        $query = $this->baseVinculosQuery()
            ->whereNull('tcp.professor_id')
            ->selectRaw('
                turmas.id as turma_id,
                turmas.nome as turma_nome,
                turmas.turno as turma_turno,
                escolas.nome as escola_nome,
                series.nome as serie_nome,
                COUNT(*) as componentes_sem_professor
            ')
            ->groupBy('turmas.id', 'turmas.nome', 'turmas.turno', 'escolas.nome', 'series.nome');

        if ($applySorting) {
            $this->applyTurmasOrdering($query);
        }

        return $query;
    }

    protected function applyComponentesOrdering(QueryBuilder $query): void
    {
        $direction = $this->normalizeSortDirection($this->componentesSortDirection);

        match ($this->componentesSortField) {
            'componente_nome' => $query->orderBy('componente_nome', $direction),
            'total' => $query->orderBy('total', $direction)->orderBy('componente_nome'),
            'com_professor' => $query->orderBy('com_professor', $direction)->orderBy('componente_nome'),
            'cobertura_percentual' => $query->orderBy('cobertura_percentual', $direction)->orderBy('componente_nome'),
            default => $query->orderBy('sem_professor', $direction)->orderBy('componente_nome'),
        };
    }

    protected function applyTurmasOrdering(QueryBuilder $query): void
    {
        $direction = $this->normalizeSortDirection($this->turmasSortDirection);

        match ($this->turmasSortField) {
            'escola_nome' => $query->orderBy('escola_nome', $direction)->orderBy('serie_nome'),
            'serie_nome' => $query->orderBy('serie_nome', $direction)->orderBy('turma_nome'),
            'turma_nome' => $query->orderBy('turma_nome', $direction),
            'turma_turno' => $query->orderBy('turma_turno', $direction)->orderBy('turma_nome'),
            default => $query->orderBy('componentes_sem_professor', $direction)->orderBy('escola_nome'),
        };
    }

    protected function countSubquery(QueryBuilder $query): int
    {
        return DB::query()->fromSub($query, 'sub')->count();
    }

    protected function normalizeSortDirection(string $direction): string
    {
        return $direction === 'asc' ? 'asc' : 'desc';
    }

    protected function toggleSortDirection(string $direction): string
    {
        return $direction === 'asc' ? 'desc' : 'asc';
    }

    protected function defaultComponentesSortDirection(string $field): string
    {
        return $field === 'componente_nome' ? 'asc' : 'desc';
    }

    protected function defaultTurmasSortDirection(string $field): string
    {
        return in_array($field, ['escola_nome', 'serie_nome', 'turma_nome', 'turma_turno'], true) ? 'asc' : 'desc';
    }

    protected function componentesSortLabels(): array
    {
        return [
            'componente_nome' => 'Componente',
            'total' => 'Total de vínculos',
            'com_professor' => 'Com professor',
            'sem_professor' => 'Sem professor',
            'cobertura_percentual' => 'Cobertura',
        ];
    }

    protected function turmasSortLabels(): array
    {
        return [
            'escola_nome' => 'Escola',
            'serie_nome' => 'Série',
            'turma_nome' => 'Turma',
            'turma_turno' => 'Turno',
            'componentes_sem_professor' => 'Componentes sem professor',
        ];
    }

    protected function getExportModalDescription(): string
    {
        return 'A exportação em XLSX usará os filtros e a ordenação atuais da tela, gerando uma aba de Componentes e outra de Turmas.';
    }

    protected function preencherAbaComponentes(Worksheet $sheet, array $dados): void
    {
        $headers = [
            'Componente',
            'Total de vínculos',
            'Com professor',
            'Sem professor',
            'Cobertura (%)',
        ];

        $primeiraLinhaDados = $this->preencherCabecalhoDaAba(
            $sheet,
            'Relatório Geral - Componentes com Falta de Professores',
            $this->componentesFiltersSummary(),
            $headers
        );

        $linha = $primeiraLinhaDados;

        foreach ($dados as $item) {
            $sheet->setCellValue("A{$linha}", $item->componente_nome);
            $sheet->setCellValue("B{$linha}", (int) $item->total);
            $sheet->setCellValue("C{$linha}", (int) $item->com_professor);
            $sheet->setCellValue("D{$linha}", (int) $item->sem_professor);
            $sheet->setCellValue("E{$linha}", (float) $item->cobertura_percentual);
            $linha++;
        }

        $this->aplicarEstiloTabela($sheet, $headers, $primeiraLinhaDados, $linha - 1);
    }

    protected function preencherAbaTurmas(Worksheet $sheet, array $dados): void
    {
        $headers = [
            'Escola',
            'Série',
            'Turma',
            'Turno',
            'Componentes sem professor',
        ];

        $primeiraLinhaDados = $this->preencherCabecalhoDaAba(
            $sheet,
            'Relatório Geral - Turmas com Componentes sem Professor',
            $this->turmasFiltersSummary(),
            $headers
        );

        $linha = $primeiraLinhaDados;

        foreach ($dados as $item) {
            $sheet->setCellValue("A{$linha}", $item->escola_nome);
            $sheet->setCellValue("B{$linha}", $item->serie_nome);
            $sheet->setCellValue("C{$linha}", $item->turma_nome);
            $sheet->setCellValue("D{$linha}", $this->formatarTurno($item->turma_turno));
            $sheet->setCellValue("E{$linha}", (int) $item->componentes_sem_professor);
            $linha++;
        }

        $this->aplicarEstiloTabela($sheet, $headers, $primeiraLinhaDados, $linha - 1);
    }

    protected function preencherCabecalhoDaAba(Worksheet $sheet, string $titulo, array $filtros, array $headers): int
    {
        $ultimaColuna = Coordinate::stringFromColumnIndex(count($headers));
        $linha = 1;

        $sheet->mergeCells("A{$linha}:{$ultimaColuna}{$linha}");
        $sheet->setCellValue("A{$linha}", $titulo);
        $sheet->getStyle("A{$linha}:{$ultimaColuna}{$linha}")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['rgb' => '0F172A'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
            ],
        ]);
        $linha++;

        $sheet->mergeCells("A{$linha}:{$ultimaColuna}{$linha}");
        $sheet->setCellValue(
            "A{$linha}",
            'Gerado em ' . now()->format('d/m/Y H:i') . ' por ' . (Auth::user()?->name ?? 'Sistema')
        );
        $sheet->getStyle("A{$linha}:{$ultimaColuna}{$linha}")->applyFromArray([
            'font' => [
                'italic' => true,
                'color' => ['rgb' => '475569'],
            ],
        ]);
        $linha += 2;

        $sheet->mergeCells("A{$linha}:{$ultimaColuna}{$linha}");
        $sheet->setCellValue("A{$linha}", 'Filtros aplicados');
        $sheet->getStyle("A{$linha}:{$ultimaColuna}{$linha}")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E3A5F'],
            ],
        ]);
        $linha++;

        foreach ($filtros as $label => $valor) {
            $sheet->setCellValue("A{$linha}", $label);
            $sheet->mergeCells("B{$linha}:{$ultimaColuna}{$linha}");
            $sheet->setCellValue("B{$linha}", $valor);
            $sheet->getStyle("A{$linha}")->getFont()->setBold(true);
            $linha++;
        }

        $linha++;
        $sheet->fromArray($headers, null, "A{$linha}");
        $sheet->freezePane('A' . ($linha + 1));

        return $linha + 1;
    }

    protected function aplicarEstiloTabela(Worksheet $sheet, array $headers, int $primeiraLinhaDados, int $ultimaLinhaDados): void
    {
        $ultimaColuna = Coordinate::stringFromColumnIndex(count($headers));
        $linhaCabecalho = $primeiraLinhaDados - 1;

        $sheet->getStyle("A{$linhaCabecalho}:{$ultimaColuna}{$linhaCabecalho}")->applyFromArray([
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

        if ($ultimaLinhaDados >= $primeiraLinhaDados) {
            $sheet->getStyle("A{$primeiraLinhaDados}:{$ultimaColuna}{$ultimaLinhaDados}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
        }

        foreach (range(1, count($headers)) as $indice) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($indice))->setAutoSize(true);
        }
    }

    protected function commonFiltersSummary(): array
    {
        return [
            'Busca' => $this->search !== '' ? $this->search : 'Sem filtro textual',
            'Escola' => $this->selectedEscolaLabel(),
            'Série' => $this->selectedSerieLabel(),
            'Turno' => $this->turnoLabel(),
        ];
    }

    protected function componentesFiltersSummary(): array
    {
        return array_merge($this->commonFiltersSummary(), [
            'Situação dos componentes' => $this->situacaoLabel(),
            'Ordenação' => $this->sortSummary($this->componentesSortLabels(), $this->componentesSortField, $this->componentesSortDirection),
        ]);
    }

    protected function turmasFiltersSummary(): array
    {
        return array_merge($this->commonFiltersSummary(), [
            'Ordenação' => $this->sortSummary($this->turmasSortLabels(), $this->turmasSortField, $this->turmasSortDirection),
        ]);
    }

    protected function sortSummary(array $labels, string $field, string $direction): string
    {
        $label = $labels[$field] ?? $field;
        $directionLabel = $direction === 'asc' ? 'crescente' : 'decrescente';

        return "{$label} ({$directionLabel})";
    }

    protected function selectedEscolaLabel(): string
    {
        if (! $this->escola_id) {
            return 'Todas as escolas';
        }

        return Escola::query()->whereKey($this->escola_id)->value('nome') ?? 'Escola não encontrada';
    }

    protected function selectedSerieLabel(): string
    {
        if (! $this->serie_id) {
            return 'Todas as séries';
        }

        return Serie::query()->whereKey($this->serie_id)->value('nome') ?? 'Série não encontrada';
    }

    protected function situacaoLabel(): string
    {
        $situacoes = $this->getSituacoesProperty();

        return $situacoes[$this->situacao] ?? 'Todos com falta';
    }

    protected function turnoLabel(): string
    {
        $turnos = $this->getTurnosProperty();

        return $turnos[$this->turno] ?? 'Todos os turnos';
    }

    public function formatarTurno(?string $turno): string
    {
        return match ($turno) {
            'manha' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            'integral' => 'Integral',
            default => $turno ?: 'Não informado',
        };
    }
    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }
}
