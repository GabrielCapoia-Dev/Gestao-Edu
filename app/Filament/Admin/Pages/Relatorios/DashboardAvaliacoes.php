<?php

namespace App\Filament\Admin\Pages\Relatorios;

use App\Models\Alternativa;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Services\Relatorios\RelatorioPdfRenderer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use UnitEnum;

class DashboardAvaliacoes extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.relatorios.dashboard-avaliacoes';

    protected static ?string $title = 'Dashboard de Avaliações';

    protected static ?string $navigationLabel = 'Dashboard Avaliações';

    protected static ?string $navigationParentItem = 'Avaliações';

    protected static ?string $slug = 'dashboard-avaliacoes';

    protected static ?int $navigationSort = 26;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public array $filtros = [];

    public array $cards = [];

    public array $tabelaEscolas = [];

    public array $avaliacoesResumo = [];

    public array $graficoAlunosSemRespostaPorEscola = [];

    public array $distribuicaoAlternativas = [];

    public array $turmasAvaliadas = [];

    public int $turmasAvaliadasTotal = 0;

    public int $turmasAvaliadasPagina = 1;

    public int $turmasAvaliadasPorPagina = 10;

    public array $turmasAvaliadasPorPaginaOptions = [5, 10, 25, 50, 100];

    public array $filtrosAplicados = [];

    public string $ultimaAtualizacao = '';

    public function mount(): void
    {
        $this->filtros = $this->filtrosPadrao();
        $this->atualizarDashboard();
    }

    public function updatedFiltros(mixed $value = null, ?string $key = null): void
    {
        $this->resetarPaginacaoTurmasAvaliadas();

        if ($key === 'avaliacao_id') {
            $this->limparFiltrosDependentes();
        }

        $this->atualizarDashboard();
    }

    public function updatedFiltrosAvaliacaoId(): void
    {
        $this->resetarPaginacaoTurmasAvaliadas();
        $this->limparFiltrosDependentes();
        $this->atualizarDashboard();
    }

    public function limparFiltros(): void
    {
        $this->filtros = $this->filtrosPadrao();
        $this->resetarPaginacaoTurmasAvaliadas();
        $this->atualizarDashboard();
    }

    public function updatedTurmasAvaliadasPorPagina(mixed $value): void
    {
        $this->turmasAvaliadasPorPagina = $this->normalizarTurmasAvaliadasPorPagina($value);
        $this->resetarPaginacaoTurmasAvaliadas();
        $this->atualizarDashboard();
    }

    public function paginaAnteriorTurmasAvaliadas(): void
    {
        $this->turmasAvaliadasPagina = max($this->turmasAvaliadasPagina - 1, 1);
        $this->atualizarDashboard();
    }

    public function proximaPaginaTurmasAvaliadas(): void
    {
        $this->turmasAvaliadasPagina = min($this->turmasAvaliadasPagina + 1, $this->totalPaginasTurmasAvaliadas());
        $this->atualizarDashboard();
    }

    protected function getForms(): array
    {
        return [
            'filtrosForm',
        ];
    }

    public function filtrosForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('avaliacao_id')
                    ->label('Avaliação')
                    ->options(fn (): array => $this->avaliacoesOptions)
                    ->placeholder('Selecione uma avaliacao')
                    ->searchable()
                    ->preload()
                    ->live(),
                Select::make('periodo_id')
                    ->label('Período')
                    ->options(fn (): array => $this->periodosOptions)
                    ->placeholder('Todos')
                    ->searchable()
                    ->preload()
                    ->disabled(fn (): bool => ! $this->avaliacaoSelecionada())
                    ->live(),
                Select::make('tipo_id')
                    ->label('Tipo')
                    ->options(fn (): array => $this->tiposOptions)
                    ->placeholder('Todos')
                    ->searchable()
                    ->preload()
                    ->disabled(fn (): bool => ! $this->avaliacaoSelecionada())
                    ->live(),
                Select::make('status')
                    ->label('Status da avaliação')
                    ->options(fn (): array => $this->statusOptions)
                    ->default('todas')
                    ->disabled(fn (): bool => ! $this->avaliacaoSelecionada())
                    ->live(),
                Select::make('series_ids')
                    ->label('Séries')
                    ->options(fn (): array => $this->seriesOptions)
                    ->multiple()
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->disabled(fn (): bool => ! $this->avaliacaoSelecionada())
                    ->live(),
                Select::make('turnos')
                    ->label('Turnos')
                    ->options(fn (): array => $this->turnosOptions)
                    ->multiple()
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->disabled(fn (): bool => ! $this->avaliacaoSelecionada())
                    ->live(),
                Select::make('componentes_ids')
                    ->label('Componentes')
                    ->options(fn (): array => $this->componentesOptions)
                    ->multiple()
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->disabled(fn (): bool => ! $this->avaliacaoSelecionada())
                    ->live(),
                Select::make('escolas_ids')
                    ->label('Escolas')
                    ->options(fn (): array => $this->escolasOptions)
                    ->multiple()
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->disabled(fn (): bool => ! $this->avaliacaoSelecionada())
                    ->live(),
                Select::make('professores_ids')
                    ->label('Professores')
                    ->options(fn (): array => $this->professoresOptions)
                    ->multiple()
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->disabled(fn (): bool => ! $this->avaliacaoSelecionada())
                    ->live(),
                Select::make('pautas_ids')
                    ->label('Pautas')
                    ->options(fn (): array => $this->pautasOptions)
                    ->multiple()
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->disabled(fn (): bool => ! $this->avaliacaoSelecionada())
                    ->live(),
                Select::make('alternativas_ids')
                    ->label('Alternativas')
                    ->options(fn (): array => $this->alternativasOptions)
                    ->multiple()
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->disabled(fn (): bool => ! $this->avaliacaoSelecionada())
                    ->live(),
            ])
            ->columns(4)
            ->statePath('filtros');
    }

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Relatórios Pedagógicos',
            'title' => 'Dashboard Dinâmico de Avaliações',
            'description' => 'Acompanhe preenchimento, cobertura por escola e distribuição de alternativas com filtros em tempo real. Atualizado em ' . ($this->ultimaAtualizacao ?: '-'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportarPdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->visible(fn (): bool => $this->podeExportar)
                ->action(fn (): mixed => $this->exportarPdf()),

            Action::make('exportarXlsx')
                ->label('Exportar XLSX')
                ->icon('heroicon-o-table-cells')
                ->color('primary')
                ->visible(fn (): bool => $this->podeExportar)
                ->action(fn (): mixed => $this->exportarXlsx()),
        ];
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->can('Listar Avaliações') || $user->can('Listar Avaliacoes');
    }

    public function avaliacaoSelecionada(): bool
    {
        return (int) ($this->filtros['avaliacao_id'] ?? 0) > 0;
    }

    public function getPodeExportarProperty(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->hasPermissionLike('exportar relatorios')
            || $user->hasPermissionLike('exportar avaliacoes');
    }

    public function getStatusOptionsProperty(): array
    {
        $filtros = $this->filtrosIgnorandoCampo('status');
        $opcoesBase = ['todas' => 'Todos'];

        if (! $this->avaliacaoIdDosFiltros($filtros)) {
            return $opcoesBase;
        }

        $avaliacaoIds = $this->obterIdsAvaliacoesFiltradas(filtros: $filtros);

        if ($avaliacaoIds === []) {
            return $opcoesBase;
        }

        $statusDisponiveis = Avaliacao::query()
            ->whereIn('id', $avaliacaoIds)
            ->pluck('status')
            ->map(fn ($status): string => (string) $status)
            ->unique()
            ->values()
            ->all();

        foreach (Avaliacao::statusOptions() as $status => $label) {
            if (in_array($status, $statusDisponiveis, true)) {
                $opcoesBase[$status] = $label;
            }
        }

        return $opcoesBase;
    }

    public function getTurnosOptionsProperty(): array
    {
        $labels = [
            'manha' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            'integral' => 'Integral',
        ];

        $filtros = $this->filtrosIgnorandoCampo('turnos');

        if (! $this->avaliacaoIdDosFiltros($filtros)) {
            return [];
        }

        $avaliacaoIds = $this->obterIdsAvaliacoesFiltradas(filtros: $filtros);

        if ($avaliacaoIds === []) {
            return [];
        }

        $turnosDisponiveis = (clone $this->baseTurmasContextoQuery($avaliacaoIds, $filtros))
            ->whereNotNull('t.turno')
            ->distinct()
            ->pluck('t.turno')
            ->map(fn ($turno): string => (string) $turno)
            ->values()
            ->all();

        $opcoes = [];

        foreach ($labels as $turno => $label) {
            if (in_array($turno, $turnosDisponiveis, true)) {
                $opcoes[$turno] = $label;
            }
        }

        return $opcoes;
    }

    public function getAvaliacoesOptionsProperty(): array
    {
        return Avaliacao::query()
            ->orderByDesc('data_inicio')
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    public function getPeriodosOptionsProperty(): array
    {
        $filtros = $this->filtrosIgnorandoCampo('periodo_id');

        if (! $this->avaliacaoIdDosFiltros($filtros)) {
            return [];
        }

        $avaliacaoIds = $this->obterIdsAvaliacoesFiltradas(filtros: $filtros);

        if ($avaliacaoIds === []) {
            return [];
        }

        $periodosIds = Avaliacao::query()
            ->whereIn('id', $avaliacaoIds)
            ->whereNotNull('periodo_avaliacao_id')
            ->pluck('periodo_avaliacao_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($periodosIds === []) {
            return [];
        }

        return PeriodoAvaliacao::query()
            ->whereIn('id', $periodosIds)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    public function getTiposOptionsProperty(): array
    {
        $filtros = $this->filtrosIgnorandoCampo('tipo_id');

        if (! $this->avaliacaoIdDosFiltros($filtros)) {
            return [];
        }

        $avaliacaoIds = $this->obterIdsAvaliacoesFiltradas(filtros: $filtros);

        if ($avaliacaoIds === []) {
            return [];
        }

        $tiposIds = Avaliacao::query()
            ->whereIn('id', $avaliacaoIds)
            ->whereNotNull('tipo_avaliacao_id')
            ->pluck('tipo_avaliacao_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($tiposIds === []) {
            return [];
        }

        return TipoAvaliacao::query()
            ->whereIn('id', $tiposIds)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    public function getSeriesOptionsProperty(): array
    {
        $filtros = $this->filtrosIgnorandoCampo('series_ids');

        if (! $this->avaliacaoIdDosFiltros($filtros)) {
            return [];
        }

        $avaliacaoIds = $this->obterIdsAvaliacoesFiltradas(filtros: $filtros);

        if ($avaliacaoIds === []) {
            return [];
        }

        $seriesIds = (clone $this->baseTurmasContextoQuery($avaliacaoIds, $filtros))
            ->whereNotNull('t.id_serie')
            ->distinct()
            ->pluck('t.id_serie')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($seriesIds === []) {
            return [];
        }

        return Serie::query()
            ->whereIn('id', $seriesIds)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    public function getComponentesOptionsProperty(): array
    {
        $filtros = $this->filtrosIgnorandoCampo('componentes_ids');

        if (! $this->avaliacaoIdDosFiltros($filtros)) {
            return [];
        }

        $avaliacaoIds = $this->obterIdsAvaliacoesFiltradas(filtros: $filtros);

        if ($avaliacaoIds === []) {
            return [];
        }

        $componentesIds = collect();

        $componentesEscopo = DB::table('avaliacao_componente')
            ->whereIn('avaliacao_id', $avaliacaoIds)
            ->pluck('componente_curricular_id')
            ->map(fn ($id): int => (int) $id);

        $componentesPautas = DB::table('avaliacao_pauta as ap')
            ->join('pautas as p', 'p.id', '=', 'ap.pauta_id')
            ->whereIn('ap.avaliacao_id', $avaliacaoIds)
            ->whereNotNull('p.componente_curricular_id');

        if ($filtros['series_ids'] !== []) {
            $componentesPautas->where(function (QueryBuilder $query) use ($filtros): void {
                $query->whereIn('p.serie_id', $filtros['series_ids'])
                    ->orWhereNull('p.serie_id');
            });
        }

        if ($filtros['pautas_ids'] !== []) {
            $componentesPautas->whereIn('p.id', $filtros['pautas_ids']);
        }

        $componentesTurmas = (clone $this->baseTurmasContextoQuery($avaliacaoIds, $filtros, forcarJoinComponenteProfessor: true))
            ->whereNotNull('tcp.componente_curricular_id')
            ->distinct()
            ->pluck('tcp.componente_curricular_id')
            ->map(fn ($id): int => (int) $id);

        $componentesIds = $componentesIds
            ->merge($componentesEscopo)
            ->merge($componentesPautas->pluck('p.componente_curricular_id')->map(fn ($id): int => (int) $id))
            ->merge($componentesTurmas)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($componentesIds === []) {
            return [];
        }

        return ComponenteCurricular::query()
            ->whereIn('id', $componentesIds)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    public function getEscolasOptionsProperty(): array
    {
        $filtros = $this->filtrosIgnorandoCampo('escolas_ids');

        if (! $this->avaliacaoIdDosFiltros($filtros)) {
            return [];
        }

        $avaliacaoIds = $this->obterIdsAvaliacoesFiltradas(filtros: $filtros);

        if ($avaliacaoIds === []) {
            return [];
        }

        $escolasIds = (clone $this->baseTurmasContextoQuery($avaliacaoIds, $filtros))
            ->whereNotNull('t.id_escola')
            ->distinct()
            ->pluck('t.id_escola')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($escolasIds === []) {
            return [];
        }

        return Escola::query()
            ->where('ativo', true)
            ->whereIn('id', $escolasIds)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    public function getProfessoresOptionsProperty(): array
    {
        $filtros = $this->filtrosIgnorandoCampo('professores_ids');

        if (! $this->avaliacaoIdDosFiltros($filtros)) {
            return [];
        }

        $avaliacaoIds = $this->obterIdsAvaliacoesFiltradas(filtros: $filtros);

        if ($avaliacaoIds === []) {
            return [];
        }

        $query = (clone $this->baseTurmasContextoQuery($avaliacaoIds, $filtros, forcarJoinComponenteProfessor: true))
            ->join('professores as pr', 'pr.id', '=', 'tcp.professor_id')
            ->whereNotNull('tcp.professor_id');

        if ($filtros['alternativas_ids'] !== []) {
            $query->whereExists(function (QueryBuilder $subQuery) use ($filtros): void {
                $subQuery
                    ->from('avaliacao_respostas as ar')
                    ->whereColumn('ar.avaliacao_id', 'at.avaliacao_id')
                    ->whereColumn('ar.turma_id', 't.id')
                    ->whereColumn('ar.professor_id', 'tcp.professor_id')
                    ->whereIn('ar.alternativa_id', $filtros['alternativas_ids']);

                if ($filtros['pautas_ids'] !== []) {
                    $subQuery->whereIn('ar.pauta_id', $filtros['pautas_ids']);
                }
            });
        }

        $professoresIds = $query
            ->distinct()
            ->pluck('pr.id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($professoresIds === []) {
            return [];
        }

        return Professor::query()
            ->whereIn('id', $professoresIds)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    public function getPautasOptionsProperty(): array
    {
        $filtros = $this->filtrosIgnorandoCampo('pautas_ids');

        if (! $this->avaliacaoIdDosFiltros($filtros)) {
            return [];
        }

        $avaliacaoIds = $this->obterIdsAvaliacoesFiltradas(filtros: $filtros);

        if ($avaliacaoIds === []) {
            return [];
        }

        $query = DB::table('avaliacao_pauta as ap')
            ->join('pautas as p', 'p.id', '=', 'ap.pauta_id')
            ->whereIn('ap.avaliacao_id', $avaliacaoIds)
            ->where('p.status', true);

        if ($filtros['componentes_ids'] !== []) {
            $query->whereIn('p.componente_curricular_id', $filtros['componentes_ids']);
        }

        if ($filtros['series_ids'] !== []) {
            $query->where(function (QueryBuilder $subQuery) use ($filtros): void {
                $subQuery->whereIn('p.serie_id', $filtros['series_ids'])
                    ->orWhereNull('p.serie_id');
            });
        }

        if ($filtros['professores_ids'] !== [] || $filtros['alternativas_ids'] !== []) {
            $query->whereExists(function (QueryBuilder $subQuery) use ($filtros): void {
                $subQuery
                    ->from('avaliacao_respostas as ar')
                    ->join('turmas as t2', 't2.id', '=', 'ar.turma_id')
                    ->whereColumn('ar.avaliacao_id', 'ap.avaliacao_id')
                    ->whereColumn('ar.pauta_id', 'p.id');

                if ($filtros['professores_ids'] !== []) {
                    $subQuery->whereIn('ar.professor_id', $filtros['professores_ids']);
                }

                if ($filtros['alternativas_ids'] !== []) {
                    $subQuery->whereIn('ar.alternativa_id', $filtros['alternativas_ids']);
                }

                if ($filtros['turnos'] !== []) {
                    $subQuery->whereIn('t2.turno', $filtros['turnos']);
                }

                if ($filtros['series_ids'] !== []) {
                    $subQuery->whereIn('t2.id_serie', $filtros['series_ids']);
                }

                if ($filtros['escolas_ids'] !== []) {
                    $subQuery->whereIn('t2.id_escola', $filtros['escolas_ids']);
                }
            });
        }

        $pautasIds = $query
            ->distinct()
            ->pluck('p.id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($pautasIds === []) {
            return [];
        }

        return Pauta::query()
            ->whereIn('id', $pautasIds)
            ->orderBy('texto')
            ->pluck('texto', 'id')
            ->toArray();
    }

    public function getAlternativasOptionsProperty(): array
    {
        $filtros = $this->filtrosIgnorandoCampo('alternativas_ids');

        if (! $this->avaliacaoIdDosFiltros($filtros)) {
            return [];
        }

        $avaliacaoIds = $this->obterIdsAvaliacoesFiltradas(filtros: $filtros);

        if ($avaliacaoIds === []) {
            return [];
        }

        $alternativasIds = collect();

        $tiposIds = Avaliacao::query()
            ->whereIn('id', $avaliacaoIds)
            ->whereNotNull('tipo_avaliacao_id')
            ->pluck('tipo_avaliacao_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($tiposIds !== []) {
            $alternativasIds = $alternativasIds->merge(
                Alternativa::query()
                    ->where('status', true)
                    ->whereIn('tipo_avaliacao_id', $tiposIds)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
            );
        }

        $queryOverrides = DB::table('avaliacao_pauta_alternativa')
            ->whereIn('avaliacao_id', $avaliacaoIds);

        if ($filtros['pautas_ids'] !== []) {
            $queryOverrides->whereIn('pauta_id', $filtros['pautas_ids']);
        }

        $alternativasIds = $alternativasIds->merge(
            $queryOverrides->pluck('alternativa_id')->map(fn ($id): int => (int) $id)
        );

        if ($filtros['pautas_ids'] !== []) {
            $alternativasIds = $alternativasIds->merge(
                DB::table('alternativa_pauta')
                    ->whereIn('pauta_id', $filtros['pautas_ids'])
                    ->pluck('alternativa_id')
                    ->map(fn ($id): int => (int) $id)
            );
        }

        if (
            $filtros['professores_ids'] !== []
            || $filtros['pautas_ids'] !== []
            || $filtros['series_ids'] !== []
            || $filtros['escolas_ids'] !== []
            || $filtros['turnos'] !== []
            || $filtros['componentes_ids'] !== []
        ) {
            $alternativasIds = $alternativasIds->merge(
                (clone $this->baseRespostasQuery($avaliacaoIds, ignorarAlternativas: true, filtros: $filtros))
                    ->distinct()
                    ->pluck('ar.alternativa_id')
                    ->map(fn ($id): int => (int) $id)
            );
        }

        $alternativasIds = $alternativasIds
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($alternativasIds === []) {
            return [];
        }

        return Alternativa::query()
            ->where('status', true)
            ->whereIn('id', $alternativasIds)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    private function avaliacaoIdDosFiltros(array $filtros): ?int
    {
        $avaliacaoId = (int) ($filtros['avaliacao_id'] ?? 0);

        return $avaliacaoId > 0 ? $avaliacaoId : null;
    }

    private function limparFiltrosDependentes(): void
    {
        $avaliacaoId = $this->normalizarId($this->filtros['avaliacao_id'] ?? null);

        $this->filtros = [
            ...$this->filtrosPadrao(),
            'avaliacao_id' => $avaliacaoId,
        ];
    }

    private function resetarPaginacaoTurmasAvaliadas(): void
    {
        $this->turmasAvaliadasPagina = 1;
    }

    private function normalizarTurmasAvaliadasPorPagina(mixed $value): int
    {
        $porPagina = (int) $value;

        return in_array($porPagina, $this->turmasAvaliadasPorPaginaOptions, true)
            ? $porPagina
            : 10;
    }

    private function normalizarPaginaTurmasAvaliadas(?int $total = null): void
    {
        $this->turmasAvaliadasPorPagina = $this->normalizarTurmasAvaliadasPorPagina($this->turmasAvaliadasPorPagina);
        $this->turmasAvaliadasPagina = min(
            max((int) $this->turmasAvaliadasPagina, 1),
            $this->totalPaginasTurmasAvaliadas($total)
        );
    }

    private function totalPaginasTurmasAvaliadas(?int $total = null): int
    {
        $total = $total ?? $this->turmasAvaliadasTotal;
        $porPagina = max($this->normalizarTurmasAvaliadasPorPagina($this->turmasAvaliadasPorPagina), 1);

        return max((int) ceil($total / $porPagina), 1);
    }

    private function filtrosIgnorandoCampo(string $campo): array
    {
        $filtros = $this->filtros;

        if (in_array($campo, ['series_ids', 'turnos', 'componentes_ids', 'escolas_ids', 'professores_ids', 'pautas_ids', 'alternativas_ids'], true)) {
            $filtros[$campo] = [];

            return $filtros;
        }

        if ($campo === 'status') {
            $filtros[$campo] = 'todas';

            return $filtros;
        }

        $filtros[$campo] = null;

        return $filtros;
    }

    private function componentesIdsDasPautasSelecionadas(array $filtros): array
    {
        if (($filtros['pautas_ids'] ?? []) === []) {
            return [];
        }

        return Pauta::query()
            ->whereIn('id', $filtros['pautas_ids'])
            ->whereNotNull('componente_curricular_id')
            ->pluck('componente_curricular_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function baseTurmasContextoQuery(
        array $avaliacaoIds,
        array $filtros,
        bool $forcarJoinComponenteProfessor = false
    ): QueryBuilder {
        $query = DB::table('avaliacao_turma as at')
            ->join('turmas as t', 't.id', '=', 'at.turma_id')
            ->whereIn('at.avaliacao_id', $avaliacaoIds);

        if (($filtros['series_ids'] ?? []) !== []) {
            $query->whereIn('t.id_serie', $filtros['series_ids']);
        }

        if (($filtros['turnos'] ?? []) !== []) {
            $query->whereIn('t.turno', $filtros['turnos']);
        }

        if (($filtros['escolas_ids'] ?? []) !== []) {
            $query->whereIn('t.id_escola', $filtros['escolas_ids']);
        }

        $componentesDasPautas = $this->componentesIdsDasPautasSelecionadas($filtros);

        $deveJoinComponenteProfessor = $forcarJoinComponenteProfessor
            || ($filtros['componentes_ids'] ?? []) !== []
            || ($filtros['professores_ids'] ?? []) !== []
            || $componentesDasPautas !== [];

        if ($deveJoinComponenteProfessor) {
            $query->join('turma_componente_professor as tcp', 'tcp.turma_id', '=', 't.id');

            if (($filtros['componentes_ids'] ?? []) !== []) {
                $query->whereIn('tcp.componente_curricular_id', $filtros['componentes_ids']);
            }

            if (($filtros['professores_ids'] ?? []) !== []) {
                $query->whereIn('tcp.professor_id', $filtros['professores_ids']);
            }

            if ($componentesDasPautas !== []) {
                $query->whereIn('tcp.componente_curricular_id', $componentesDasPautas);
            }
        }

        if (($filtros['alternativas_ids'] ?? []) !== []) {
            $query->whereExists(function (QueryBuilder $subQuery) use ($filtros): void {
                $subQuery
                    ->from('avaliacao_respostas as ar')
                    ->whereColumn('ar.avaliacao_id', 'at.avaliacao_id')
                    ->whereColumn('ar.turma_id', 't.id')
                    ->whereIn('ar.alternativa_id', $filtros['alternativas_ids']);

                if (($filtros['pautas_ids'] ?? []) !== []) {
                    $subQuery->whereIn('ar.pauta_id', $filtros['pautas_ids']);
                }

                if (($filtros['professores_ids'] ?? []) !== []) {
                    $subQuery->whereIn('ar.professor_id', $filtros['professores_ids']);
                }
            });
        }

        return $query;
    }

    public function exportarPdf()
    {
        if (! $this->podeExportar) {
            Notification::make()
                ->title('Você não tem permissão para exportar relatórios.')
                ->warning()
                ->send();

            return null;
        }

        if (! $this->avaliacaoSelecionada()) {
            Notification::make()
                ->title('Selecione uma avaliacao antes de exportar.')
                ->warning()
                ->send();

            return null;
        }

        $dados = $this->montarDashboardData();

        return app(RelatorioPdfRenderer::class)->download(
            'relatorios.Avaliacoes.dashboard-avaliacoes',
            [
                'cards' => $dados['cards'],
                'escolas' => $dados['tabela_escolas'],
                'avaliacoes' => $dados['avaliacoes_resumo'],
                'alternativas' => $dados['distribuicao_alternativas'],
                'reportTitle' => 'Dashboard de Avaliações',
                'reportSubtitle' => 'Resumo analítico por escopo e preenchimento',
                'reportFilters' => $this->filtrosAplicadosFormatados(),
                'usuarioExportacao' => Auth::user(),
                'dataExportacao' => now(),
                'orientation' => 'landscape',
            ],
            'dashboard-avaliacoes-' . now()->format('Y-m-d_H-i') . '.pdf'
        );
    }

    public function exportarXlsx()
    {
        if (! $this->podeExportar) {
            Notification::make()
                ->title('Você não tem permissão para exportar relatórios.')
                ->warning()
                ->send();

            return null;
        }

        if (! $this->avaliacaoSelecionada()) {
            Notification::make()
                ->title('Selecione uma avaliacao antes de exportar.')
                ->warning()
                ->send();

            return null;
        }

        $dados = $this->montarDashboardData();

        $spreadsheet = new Spreadsheet();

        $resumoSheet = $spreadsheet->getActiveSheet();
        $resumoSheet->setTitle('Resumo');
        $linha = $this->preencherCabecalhoSheet(
            $resumoSheet,
            'Dashboard de Avaliações',
            $this->filtrosAplicadosFormatados()
        );

        $resumoSheet->setCellValue("A{$linha}", 'Indicador');
        $resumoSheet->setCellValue("B{$linha}", 'Valor');
        $this->estilizarHeaderLinha($resumoSheet, "A{$linha}:B{$linha}");
        $linha++;

        $indicadores = [
            '% de alunos sem resposta em pautas' => ($dados['cards']['percentual_alunos_sem_resposta_pautas'] ?? 0) . '%',
            'Preenchimentos esperados' => $dados['cards']['preenchimentos_esperados'] ?? 0,
            'Preenchimentos respondidos' => $dados['cards']['preenchimentos_respondidos'] ?? 0,
            'Preenchimentos pendentes' => $dados['cards']['preenchimentos_pendentes'] ?? 0,
            '% de turmas preenchidas' => ($dados['cards']['percentual_turmas_preenchidas'] ?? 0) . '%',
            'Turmas preenchidas' => ($dados['cards']['turmas_preenchidas'] ?? 0) . ' de ' . ($dados['cards']['turmas_esperadas'] ?? 0),
            '% de escolas preenchidas' => ($dados['cards']['percentual_escolas_preenchidas'] ?? 0) . '%',
            'Escolas preenchidas' => ($dados['cards']['escolas_preenchidas'] ?? 0) . ' de ' . ($dados['cards']['total_escolas'] ?? 0),
            '% preenchimento manha' => ($dados['cards']['percentual_turno_manha'] ?? 0) . '%',
            'Alunos sem resposta manha' => ($dados['cards']['turno_manha_alunos_pendentes'] ?? 0) . ' de ' . ($dados['cards']['turno_manha_alunos_total'] ?? 0),
            '% preenchimento tarde' => ($dados['cards']['percentual_turno_tarde'] ?? 0) . '%',
            'Alunos sem resposta tarde' => ($dados['cards']['turno_tarde_alunos_pendentes'] ?? 0) . ' de ' . ($dados['cards']['turno_tarde_alunos_total'] ?? 0),
        ];

        foreach ($indicadores as $label => $valor) {
            $resumoSheet->setCellValue("A{$linha}", $label);
            $resumoSheet->setCellValue("B{$linha}", $valor);
            $linha++;
        }

        $this->estilizarCorpoTabela($resumoSheet, 'A' . ($linha - count($indicadores)) . ':B' . ($linha - 1));
        $this->autoSizeColumns($resumoSheet, 2);

        $escolasSheet = $spreadsheet->createSheet();
        $escolasSheet->setTitle('Escolas');
        $this->preencherTabelaSheet(
            $escolasSheet,
            'Progresso por Escola',
            [
                'Escola',
                'Preenchimentos esperados',
                'Preenchimentos respondidos',
                'Preenchimentos pendentes',
                '% sem resposta',
                '% preenchimento',
                'Turmas esperadas',
                'Turmas com resposta',
            ],
            collect($dados['tabela_escolas'])->map(fn (array $item): array => [
                $item['nome'],
                $item['preenchimentos_esperados'],
                $item['preenchimentos_respondidos'],
                $item['preenchimentos_pendentes'],
                $item['percentual_pendentes'] . '%',
                $item['percentual_preenchimento'] . '%',
                $item['turmas_esperadas'],
                $item['turmas_com_resposta'],
            ])
        );

        $avaliacoesSheet = $spreadsheet->createSheet();
        $avaliacoesSheet->setTitle('Avaliações');
        $this->preencherTabelaSheet(
            $avaliacoesSheet,
            'Resumo por Avaliação',
            [
                'Avaliação',
                'Tipo',
                'Período',
                'Status',
                'Escolas no escopo',
                'Turmas no escopo',
                'Preenchimentos esperados',
                'Preenchimentos respondidos',
                'Preenchimentos pendentes',
                '% sem resposta',
                'Início',
                'Fim',
            ],
            collect($dados['avaliacoes_resumo'])->map(fn (array $item): array => [
                $item['nome'],
                $item['tipo'],
                $item['periodo'],
                $item['status_label'],
                $item['escolas_esperadas'],
                $item['turmas_esperadas'],
                $item['preenchimentos_esperados'],
                $item['preenchimentos_respondidos'],
                $item['preenchimentos_pendentes'],
                $item['percentual_pendentes'] . '%',
                $item['data_inicio'],
                $item['data_fim'],
            ])
        );

        $alternativasSheet = $spreadsheet->createSheet();
        $alternativasSheet->setTitle('Alternativas');
        $this->preencherTabelaSheet(
            $alternativasSheet,
            $dados['distribuicao_alternativas']['titulo'] ?? 'Distribuição de Alternativas',
            [
                'Alternativa',
                'Alunos marcados',
                '% dos alunos',
            ],
            collect($dados['distribuicao_alternativas']['itens'] ?? [])->map(fn (array $item): array => [
                $item['nome'],
                $item['total'],
                $item['percentual'] . '%',
            ])
        );

        $spreadsheet->setActiveSheetIndex(0);

        return $this->downloadSpreadsheet(
            $spreadsheet,
            'dashboard-avaliacoes-' . now()->format('Y-m-d_H-i') . '.xlsx'
        );
    }

    private function atualizarDashboard(): void
    {
        $dados = $this->montarDashboardData();

        $this->cards = $dados['cards'];
        $this->tabelaEscolas = $dados['tabela_escolas'];
        $this->avaliacoesResumo = $dados['avaliacoes_resumo'];
        $this->graficoAlunosSemRespostaPorEscola = $dados['grafico_alunos_sem_resposta_por_escola'];
        $this->distribuicaoAlternativas = $dados['distribuicao_alternativas'];
        $this->turmasAvaliadas = $dados['turmas_avaliadas'];
        $this->turmasAvaliadasTotal = $dados['turmas_avaliadas_total'];
        $this->filtrosAplicados = $this->filtrosAplicadosFormatados();
        $this->ultimaAtualizacao = $this->avaliacaoSelecionada()
            ? now()->format('d/m/Y H:i:s')
            : '';
    }

    /**
     * @return array{
     *     cards: array<string, int|float>,
     *     tabela_escolas: array<int, array<string, int|float|string|bool>>,
     *     avaliacoes_resumo: array<int, array<string, int|float|string>>,
     *     grafico_alunos_sem_resposta_por_escola: array<int, array<string, int|float|string>>,
     *     distribuicao_alternativas: array<string, mixed>,
     *     turmas_avaliadas: array<int, array<string, int|string>>,
     *     turmas_avaliadas_total: int
     * }
     */
    private function montarDashboardData(): array
    {
        $this->normalizarFiltros();

        if (! $this->avaliacaoSelecionada()) {
            return $this->dashboardVazio();
        }

        $avaliacaoIds = $this->obterIdsAvaliacoesFiltradas();

        $tabelaEscolas = $this->montarTabelaEscolas($avaliacaoIds);
        $totaisPreenchimento = $this->calcularTotaisPreenchimento($avaliacaoIds, $tabelaEscolas);
        $turnos = $this->calcularPreenchimentoPorTurno($avaliacaoIds);
        $turmasAvaliadas = $this->montarTurmasAvaliadas($avaliacaoIds);

        return [
            'cards' => [
                ...$totaisPreenchimento,
                'percentual_turno_manha' => $turnos['manha']['percentual_preenchimento'] ?? 0.0,
                'turno_manha_respondidas' => $turnos['manha']['respondidas'] ?? 0,
                'turno_manha_esperadas' => $turnos['manha']['esperadas'] ?? 0,
                'turno_manha_alunos_pendentes' => $turnos['manha']['alunos_pendentes'] ?? 0,
                'turno_manha_alunos_total' => $turnos['manha']['alunos_total'] ?? 0,
                'percentual_turno_tarde' => $turnos['tarde']['percentual_preenchimento'] ?? 0.0,
                'turno_tarde_respondidas' => $turnos['tarde']['respondidas'] ?? 0,
                'turno_tarde_esperadas' => $turnos['tarde']['esperadas'] ?? 0,
                'turno_tarde_alunos_pendentes' => $turnos['tarde']['alunos_pendentes'] ?? 0,
                'turno_tarde_alunos_total' => $turnos['tarde']['alunos_total'] ?? 0,
            ],
            'tabela_escolas' => $tabelaEscolas,
            'avaliacoes_resumo' => $this->montarResumoAvaliacoes($avaliacaoIds),
            'grafico_alunos_sem_resposta_por_escola' => $this->montarGraficoAlunosSemRespostaPorEscola($tabelaEscolas),
            'distribuicao_alternativas' => $this->montarDistribuicaoAlternativas($avaliacaoIds),
            'turmas_avaliadas' => $turmasAvaliadas['itens'],
            'turmas_avaliadas_total' => $turmasAvaliadas['total'],
        ];
    }

    private function dashboardVazio(): array
    {
        return [
            'cards' => [
                'preenchimentos_esperados' => 0,
                'preenchimentos_respondidos' => 0,
                'preenchimentos_pendentes' => 0,
                'percentual_alunos_sem_resposta_pautas' => 0.0,
                'percentual_turmas_preenchidas' => 0.0,
                'turmas_esperadas' => 0,
                'turmas_preenchidas' => 0,
                'percentual_escolas_preenchidas' => 0.0,
                'total_escolas' => 0,
                'escolas_preenchidas' => 0,
                'escolas_nao_preenchidas' => 0,
                'percentual_turno_manha' => 0.0,
                'turno_manha_respondidas' => 0,
                'turno_manha_esperadas' => 0,
                'turno_manha_alunos_pendentes' => 0,
                'turno_manha_alunos_total' => 0,
                'percentual_turno_tarde' => 0.0,
                'turno_tarde_respondidas' => 0,
                'turno_tarde_esperadas' => 0,
                'turno_tarde_alunos_pendentes' => 0,
                'turno_tarde_alunos_total' => 0,
            ],
            'tabela_escolas' => [],
            'avaliacoes_resumo' => [],
            'grafico_alunos_sem_resposta_por_escola' => [],
            'distribuicao_alternativas' => [
                'titulo' => 'Top alternativas no escopo',
                'subtitulo' => 'Selecione uma avaliacao para carregar os indicadores.',
                'total_respostas' => 0,
                'total_esperado' => 0,
                'total_alunos' => 0,
                'itens' => [],
            ],
            'turmas_avaliadas' => [],
            'turmas_avaliadas_total' => 0,
        ];
    }

    /**
     * @return array<int>
     */
    private function obterIdsAvaliacoesFiltradas(bool $forcarAtivas = false, ?array $filtros = null): array
    {
        $filtrosAtivos = $filtros ?? $this->filtros;

        $query = Avaliacao::query()
            ->select('avaliacoes.id')
            ->distinct();

        $this->aplicarFiltrosDiretosAvaliacao($query, $forcarAtivas, $filtrosAtivos);

        if ($this->temFiltrosDeResposta($filtrosAtivos)) {
            $query->whereHas('respostas', function (EloquentBuilder $respostaQuery) use ($filtrosAtivos): void {
                $this->aplicarFiltrosRespostaEloquent($respostaQuery, filtros: $filtrosAtivos);
            });
        }

        return $query
            ->pluck('avaliacoes.id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    private function aplicarFiltrosDiretosAvaliacao(EloquentBuilder $query, bool $forcarAtivas = false, ?array $filtros = null): void
    {
        $filtrosAtivos = $filtros ?? $this->filtros;

        $avaliacaoId = $filtrosAtivos['avaliacao_id'];
        $periodoId = $filtrosAtivos['periodo_id'];
        $tipoId = $filtrosAtivos['tipo_id'];
        $status = $forcarAtivas ? Avaliacao::STATUS_ATIVA : $filtrosAtivos['status'];
        $seriesIds = $filtrosAtivos['series_ids'];
        $componentesIds = $filtrosAtivos['componentes_ids'];
        $escolasIds = $filtrosAtivos['escolas_ids'];
        $pautasIds = $filtrosAtivos['pautas_ids'];
        $turnos = $filtrosAtivos['turnos'];

        if ($avaliacaoId) {
            $query->whereKey($avaliacaoId);
        }

        if ($periodoId) {
            $query->where('periodo_avaliacao_id', $periodoId);
        }

        if ($tipoId) {
            $query->where('tipo_avaliacao_id', $tipoId);
        }

        if ($status !== 'todas') {
            $query->where('status', $status);
        }

        if ($seriesIds !== []) {
            $query->whereHas('series', fn (EloquentBuilder $serieQuery) => $serieQuery->whereIn('series.id', $seriesIds));
        }

        if ($componentesIds !== []) {
            $query->whereHas(
                'componentes',
                fn (EloquentBuilder $componenteQuery) => $componenteQuery->whereIn('componentes_curriculares.id', $componentesIds)
            );
        }

        if ($escolasIds !== []) {
            $query->whereHas('escolas', fn (EloquentBuilder $escolaQuery) => $escolaQuery->whereIn('escolas.id', $escolasIds));
        }

        if ($pautasIds !== []) {
            $query->whereHas('pautas', fn (EloquentBuilder $pautaQuery) => $pautaQuery->whereIn('pautas.id', $pautasIds));
        }

        if ($turnos !== []) {
            $query->whereHas('turmas', fn (EloquentBuilder $turmaQuery) => $turmaQuery->whereIn('turno', $turnos));
        }
    }

    private function aplicarFiltrosRespostaEloquent(
        EloquentBuilder $query,
        bool $ignorarAlternativas = false,
        ?array $filtros = null
    ): void
    {
        $filtrosAtivos = $filtros ?? $this->filtros;

        $professoresIds = $filtrosAtivos['professores_ids'];
        $pautasIds = $filtrosAtivos['pautas_ids'];
        $alternativasIds = $filtrosAtivos['alternativas_ids'];
        $turnos = $filtrosAtivos['turnos'];
        $seriesIds = $filtrosAtivos['series_ids'];
        $escolasIds = $filtrosAtivos['escolas_ids'];
        $componentesIds = $filtrosAtivos['componentes_ids'];

        if ($professoresIds !== []) {
            $query->whereIn('professor_id', $professoresIds);
        }

        if ($pautasIds !== []) {
            $query->whereIn('pauta_id', $pautasIds);
        }

        if (! $ignorarAlternativas && $alternativasIds !== []) {
            $query->whereIn('alternativa_id', $alternativasIds);
        }

        if ($turnos !== []) {
            $query->whereHas('turma', fn (EloquentBuilder $turmaQuery) => $turmaQuery->whereIn('turno', $turnos));
        }

        if ($seriesIds !== []) {
            $query->whereHas('turma', fn (EloquentBuilder $turmaQuery) => $turmaQuery->whereIn('id_serie', $seriesIds));
        }

        if ($escolasIds !== []) {
            $query->whereHas('turma', fn (EloquentBuilder $turmaQuery) => $turmaQuery->whereIn('id_escola', $escolasIds));
        }

        if ($componentesIds !== []) {
            $query->whereHas(
                'pauta',
                fn (EloquentBuilder $pautaQuery) => $pautaQuery->whereIn('componente_curricular_id', $componentesIds)
            );
        }
    }

    private function temFiltrosDeResposta(?array $filtros = null): bool
    {
        $filtrosAtivos = $filtros ?? $this->filtros;

        if ($this->avaliacaoIdDosFiltros($filtrosAtivos)) {
            return false;
        }

        return $filtrosAtivos['professores_ids'] !== []
            || $filtrosAtivos['alternativas_ids'] !== [];
    }

    private function baseRespostasQuery(
        array $avaliacaoIds,
        bool $ignorarAlternativas = false,
        ?array $filtros = null
    ): QueryBuilder
    {
        $filtrosAtivos = $filtros ?? $this->filtros;

        if ($avaliacaoIds === []) {
            return DB::table('avaliacao_respostas as ar')->whereRaw('1 = 0');
        }

        $query = DB::table('avaliacao_respostas as ar')
            ->join('turmas as t', 't.id', '=', 'ar.turma_id')
            ->join('alunos as aln', 'aln.id', '=', 'ar.aluno_id')
            ->join('pautas as p', 'p.id', '=', 'ar.pauta_id')
            ->join('alternativas as alt', 'alt.id', '=', 'ar.alternativa_id')
            ->whereIn('ar.avaliacao_id', $avaliacaoIds)
            ->where('aln.status', '!=', Aluno::STATUS_PENDENTE)
            ->where('p.status', true)
            ->where(function (QueryBuilder $query): void {
                $query->whereNull('p.serie_id')
                    ->orWhereColumn('p.serie_id', 't.id_serie');
            });

        if ($filtrosAtivos['series_ids'] !== []) {
            $query->whereIn('t.id_serie', $filtrosAtivos['series_ids']);
        }

        if ($filtrosAtivos['turnos'] !== []) {
            $query->whereIn('t.turno', $filtrosAtivos['turnos']);
        }

        if ($filtrosAtivos['componentes_ids'] !== []) {
            $query->whereIn('p.componente_curricular_id', $filtrosAtivos['componentes_ids']);
        }

        if ($filtrosAtivos['escolas_ids'] !== []) {
            $query->whereIn('t.id_escola', $filtrosAtivos['escolas_ids']);
        }

        if ($filtrosAtivos['professores_ids'] !== []) {
            $query->whereIn('ar.professor_id', $filtrosAtivos['professores_ids']);
        }

        if ($filtrosAtivos['pautas_ids'] !== []) {
            $query->whereIn('ar.pauta_id', $filtrosAtivos['pautas_ids']);
        }

        if (! $ignorarAlternativas && $filtrosAtivos['alternativas_ids'] !== []) {
            $query->whereIn('ar.alternativa_id', $filtrosAtivos['alternativas_ids']);
        }

        return $query;
    }

    private function basePreenchimentosEsperadosQuery(array $avaliacaoIds, ?array $filtros = null): QueryBuilder
    {
        $filtrosAtivos = $filtros ?? $this->filtros;

        if ($avaliacaoIds === []) {
            return DB::table('avaliacao_turma as at')->whereRaw('1 = 0');
        }

        $query = DB::table('avaliacao_turma as at')
            ->join('turmas as t', 't.id', '=', 'at.turma_id')
            ->join('alunos as aln', 'aln.id_turma', '=', 't.id')
            ->join('avaliacao_pauta as ap', 'ap.avaliacao_id', '=', 'at.avaliacao_id')
            ->join('pautas as p', 'p.id', '=', 'ap.pauta_id')
            ->whereIn('at.avaliacao_id', $avaliacaoIds)
            ->where('aln.status', '!=', Aluno::STATUS_PENDENTE)
            ->where('p.status', true)
            ->where(function (QueryBuilder $query): void {
                $query->whereNull('p.serie_id')
                    ->orWhereColumn('p.serie_id', 't.id_serie');
            });

        if ($filtrosAtivos['series_ids'] !== []) {
            $query->whereIn('t.id_serie', $filtrosAtivos['series_ids']);
        }

        if ($filtrosAtivos['turnos'] !== []) {
            $query->whereIn('t.turno', $filtrosAtivos['turnos']);
        }

        if ($filtrosAtivos['escolas_ids'] !== []) {
            $query->whereIn('t.id_escola', $filtrosAtivos['escolas_ids']);
        }

        if ($filtrosAtivos['componentes_ids'] !== []) {
            $query->whereIn('p.componente_curricular_id', $filtrosAtivos['componentes_ids']);
        }

        if ($filtrosAtivos['pautas_ids'] !== []) {
            $query->whereIn('p.id', $filtrosAtivos['pautas_ids']);
        }

        if ($filtrosAtivos['professores_ids'] !== []) {
            $query
                ->join('turma_componente_professor as tcp', 'tcp.turma_id', '=', 't.id')
                ->whereIn('tcp.professor_id', $filtrosAtivos['professores_ids'])
                ->where(function (QueryBuilder $query): void {
                    $query->whereNull('p.componente_curricular_id')
                        ->orWhereColumn('tcp.componente_curricular_id', 'p.componente_curricular_id');
                });
        }

        return $query;
    }

    private function contarPreenchimentosEsperados(array $avaliacaoIds): int
    {
        if ($avaliacaoIds === []) {
            return 0;
        }

        $distinctExpr = $this->distinctCombinacaoExpr('at.avaliacao_id', 'at.turma_id', 'p.id', 'aln.id');

        return (int) ((clone $this->basePreenchimentosEsperadosQuery($avaliacaoIds))
            ->selectRaw("COUNT(DISTINCT {$distinctExpr}) as total")
            ->value('total') ?? 0);
    }

    private function contarPreenchimentosRespondidos(array $avaliacaoIds): int
    {
        if ($avaliacaoIds === []) {
            return 0;
        }

        $distinctExpr = $this->distinctCombinacaoExpr('ar.avaliacao_id', 'ar.turma_id', 'ar.pauta_id', 'ar.aluno_id');

        return (int) ((clone $this->baseRespostasQuery($avaliacaoIds, ignorarAlternativas: true))
            ->selectRaw("COUNT(DISTINCT {$distinctExpr}) as total")
            ->value('total') ?? 0);
    }

    private function contarAlunosEsperados(array $avaliacaoIds): int
    {
        if ($avaliacaoIds === []) {
            return 0;
        }

        return (int) ((clone $this->basePreenchimentosEsperadosQuery($avaliacaoIds))
            ->selectRaw('COUNT(DISTINCT aln.id) as total')
            ->value('total') ?? 0);
    }

    private function calcularTotaisPreenchimento(array $avaliacaoIds, array $tabelaEscolas): array
    {
        $esperados = $this->contarPreenchimentosEsperados($avaliacaoIds);
        $respondidos = min($this->contarPreenchimentosRespondidos($avaliacaoIds), $esperados);
        $pendentes = max($esperados - $respondidos, 0);
        $turmas = $this->contarTurmasPreenchidas($avaliacaoIds);

        $totalEscolas = collect($tabelaEscolas)
            ->filter(fn (array $item): bool => (int) ($item['preenchimentos_esperados'] ?? 0) > 0)
            ->count();
        $escolasPreenchidas = collect($tabelaEscolas)
            ->filter(fn (array $item): bool => (bool) ($item['esta_preenchida'] ?? false))
            ->count();
        $escolasNaoPreenchidas = max($totalEscolas - $escolasPreenchidas, 0);

        return [
            'preenchimentos_esperados' => $esperados,
            'preenchimentos_respondidos' => $respondidos,
            'preenchimentos_pendentes' => $pendentes,
            'percentual_alunos_sem_resposta_pautas' => $esperados > 0
                ? round(($pendentes / $esperados) * 100, 1)
                : 0.0,
            'percentual_turmas_preenchidas' => $turmas['esperadas'] > 0
                ? round(($turmas['preenchidas'] / $turmas['esperadas']) * 100, 1)
                : 0.0,
            'turmas_esperadas' => $turmas['esperadas'],
            'turmas_preenchidas' => $turmas['preenchidas'],
            'percentual_escolas_preenchidas' => $totalEscolas > 0
                ? round(($escolasPreenchidas / $totalEscolas) * 100, 1)
                : 0.0,
            'total_escolas' => $totalEscolas,
            'escolas_preenchidas' => $escolasPreenchidas,
            'escolas_nao_preenchidas' => $escolasNaoPreenchidas,
        ];
    }

    private function contarTurmasPreenchidas(array $avaliacaoIds): array
    {
        if ($avaliacaoIds === []) {
            return ['esperadas' => 0, 'preenchidas' => 0];
        }

        $distinctEsperado = $this->distinctCombinacaoExpr('at.avaliacao_id', 'at.turma_id', 'p.id', 'aln.id');
        $distinctRespondido = $this->distinctCombinacaoExpr('ar.avaliacao_id', 'ar.turma_id', 'ar.pauta_id', 'ar.aluno_id');

        $esperadas = (clone $this->basePreenchimentosEsperadosQuery($avaliacaoIds))
            ->groupBy('at.avaliacao_id', 'at.turma_id')
            ->select(
                'at.avaliacao_id',
                'at.turma_id',
                DB::raw("COUNT(DISTINCT {$distinctEsperado}) as total")
            )
            ->get()
            ->keyBy(fn ($item): string => $item->avaliacao_id . ':' . $item->turma_id);

        $respondidas = (clone $this->baseRespostasQuery($avaliacaoIds, ignorarAlternativas: true))
            ->groupBy('ar.avaliacao_id', 'ar.turma_id')
            ->select(
                'ar.avaliacao_id',
                'ar.turma_id',
                DB::raw("COUNT(DISTINCT {$distinctRespondido}) as total")
            )
            ->get()
            ->keyBy(fn ($item): string => $item->avaliacao_id . ':' . $item->turma_id);

        $preenchidas = 0;

        foreach ($esperadas as $chave => $esperada) {
            $totalEsperado = (int) ($esperada->total ?? 0);
            $totalRespondido = (int) ($respondidas->get($chave)?->total ?? 0);

            if ($totalEsperado > 0 && $totalRespondido >= $totalEsperado) {
                $preenchidas++;
            }
        }

        return [
            'esperadas' => $esperadas->count(),
            'preenchidas' => $preenchidas,
        ];
    }

    private function calcularPreenchimentoPorTurno(array $avaliacaoIds): array
    {
        $resultado = [
            'manha' => [
                'esperadas' => 0,
                'respondidas' => 0,
                'percentual_preenchimento' => 0.0,
                'alunos_total' => 0,
                'alunos_pendentes' => 0,
            ],
            'tarde' => [
                'esperadas' => 0,
                'respondidas' => 0,
                'percentual_preenchimento' => 0.0,
                'alunos_total' => 0,
                'alunos_pendentes' => 0,
            ],
        ];

        if ($avaliacaoIds === []) {
            return $resultado;
        }

        $distinctEsperado = $this->distinctCombinacaoExpr('at.avaliacao_id', 'at.turma_id', 'p.id', 'aln.id');
        $distinctRespondido = $this->distinctCombinacaoExpr('ar.avaliacao_id', 'ar.turma_id', 'ar.pauta_id', 'ar.aluno_id');

        $esperadas = (clone $this->basePreenchimentosEsperadosQuery($avaliacaoIds))
            ->whereIn('t.turno', array_keys($resultado))
            ->groupBy('t.turno')
            ->select('t.turno', DB::raw("COUNT(DISTINCT {$distinctEsperado}) as total"))
            ->get()
            ->keyBy('turno');

        $respondidas = (clone $this->baseRespostasQuery($avaliacaoIds, ignorarAlternativas: true))
            ->whereIn('t.turno', array_keys($resultado))
            ->groupBy('t.turno')
            ->select('t.turno', DB::raw("COUNT(DISTINCT {$distinctRespondido}) as total"))
            ->get()
            ->keyBy('turno');

        $esperadasPorAluno = (clone $this->basePreenchimentosEsperadosQuery($avaliacaoIds))
            ->whereIn('t.turno', array_keys($resultado))
            ->groupBy('t.turno', 'aln.id')
            ->select(
                't.turno',
                'aln.id as aluno_id',
                DB::raw("COUNT(DISTINCT {$distinctEsperado}) as total")
            )
            ->get()
            ->groupBy('turno');

        $respondidasPorAluno = (clone $this->baseRespostasQuery($avaliacaoIds, ignorarAlternativas: true))
            ->whereIn('t.turno', array_keys($resultado))
            ->groupBy('t.turno', 'ar.aluno_id')
            ->select(
                't.turno',
                'ar.aluno_id',
                DB::raw("COUNT(DISTINCT {$distinctRespondido}) as total")
            )
            ->get()
            ->groupBy('turno')
            ->map(fn (Collection $items): Collection => $items->keyBy('aluno_id'));

        foreach (array_keys($resultado) as $turno) {
            $totalEsperado = (int) ($esperadas->get($turno)?->total ?? 0);
            $totalRespondido = min((int) ($respondidas->get($turno)?->total ?? 0), $totalEsperado);
            $esperadasDoTurno = $esperadasPorAluno->get($turno, collect());
            $respondidasDoTurno = $respondidasPorAluno->get($turno, collect());
            $alunosPendentes = 0;

            foreach ($esperadasDoTurno as $esperadaPorAluno) {
                $totalEsperadoAluno = (int) ($esperadaPorAluno->total ?? 0);
                $totalRespondidoAluno = min(
                    (int) ($respondidasDoTurno->get($esperadaPorAluno->aluno_id)?->total ?? 0),
                    $totalEsperadoAluno
                );

                if ($totalEsperadoAluno > 0 && $totalRespondidoAluno < $totalEsperadoAluno) {
                    $alunosPendentes++;
                }
            }

            $resultado[$turno] = [
                'esperadas' => $totalEsperado,
                'respondidas' => $totalRespondido,
                'percentual_preenchimento' => $totalEsperado > 0
                    ? round(($totalRespondido / $totalEsperado) * 100, 1)
                    : 0.0,
                'alunos_total' => $esperadasDoTurno->count(),
                'alunos_pendentes' => $alunosPendentes,
            ];
        }

        return $resultado;
    }

    private function contarTurmasEsperadas(array $avaliacaoIds): int
    {
        if ($avaliacaoIds === []) {
            return 0;
        }

        $distinctExpr = $this->distinctCombinacaoExpr('at.avaliacao_id', 'at.turma_id');

        $query = DB::table('avaliacao_turma as at')
            ->join('turmas as t', 't.id', '=', 'at.turma_id')
            ->whereIn('at.avaliacao_id', $avaliacaoIds);

        $this->aplicarFiltrosTurmaQuery($query, 't');

        return (int) ($query
            ->selectRaw("COUNT(DISTINCT {$distinctExpr}) as total")
            ->value('total') ?? 0);
    }

    private function contarTurmasRespondidas(array $avaliacaoIds): int
    {
        if ($avaliacaoIds === []) {
            return 0;
        }

        $distinctExpr = $this->distinctCombinacaoExpr('ar.avaliacao_id', 'ar.turma_id');

        return (int) ((clone $this->baseRespostasQuery($avaliacaoIds))
            ->selectRaw("COUNT(DISTINCT {$distinctExpr}) as total")
            ->value('total') ?? 0);
    }

    /**
     * @return array<int, array<string, int|float|string|bool>>
     */
    private function montarTabelaEscolas(array $avaliacaoIds): array
    {
        if ($avaliacaoIds === []) {
            return [];
        }

        $distinctExprEsperado = $this->distinctCombinacaoExpr('at.avaliacao_id', 'at.turma_id', 'p.id', 'aln.id');
        $distinctExprRespondido = $this->distinctCombinacaoExpr('ar.avaliacao_id', 'ar.turma_id', 'ar.pauta_id', 'ar.aluno_id');

        $esperadas = (clone $this->basePreenchimentosEsperadosQuery($avaliacaoIds))
            ->join('escolas as e', 'e.id', '=', 't.id_escola')
            ->groupBy('e.id', 'e.nome')
            ->select(
                'e.id',
                'e.nome',
                DB::raw("COUNT(DISTINCT {$distinctExprEsperado}) as preenchimentos_esperados"),
                DB::raw('COUNT(DISTINCT at.turma_id) as turmas_esperadas')
            )
            ->get()
            ->keyBy('id');

        $respondidas = (clone $this->baseRespostasQuery($avaliacaoIds, ignorarAlternativas: true))
            ->groupBy('t.id_escola')
            ->select(
                't.id_escola',
                DB::raw("COUNT(DISTINCT {$distinctExprRespondido}) as preenchimentos_respondidos"),
                DB::raw('COUNT(DISTINCT ar.turma_id) as turmas_com_resposta')
            )
            ->get()
            ->keyBy('id_escola');

        $escolasFiltradas = $this->filtros['escolas_ids'] !== []
            ? Escola::query()
                ->where('ativo', true)
                ->whereIn('id', $this->filtros['escolas_ids'])
                ->orderBy('nome')
                ->get(['id', 'nome'])
            : collect();

        /** @var array<int, array<string, int|float|string|bool>> $linhas */
        $linhas = [];

        foreach ($esperadas as $escolaId => $esperada) {
            $respondida = $respondidas->get($escolaId);

            $preenchimentosEsperados = (int) ($esperada->preenchimentos_esperados ?? 0);
            $preenchimentosRespondidos = min((int) ($respondida->preenchimentos_respondidos ?? 0), $preenchimentosEsperados);
            $preenchimentosPendentes = max($preenchimentosEsperados - $preenchimentosRespondidos, 0);
            $percentualPreenchimento = $preenchimentosEsperados > 0
                ? round(($preenchimentosRespondidos / $preenchimentosEsperados) * 100, 1)
                : 0.0;
            $percentualPendentes = $preenchimentosEsperados > 0
                ? round(($preenchimentosPendentes / $preenchimentosEsperados) * 100, 1)
                : 0.0;

            $linhas[] = [
                'id' => (int) $escolaId,
                'nome' => (string) $esperada->nome,
                'preenchimentos_esperados' => $preenchimentosEsperados,
                'preenchimentos_respondidos' => $preenchimentosRespondidos,
                'preenchimentos_pendentes' => $preenchimentosPendentes,
                'percentual_preenchimento' => $percentualPreenchimento,
                'percentual_pendentes' => $percentualPendentes,
                'turmas_esperadas' => (int) ($esperada->turmas_esperadas ?? 0),
                'turmas_com_resposta' => (int) ($respondida->turmas_com_resposta ?? 0),
                'respostas_total' => $preenchimentosRespondidos,
                'percentual_turmas' => $percentualPreenchimento,
                'esta_preenchida' => $preenchimentosEsperados > 0 && $preenchimentosPendentes === 0,
            ];
        }

        foreach ($escolasFiltradas as $escola) {
            $jaExiste = collect($linhas)->contains(fn (array $item): bool => (int) $item['id'] === (int) $escola->id);

            if ($jaExiste) {
                continue;
            }

            $linhas[] = [
                'id' => (int) $escola->id,
                'nome' => (string) $escola->nome,
                'preenchimentos_esperados' => 0,
                'preenchimentos_respondidos' => 0,
                'preenchimentos_pendentes' => 0,
                'percentual_preenchimento' => 0.0,
                'percentual_pendentes' => 0.0,
                'turmas_esperadas' => 0,
                'turmas_com_resposta' => 0,
                'respostas_total' => 0,
                'percentual_turmas' => 0.0,
                'esta_preenchida' => false,
            ];
        }

        usort($linhas, function (array $a, array $b): int {
            if ($a['percentual_pendentes'] === $b['percentual_pendentes']) {
                return strcmp((string) $a['nome'], (string) $b['nome']);
            }

            return $b['percentual_pendentes'] <=> $a['percentual_pendentes'];
        });

        return array_map(function (array $item): array {
            return [
                ...$item,
                'percentual_turmas' => round((float) $item['percentual_turmas'], 1),
                'percentual_preenchimento' => round((float) $item['percentual_preenchimento'], 1),
                'percentual_pendentes' => round((float) $item['percentual_pendentes'], 1),
            ];
        }, $linhas);
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    private function montarGraficoAlunosSemRespostaPorEscola(array $tabelaEscolas): array
    {
        return collect($tabelaEscolas)
            ->filter(fn (array $item): bool => (int) ($item['preenchimentos_esperados'] ?? 0) > 0)
            ->sortByDesc(fn (array $item): float => (float) ($item['percentual_pendentes'] ?? 0))
            ->take(12)
            ->map(function (array $item): array {
                $percentual = (float) ($item['percentual_pendentes'] ?? 0);

                return [
                    'id' => (int) ($item['id'] ?? 0),
                    'nome' => (string) ($item['nome'] ?? '-'),
                    'total' => (int) ($item['preenchimentos_pendentes'] ?? 0),
                    'esperadas' => (int) ($item['preenchimentos_esperados'] ?? 0),
                    'percentual' => round($percentual, 1),
                    'percentual_barra' => round($percentual, 1),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    private function montarResumoAvaliacoes(array $avaliacaoIds): array
    {
        if ($avaliacaoIds === []) {
            return [];
        }

        $distinctEsperado = $this->distinctCombinacaoExpr('at.avaliacao_id', 'at.turma_id', 'p.id', 'aln.id');
        $distinctRespondido = $this->distinctCombinacaoExpr('ar.avaliacao_id', 'ar.turma_id', 'ar.pauta_id', 'ar.aluno_id');

        $esperadosPorAvaliacao = (clone $this->basePreenchimentosEsperadosQuery($avaliacaoIds))
            ->groupBy('at.avaliacao_id')
            ->select(
                'at.avaliacao_id',
                DB::raw("COUNT(DISTINCT {$distinctEsperado}) as preenchimentos_esperados"),
                DB::raw('COUNT(DISTINCT t.id_escola) as escolas_esperadas'),
                DB::raw('COUNT(DISTINCT at.turma_id) as turmas_esperadas')
            )
            ->get()
            ->keyBy('avaliacao_id');

        $respostasPorAvaliacao = (clone $this->baseRespostasQuery($avaliacaoIds, ignorarAlternativas: true))
            ->groupBy('ar.avaliacao_id')
            ->select(
                'ar.avaliacao_id',
                DB::raw("COUNT(DISTINCT {$distinctRespondido}) as respostas_total")
            )
            ->get()
            ->keyBy('avaliacao_id');

        $statusOptions = Avaliacao::statusOptions();

        return Avaliacao::query()
            ->with([
                'tipo:id,nome',
                'periodo:id,nome',
            ])
            ->withCount(['escolas', 'turmas', 'pautas'])
            ->whereIn('id', $avaliacaoIds)
            ->orderByDesc('data_inicio')
            ->limit(20)
            ->get()
            ->map(function (Avaliacao $avaliacao) use ($esperadosPorAvaliacao, $respostasPorAvaliacao, $statusOptions): array {
                $esperado = $esperadosPorAvaliacao->get($avaliacao->id);
                $resposta = $respostasPorAvaliacao->get($avaliacao->id);

                $preenchimentosEsperados = (int) ($esperado->preenchimentos_esperados ?? 0);
                $preenchimentosRespondidos = min((int) ($resposta->respostas_total ?? 0), $preenchimentosEsperados);
                $preenchimentosPendentes = max($preenchimentosEsperados - $preenchimentosRespondidos, 0);
                $percentualPendentes = $preenchimentosEsperados > 0
                    ? round(($preenchimentosPendentes / $preenchimentosEsperados) * 100, 1)
                    : 0.0;

                return [
                    'id' => (int) $avaliacao->id,
                    'nome' => (string) $avaliacao->nome,
                    'tipo' => (string) ($avaliacao->tipo?->nome ?? '-'),
                    'periodo' => (string) ($avaliacao->periodo?->nome ?? '-'),
                    'status' => (string) $avaliacao->status,
                    'status_label' => (string) ($statusOptions[$avaliacao->status] ?? ucfirst((string) $avaliacao->status)),
                    'escolas_esperadas' => (int) ($esperado->escolas_esperadas ?? 0),
                    'turmas_esperadas' => (int) ($esperado->turmas_esperadas ?? 0),
                    'preenchimentos_esperados' => $preenchimentosEsperados,
                    'preenchimentos_respondidos' => $preenchimentosRespondidos,
                    'preenchimentos_pendentes' => $preenchimentosPendentes,
                    'percentual_pendentes' => $percentualPendentes,
                    'respostas_total' => $preenchimentosRespondidos,
                    'pautas_total' => (int) ($avaliacao->pautas_count ?? 0),
                    'data_inicio' => optional($avaliacao->data_inicio)->format('d/m/Y') ?? '-',
                    'data_fim' => optional($avaliacao->data_fim)->format('d/m/Y') ?? '-',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{itens: array<int, array<string, int|string>>, total: int}
     */
    private function montarTurmasAvaliadas(array $avaliacaoIds): array
    {
        if ($avaliacaoIds === []) {
            $this->normalizarPaginaTurmasAvaliadas(0);

            return ['itens' => [], 'total' => 0];
        }

        $query = (clone $this->baseRespostasQuery($avaliacaoIds))
            ->join('avaliacoes as av', 'av.id', '=', 'ar.avaliacao_id')
            ->leftJoin('escolas as e', 'e.id', '=', 't.id_escola')
            ->leftJoin('series as s', 's.id', '=', 't.id_serie')
            ->groupBy(
                'ar.avaliacao_id',
                'ar.turma_id',
                'av.nome',
                't.nome',
                't.turno',
                'e.nome',
                's.nome'
            )
            ->select(
                'ar.avaliacao_id',
                'ar.turma_id',
                'av.nome as avaliacao_nome',
                't.nome as turma_nome',
                't.turno',
                'e.nome as escola_nome',
                's.nome as serie_nome',
                DB::raw('COUNT(*) as respostas_total'),
                DB::raw('COUNT(DISTINCT ar.aluno_id) as alunos_respondidos'),
                DB::raw('COUNT(DISTINCT ar.pauta_id) as pautas_respondidas'),
                DB::raw('MAX(ar.respondido_em) as ultima_resposta_em')
            );

        $total = (int) DB::query()
            ->fromSub(clone $query, 'turmas_avaliadas')
            ->count();

        $this->normalizarPaginaTurmasAvaliadas($total);

        $dados = $query
            ->orderByDesc(DB::raw('MAX(ar.respondido_em)'))
            ->orderBy('av.nome')
            ->forPage($this->turmasAvaliadasPagina, $this->turmasAvaliadasPorPagina)
            ->get();

        $itens = $dados
            ->map(function ($item): array {
                return [
                    'avaliacao_id' => (int) $item->avaliacao_id,
                    'turma_id' => (int) $item->turma_id,
                    'avaliacao_nome' => (string) ($item->avaliacao_nome ?? '-'),
                    'escola_nome' => (string) ($item->escola_nome ?? '-'),
                    'serie_nome' => (string) ($item->serie_nome ?? '-'),
                    'turma_nome' => (string) ($item->turma_nome ?? '-'),
                    'turno' => (string) ($item->turno ?? '-'),
                    'respostas_total' => (int) ($item->respostas_total ?? 0),
                    'alunos_respondidos' => (int) ($item->alunos_respondidos ?? 0),
                    'pautas_respondidas' => (int) ($item->pautas_respondidas ?? 0),
                    'ultima_resposta' => $item->ultima_resposta_em
                        ? \Illuminate\Support\Carbon::parse($item->ultima_resposta_em)->format('d/m/Y H:i')
                        : '-',
                ];
            })
            ->values()
            ->all();

        return ['itens' => $itens, 'total' => $total];
    }

    /**
     * @return array{
     *     titulo: string,
     *     subtitulo: string,
     *     total_respostas: int,
     *     itens: array<int, array{name: string, nome: string, total: int, percentual: float, percentual_barra: float}>
     * }
     */
    private function montarDistribuicaoAlternativas(array $avaliacaoIds): array
    {
        if ($avaliacaoIds === []) {
            return [
                'titulo' => 'Distribuição de alternativas',
                'subtitulo' => 'Sem dados para o escopo atual.',
                'total_respostas' => 0,
                'total_esperado' => 0,
                'total_alunos' => 0,
                'itens' => [],
            ];
        }

        $avaliacaoSelecionadaId = (int) ($this->filtros['avaliacao_id'] ?? 0);

        if ($avaliacaoSelecionadaId > 0 && in_array($avaliacaoSelecionadaId, $avaliacaoIds, true)) {
            $avaliacao = Avaliacao::query()
                ->select('id', 'nome', 'tipo_avaliacao_id')
                ->find($avaliacaoSelecionadaId);

            if (! $avaliacao) {
                return [
                    'titulo' => 'Distribuição de alternativas',
                    'subtitulo' => 'Avaliação selecionada não encontrada.',
                    'total_respostas' => 0,
                    'total_esperado' => 0,
                    'total_alunos' => 0,
                    'itens' => [],
                ];
            }

            $contagens = (clone $this->baseRespostasQuery([$avaliacaoSelecionadaId], ignorarAlternativas: true))
                ->groupBy('ar.alternativa_id', 'alt.nome')
                ->select('ar.alternativa_id', 'alt.nome', DB::raw('COUNT(DISTINCT ar.aluno_id) as total'))
                ->get()
                ->keyBy('alternativa_id');

            $idsPadrao = Alternativa::query()
                ->where('status', true)
                ->where('tipo_avaliacao_id', (int) $avaliacao->tipo_avaliacao_id)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id);

            $idsOverride = DB::table('avaliacao_pauta_alternativa')
                ->where('avaliacao_id', $avaliacaoSelecionadaId)
                ->pluck('alternativa_id')
                ->map(fn ($id): int => (int) $id);

            $idsAlternativas = $idsPadrao
                ->merge($idsOverride)
                ->merge($contagens->pluck('alternativa_id')->map(fn ($id): int => (int) $id))
                ->unique()
                ->values();

            $nomesAlternativas = Alternativa::query()
                ->whereIn('id', $idsAlternativas->all())
                ->pluck('nome', 'id');

            $totalRespostas = (int) $contagens->sum('total');
            $totalAlunos = $this->contarAlunosEsperados([$avaliacaoSelecionadaId]);

            $itens = $idsAlternativas
                ->map(function (int $alternativaId) use ($contagens, $nomesAlternativas, $totalAlunos): array {
                    $registro = $contagens->get($alternativaId);
                    $total = (int) ($registro->total ?? 0);
                    $nome = (string) ($nomesAlternativas[$alternativaId] ?? $registro->nome ?? "Alternativa #{$alternativaId}");
                    $percentual = $totalAlunos > 0 ? round(($total / $totalAlunos) * 100, 1) : 0.0;

                    return [
                        'nome' => $nome,
                        'total' => $total,
                        'percentual' => $percentual,
                        'percentual_barra' => $percentual,
                    ];
                })
                ->sortByDesc('total')
                ->values()
                ->all();

            return [
                'titulo' => 'Distribuição de alternativas',
                'subtitulo' => 'Avaliação selecionada: ' . $avaliacao->nome,
                'total_respostas' => $totalRespostas,
                'total_esperado' => $totalAlunos,
                'total_alunos' => $totalAlunos,
                'itens' => $itens,
            ];
        }

        $contagens = (clone $this->baseRespostasQuery($avaliacaoIds))
            ->groupBy('ar.alternativa_id', 'alt.nome')
            ->orderByDesc(DB::raw('COUNT(DISTINCT ar.aluno_id)'))
            ->limit(12)
            ->select('ar.alternativa_id', 'alt.nome', DB::raw('COUNT(DISTINCT ar.aluno_id) as total'))
            ->get();

        $totalRespostas = (int) $contagens->sum('total');
        $totalAlunos = $this->contarAlunosEsperados($avaliacaoIds);

        $itens = $contagens
            ->map(function ($item) use ($totalAlunos): array {
                $total = (int) ($item->total ?? 0);
                $percentual = $totalAlunos > 0 ? round(($total / $totalAlunos) * 100, 1) : 0.0;

                return [
                    'nome' => (string) $item->nome,
                    'total' => $total,
                    'percentual' => $percentual,
                    'percentual_barra' => $percentual,
                ];
            })
            ->values()
            ->all();

        return [
            'titulo' => 'Top alternativas no escopo',
            'subtitulo' => 'Selecione uma avaliação para ver a distribuição detalhada por alternativa.',
            'total_respostas' => $totalRespostas,
            'total_esperado' => $totalAlunos,
            'total_alunos' => $totalAlunos,
            'itens' => $itens,
        ];
    }

    private function aplicarFiltrosTurmaQuery(QueryBuilder $query, string $alias = 't', ?array $filtros = null): void
    {
        $filtrosAtivos = $filtros ?? $this->filtros;

        if ($filtrosAtivos['series_ids'] !== []) {
            $query->whereIn($alias . '.id_serie', $filtrosAtivos['series_ids']);
        }

        if ($filtrosAtivos['turnos'] !== []) {
            $query->whereIn($alias . '.turno', $filtrosAtivos['turnos']);
        }

        if ($filtrosAtivos['escolas_ids'] !== []) {
            $query->whereIn($alias . '.id_escola', $filtrosAtivos['escolas_ids']);
        }
    }

    /**
     * @return array<string, string>
     */
    private function filtrosAplicadosFormatados(): array
    {
        $filtros = [];

        if ($this->filtros['avaliacao_id']) {
            $filtros['Avaliação'] = $this->labelById(
                $this->filtros['avaliacao_id'],
                $this->avaliacoesOptions
            );
        }

        if ($this->filtros['tipo_id']) {
            $filtros['Tipo'] = $this->labelById(
                $this->filtros['tipo_id'],
                $this->tiposOptions
            );
        }

        if ($this->filtros['periodo_id']) {
            $filtros['Período'] = $this->labelById(
                $this->filtros['periodo_id'],
                $this->periodosOptions
            );
        }

        if ($this->filtros['status'] !== 'todas') {
            $filtros['Status'] = $this->statusOptions[$this->filtros['status']] ?? $this->filtros['status'];
        }

        if ($this->filtros['series_ids'] !== []) {
            $filtros['Séries'] = $this->labelsByIds($this->filtros['series_ids'], $this->seriesOptions);
        }

        if ($this->filtros['turnos'] !== []) {
            $filtros['Turnos'] = $this->labelsByIds($this->filtros['turnos'], $this->turnosOptions);
        }

        if ($this->filtros['componentes_ids'] !== []) {
            $filtros['Componentes'] = $this->labelsByIds($this->filtros['componentes_ids'], $this->componentesOptions);
        }

        if ($this->filtros['escolas_ids'] !== []) {
            $filtros['Escolas'] = $this->labelsByIds($this->filtros['escolas_ids'], $this->escolasOptions);
        }

        if ($this->filtros['professores_ids'] !== []) {
            $filtros['Professores'] = $this->labelsByIds($this->filtros['professores_ids'], $this->professoresOptions);
        }

        if ($this->filtros['pautas_ids'] !== []) {
            $filtros['Pautas'] = $this->labelsByIds($this->filtros['pautas_ids'], $this->pautasOptions);
        }

        if ($this->filtros['alternativas_ids'] !== []) {
            $filtros['Alternativas'] = $this->labelsByIds($this->filtros['alternativas_ids'], $this->alternativasOptions);
        }

        return $filtros;
    }

    private function labelById(int|string $id, array $options): string
    {
        return (string) ($options[$id] ?? $options[(string) $id] ?? "ID {$id}");
    }

    /**
     * @param array<int|string> $ids
     */
    private function labelsByIds(array $ids, array $options): string
    {
        return collect($ids)
            ->map(fn ($id): string => (string) ($options[$id] ?? $options[(string) $id] ?? "ID {$id}"))
            ->filter(fn (string $label): bool => $label !== '')
            ->unique()
            ->values()
            ->implode(', ');
    }

    private function normalizarFiltros(): void
    {
        $this->filtros['avaliacao_id'] = $this->normalizarId($this->filtros['avaliacao_id'] ?? null);
        $this->filtros['tipo_id'] = $this->normalizarId($this->filtros['tipo_id'] ?? null);
        $this->filtros['periodo_id'] = $this->normalizarId($this->filtros['periodo_id'] ?? null);

        $status = (string) ($this->filtros['status'] ?? 'todas');
        $this->filtros['status'] = array_key_exists($status, $this->statusOptions)
            ? $status
            : 'todas';

        $this->filtros['series_ids'] = $this->normalizarArrayIds($this->filtros['series_ids'] ?? []);
        $this->filtros['componentes_ids'] = $this->normalizarArrayIds($this->filtros['componentes_ids'] ?? []);
        $this->filtros['escolas_ids'] = $this->normalizarArrayIds($this->filtros['escolas_ids'] ?? []);
        $this->filtros['professores_ids'] = $this->normalizarArrayIds($this->filtros['professores_ids'] ?? []);
        $this->filtros['pautas_ids'] = $this->normalizarArrayIds($this->filtros['pautas_ids'] ?? []);
        $this->filtros['alternativas_ids'] = $this->normalizarArrayIds($this->filtros['alternativas_ids'] ?? []);

        $turnosPermitidos = array_keys($this->turnosOptions);
        $this->filtros['turnos'] = collect($this->filtros['turnos'] ?? [])
            ->map(fn ($turno): string => (string) $turno)
            ->filter(fn (string $turno): bool => in_array($turno, $turnosPermitidos, true))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizarId(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    /**
     * @param array<int, mixed> $values
     * @return array<int>
     */
    private function normalizarArrayIds(array $values): array
    {
        return collect($values)
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosPadrao(): array
    {
        return [
            'avaliacao_id' => null,
            'tipo_id' => null,
            'periodo_id' => null,
            'status' => 'todas',
            'series_ids' => [],
            'turnos' => [],
            'componentes_ids' => [],
            'escolas_ids' => [],
            'professores_ids' => [],
            'pautas_ids' => [],
            'alternativas_ids' => [],
        ];
    }

    private function distinctCombinacaoExpr(string ...$colunas): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return implode(" || '|#|' || ", $colunas);
        }

        return 'CONCAT(' . implode(", '|#|', ", $colunas) . ')';
    }

    private function preencherTabelaSheet(Worksheet $sheet, string $titulo, array $headers, Collection $rows): void
    {
        $linha = $this->preencherCabecalhoSheet($sheet, $titulo, []);
        $ultimaColuna = Coordinate::stringFromColumnIndex(count($headers));

        $sheet->fromArray($headers, null, "A{$linha}");
        $this->estilizarHeaderLinha($sheet, "A{$linha}:{$ultimaColuna}{$linha}");
        $linha++;

        foreach ($rows as $row) {
            $sheet->fromArray($row, null, "A{$linha}");
            $linha++;
        }

        if ($rows->isNotEmpty()) {
            $this->estilizarCorpoTabela($sheet, 'A' . ($linha - $rows->count()) . ":{$ultimaColuna}" . ($linha - 1));
        }

        $this->autoSizeColumns($sheet, count($headers));
    }

    /**
     * @param array<string, string> $filtros
     */
    private function preencherCabecalhoSheet(Worksheet $sheet, string $titulo, array $filtros): int
    {
        $sheet->setCellValue('A1', $titulo);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', 'Gerado em ' . now()->format('d/m/Y H:i') . ' por ' . (Auth::user()?->name ?? 'Sistema'));
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        $linha = 4;

        if ($filtros !== []) {
            $sheet->setCellValue("A{$linha}", 'Filtros aplicados');
            $sheet->getStyle("A{$linha}")->getFont()->setBold(true);
            $linha++;

            foreach ($filtros as $label => $value) {
                $sheet->setCellValue("A{$linha}", $label);
                $sheet->setCellValue("B{$linha}", $value);
                $sheet->getStyle("A{$linha}")->getFont()->setBold(true);
                $linha++;
            }

            $linha++;
        }

        return $linha;
    }

    private function estilizarHeaderLinha(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0B5BA8'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
    }

    private function estilizarCorpoTabela(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    private function autoSizeColumns(Worksheet $sheet, int $count): void
    {
        foreach (range(1, $count) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $fileName)
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'dashboard_avaliacoes_');

        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
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
