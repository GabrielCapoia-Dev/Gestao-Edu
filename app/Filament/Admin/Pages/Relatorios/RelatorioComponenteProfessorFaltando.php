<?php

namespace App\Filament\Admin\Pages\Relatorios;

use App\Models\Escola;
use App\Models\Serie;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use App\Models\Professor;
use App\Models\Turma;
use App\Models\ComponenteCurricular;
use App\Models\TurmaComponenteProfessor;

class RelatorioComponenteProfessorFaltando extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBar;
    protected static ?string $title = 'Componentes com Falta de Professores';
    protected string $view = 'filament.pages.relatorios.relatorio-componente-professor-faltando';
    protected static ?string $slug = 'relatorio-componentes-com-professores-faltando';
    protected static bool $shouldRegisterNavigation = false;

    // Filtros compartilhados
    public ?int $escola_id  = null;
    public ?int $serie_id   = null;
    public string $search   = '';
    public string $situacao = '';

    // Paginação — componentes
    public int $perPage = 10;
    public int $page    = 1;

    // Paginação — turmas
    public int $perPageTurmas = 10;
    public int $pageTurmas    = 1;

    // KPIs
    public int $totalProfessores = 0;
    public int $totalTurmas      = 0;
    public int $totalComponentes = 0;
    public int $totalEscolas     = 0;
    public int $vinculos         = 0;
    public int $comProfessor     = 0;
    public int $semProfessor     = 0;
    public array $topComponentes = [];

    // Tabela de componentes
    public array $registros    = [];
    public int $totalRegistros = 0;

    // Tabela de turmas
    public array $turmas              = [];
    public int $totalTurmasFaltando   = 0;

    public static function canAccess(): bool
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        return $user->hasPermissionTo('Listar Relatórios: Componentes com Professores Faltando');
    }

    public function mount(): void
    {
        $this->totalProfessores = Professor::count();
        $this->totalTurmas      = Turma::count();
        $this->totalComponentes = ComponenteCurricular::count();
        $this->totalEscolas     = Escola::count();

        $this->vinculos     = TurmaComponenteProfessor::count();
        $this->comProfessor = TurmaComponenteProfessor::whereNotNull('professor_id')->count();
        $this->semProfessor = TurmaComponenteProfessor::whereNull('professor_id')->count();

        $this->topComponentes = DB::table('turma_componente_professor as tcp')
            ->join('componentes_curriculares as cc', 'cc.id', '=', 'tcp.componente_curricular_id')
            ->selectRaw('cc.nome, COUNT(*) as total,
                SUM(CASE WHEN tcp.professor_id IS NOT NULL THEN 1 ELSE 0 END) as com_professor,
                SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) as sem_professor')
            ->groupBy('cc.id', 'cc.nome')
            ->havingRaw('SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) > 0')
            ->orderByDesc('sem_professor')
            ->limit(5)
            ->get()
            ->toArray();
            

        $this->carregarDados();
        $this->carregarTurmas();
    }

    public function carregarDados(): void
    {
        // Base de vínculos com os filtros aplicados
        $baseQuery = DB::table('turma_componente_professor as tcp')
            ->join('componentes_curriculares as cc', 'cc.id', '=', 'tcp.componente_curricular_id')
            ->join('turmas', 'turmas.id', '=', 'tcp.turma_id')
            ->join('escolas', 'escolas.id', '=', 'turmas.id_escola')
            ->join('series', 'series.id', '=', 'turmas.id_serie');

        if ($this->escola_id) {
            $baseQuery->where('turmas.id_escola', $this->escola_id);
        }
        if ($this->serie_id) {
            $baseQuery->where('turmas.id_serie', $this->serie_id);
        }
        if ($this->search) {
            $baseQuery->where('cc.nome', 'like', '%' . $this->search . '%');
        }

        // KPIs reativos
        $kpiQuery = clone $baseQuery;
        $this->vinculos     = $kpiQuery->count();
        $this->comProfessor = (clone $baseQuery)->whereNotNull('tcp.professor_id')->count();
        $this->semProfessor = (clone $baseQuery)->whereNull('tcp.professor_id')->count();

        // Tabela de componentes
        $query = clone $baseQuery;
        $query->selectRaw('
            cc.id as componente_id,
            cc.nome as componente_nome,
            COUNT(*) as total,
            SUM(CASE WHEN tcp.professor_id IS NOT NULL THEN 1 ELSE 0 END) as com_professor,
            SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) as sem_professor
        ')
            ->groupBy('cc.id', 'cc.nome')
            ->havingRaw('SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) > 0');

        if ($this->situacao === 'com_professor') {
            $query->havingRaw('SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) = 0');
        }

        $this->totalRegistros = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->count();

        $this->registros = $query
            ->orderByDesc('sem_professor')
            ->offset(($this->page - 1) * $this->perPage)
            ->limit($this->perPage)
            ->get()
            ->toArray();
    }

    public function carregarTurmas(): void
    {
        $query = DB::table('turma_componente_professor as tcp')
            ->join('turmas', 'turmas.id', '=', 'tcp.turma_id')
            ->join('escolas', 'escolas.id', '=', 'turmas.id_escola')
            ->join('series', 'series.id', '=', 'turmas.id_serie')
            ->join('componentes_curriculares as cc', 'cc.id', '=', 'tcp.componente_curricular_id')
            ->whereNull('tcp.professor_id')
            ->selectRaw('
            turmas.id as turma_id,
            turmas.nome as turma_nome,
            escolas.nome as escola_nome,
            series.nome as serie_nome,
            COUNT(*) as componentes_sem_professor
        ')
            ->groupBy('turmas.id', 'turmas.nome', 'escolas.nome', 'series.nome');

        if ($this->escola_id) {
            $query->where('turmas.id_escola', $this->escola_id);
        }
        if ($this->serie_id) {
            $query->where('turmas.id_serie', $this->serie_id);
        }
        if ($this->search) {
            $query->where('cc.nome', 'like', '%' . $this->search . '%');
        }

        $this->totalTurmasFaltando = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->count();

        $this->turmas = $query
            ->orderByDesc('componentes_sem_professor')
            ->offset(($this->pageTurmas - 1) * $this->perPageTurmas)
            ->limit($this->perPageTurmas)
            ->get()
            ->toArray();
    }

    public function updatedSituacao(): void
    {
        $this->page = 1;
        $this->carregarDados();
    }

    public function updatedSearch(): void
    {
        $this->page       = 1;
        $this->pageTurmas = 1;
        $this->carregarDados();
        $this->carregarTurmas();
    }

    public function updatedEscolaId(): void
    {
        $this->page       = 1;
        $this->pageTurmas = 1;
        $this->carregarDados();
        $this->carregarTurmas();
    }

    public function updatedSerieId(): void
    {
        $this->page       = 1;
        $this->pageTurmas = 1;
        $this->carregarDados();
        $this->carregarTurmas();
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

    public function getTotalPaginasProperty(): int
    {
        return (int) ceil($this->totalRegistros / $this->perPage);
    }

    public function getTotalPaginasTurmasProperty(): int
    {
        return (int) ceil($this->totalTurmasFaltando / $this->perPageTurmas);
    }

    public function getEscolasProperty(): \Illuminate\Support\Collection
    {
        return Escola::orderBy('nome')->get();
    }

    public function getSeriesProperty(): \Illuminate\Support\Collection
    {
        return Serie::orderBy('nome')->get();
    }
}
