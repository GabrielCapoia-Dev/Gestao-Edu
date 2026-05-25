<?php

namespace App\Filament\Admin\Pages\Relatorios;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use BackedEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Professor;
use App\Models\Turma;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\TurmaComponenteProfessor;

class RelatoriosDashboard extends Page
{
    protected string $view = 'filament.pages.relatorios.relatorios-dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBar;
    protected static ?string $navigationLabel = 'Relatórios';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Dashboard de Relatórios';
    protected static ?string $slug = 'relatorios-dashboard';


    public int $totalProfessores    = 0;
    public int $totalTurmas         = 0;
    public int $totalComponentes    = 0;
    public int $totalEscolas        = 0;
    public int $vinculos            = 0;
    public int $semProfessor        = 0;
    public int $comProfessor        = 0;

    public int $perPageComponentes = 5;
    public int $pageComponentes = 1;
    public int $totalComponentesRegistros = 0;
    public array $topComponentes = [];

    public int $perPageEscolas = 5;
    public int $pageEscolas = 1;
    public int $totalEscolasRegistros = 0;
    public array $faltasPorEscolaComponente = [];

    public function mount(): void
    {
        $this->totalProfessores = Professor::count();
        $this->totalTurmas      = Turma::count();
        $this->totalComponentes = ComponenteCurricular::count();
        $this->totalEscolas     = Escola::where('ativo', true)->count();

        $this->vinculos     = TurmaComponenteProfessor::count();
        $this->semProfessor = $this->contarTurmasComProfessorFaltando();
        $this->comProfessor = $this->contarTurmasComTodosComponentesComProfessor();

        $this->carregarComponentes();
        $this->carregarEscolas();
    }

    protected function componentesQuery(bool $applyOrdering = true): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('turma_componente_professor as tcp')
            ->join('componentes_curriculares as cc', 'cc.id', '=', 'tcp.componente_curricular_id')
            ->select(
                'cc.nome',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN tcp.professor_id IS NOT NULL THEN 1 ELSE 0 END) as com_professor'),
                DB::raw('SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) as sem_professor')
            )
            ->groupBy('cc.id', 'cc.nome')
            ->havingRaw('SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) > 0');

        if ($applyOrdering) {
            $query->orderByDesc('sem_professor');
        }

        return $query;
    }

    protected function escolasQuery(bool $applyOrdering = true): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('turma_componente_professor as tcp')
            ->join('turmas', 'turmas.id', '=', 'tcp.turma_id')
            ->join('escolas', 'escolas.id', '=', 'turmas.id_escola')
            ->join('componentes_curriculares as cc', 'cc.id', '=', 'tcp.componente_curricular_id')
            ->whereNull('tcp.professor_id')
            ->select(
                'escolas.nome as escola_nome',
                'cc.nome as componente_nome',
                DB::raw('COUNT(*) as professores_faltando'),
                DB::raw('COUNT(DISTINCT turmas.id) as turmas_afetadas')
            )
            ->groupBy('escolas.id', 'escolas.nome', 'cc.id', 'cc.nome');

        if ($applyOrdering) {
            $query->orderByDesc('professores_faltando')
                ->orderBy('escolas.nome')
                ->orderBy('cc.nome');
        }

        return $query;
    }

    public function carregarComponentes(): void
    {
        $this->totalComponentesRegistros = $this->countSubquery($this->componentesQuery(false));

        $this->topComponentes = $this->componentesQuery()
            ->offset(($this->pageComponentes - 1) * $this->perPageComponentes)
            ->limit($this->perPageComponentes)
            ->get()
            ->toArray();
    }

    public function carregarEscolas(): void
    {
        $this->totalEscolasRegistros = $this->countSubquery($this->escolasQuery(false));

        $this->faltasPorEscolaComponente = $this->escolasQuery()
            ->offset(($this->pageEscolas - 1) * $this->perPageEscolas)
            ->limit($this->perPageEscolas)
            ->get()
            ->toArray();
    }

    public function updatedPerPageComponentes(): void
    {
        $this->pageComponentes = 1;
        $this->carregarComponentes();
    }

    public function updatedPerPageEscolas(): void
    {
        $this->pageEscolas = 1;
        $this->carregarEscolas();
    }

    public function irParaPaginaComponentes(int $pagina): void
    {
        $this->pageComponentes = $pagina;
        $this->carregarComponentes();
    }

    public function irParaPaginaEscolas(int $pagina): void
    {
        $this->pageEscolas = $pagina;
        $this->carregarEscolas();
    }

    public function getTotalPaginasComponentesProperty(): int
    {
        return max(1, (int) ceil($this->totalComponentesRegistros / $this->perPageComponentes));
    }

    public function getTotalPaginasEscolasProperty(): int
    {
        return max(1, (int) ceil($this->totalEscolasRegistros / $this->perPageEscolas));
    }

    protected function contarTurmasComProfessorFaltando(): int
    {
        return $this->countSubquery(
            DB::table('turma_componente_professor as tcp')
                ->select('tcp.turma_id')
                ->groupBy('tcp.turma_id')
                ->havingRaw('SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) > 0')
        );
    }

    protected function contarTurmasComTodosComponentesComProfessor(): int
    {
        return $this->countSubquery(
            DB::table('turma_componente_professor as tcp')
                ->select('tcp.turma_id')
                ->groupBy('tcp.turma_id')
                ->havingRaw('SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) = 0')
        );
    }

    protected function countSubquery(\Illuminate\Database\Query\Builder $query): int
    {
        return DB::query()->fromSub($query, 'sub')->count();
    }

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.pages.partials.page-header', [
            'eyebrow' => 'Dados atualizados em tempo real',
            'title' => 'Central de Relatórios',
            'description' => 'Visualize, filtre e exporte os dados do sistema. Selecione um relatório abaixo para começar.',
        ]);
    }


    public static function canAccess(): bool
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        return $user->hasPermissionTo('Listar Relatórios: Dashboard');
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
