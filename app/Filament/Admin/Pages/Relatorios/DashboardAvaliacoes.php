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
use App\Models\User;
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

    private const PERMISSAO_ACOMPANHAR_AVALIACOES = 'Acompanhar Avaliações';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.relatorios.dashboard-avaliacoes';

    protected static ?string $title = 'Acompanhamento de Pareceres';

    protected static ?string $navigationLabel = 'Acompanhamento de Pareceres';

    protected static ?string $navigationParentItem = 'Avaliações';

    protected static ?string $slug = 'dashboard-avaliacoes';

    protected static ?int $navigationSort = 26;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public array $filtros = [];

    public array $cards = [];

    public array $tabelaEscolas = [];

    public array $preenchimentoPorComponentes = [];

    public array $preenchimentoPorSeries = [];

    public array $turmasIncompletasPorEscola = [];

    public array $acompanhamentoTurmas = [];

    public int $acompanhamentoTurmasTotal = 0;

    public int $acompanhamentoTurmasPagina = 1;

    public int $acompanhamentoTurmasPorPagina = 5;

    public array $acompanhamentoTurmasPorPaginaOptions = [5, 10, 25, 50, 100];

    public array $filtrosAcompanhamento = [];

    public bool $workspaceAcompanhamentoAberto = false;

    public ?array $workspaceAcompanhamentoLinha = null;

    public int $workspaceAcompanhamentoKey = 0;

    public array $listagensPaginas = [
        'tabelaEscolas' => 1,
    ];

    public array $listagensPorPagina = [
        'tabelaEscolas' => 5,
    ];

    public array $listagensPorPaginaOptions = [5, 10, 25, 50, 100];

    public array $filtrosAplicados = [];

    public string $ultimaAtualizacao = '';

    public function mount(): void
    {
        $this->filtros = $this->filtrosPadrao();
        $this->filtrosAcompanhamento = $this->filtrosAcompanhamentoPadrao();

        $avaliacaoId = $this->avaliacaoIdInicialDaUrl();

        if ($avaliacaoId) {
            $this->filtros['avaliacao_id'] = $avaliacaoId;
        }

        $this->atualizarDashboard();
    }

    public function updatedFiltros(mixed $value = null, ?string $key = null): void
    {
        $this->resetarPaginacoesDashboard();
        $this->fecharWorkspaceAcompanhamento();

        if ($key === 'avaliacao_id') {
            $this->limparFiltrosDependentes();
            $this->filtrosAcompanhamento = $this->filtrosAcompanhamentoPadrao();
        }

        $this->atualizarDashboard();
    }

    public function updatedFiltrosAvaliacaoId(): void
    {
        $this->resetarPaginacoesDashboard();
        $this->fecharWorkspaceAcompanhamento();
        $this->limparFiltrosDependentes();
        $this->filtrosAcompanhamento = $this->filtrosAcompanhamentoPadrao();
        $this->atualizarDashboard();
    }

    public function limparFiltros(): void
    {
        $this->filtros = $this->filtrosPadrao();
        $this->filtrosAcompanhamento = $this->filtrosAcompanhamentoPadrao();
        $this->resetarPaginacoesDashboard();
        $this->fecharWorkspaceAcompanhamento();
        $this->atualizarDashboard();
    }

    public function updatedFiltrosAcompanhamento(): void
    {
        $this->workspaceAcompanhamentoAberto = false;
        $this->workspaceAcompanhamentoLinha = null;
        $this->resetarPaginacaoAcompanhamentoTurmas();
        $this->atualizarAcompanhamentoTurmas();
    }

    public function updatedAcompanhamentoTurmasPorPagina(mixed $value): void
    {
        $this->acompanhamentoTurmasPorPagina = $this->normalizarAcompanhamentoTurmasPorPagina($value);
        $this->resetarPaginacaoAcompanhamentoTurmas();
        $this->atualizarAcompanhamentoTurmas();
    }

    public function updatedListagensPorPagina(mixed $value, ?string $key = null): void
    {
        if (! $this->listagemPaginadaValida($key)) {
            return;
        }

        $this->listagensPorPagina[$key] = $this->normalizarListagemPorPagina($value);
        $this->listagensPaginas[$key] = 1;
        $this->normalizarPaginacoesListagens();
    }

    public function paginaAnteriorListagem(string $listagem): void
    {
        if (! $this->listagemPaginadaValida($listagem)) {
            return;
        }

        $this->listagensPaginas[$listagem] = max(((int) ($this->listagensPaginas[$listagem] ?? 1)) - 1, 1);
    }

    public function proximaPaginaListagem(string $listagem): void
    {
        if (! $this->listagemPaginadaValida($listagem)) {
            return;
        }

        $paginaAtual = (int) ($this->listagensPaginas[$listagem] ?? 1);
        $this->listagensPaginas[$listagem] = min($paginaAtual + 1, $this->totalPaginasListagem($listagem));
    }

    public function paginaAnteriorAcompanhamentoTurmas(): void
    {
        $this->acompanhamentoTurmasPagina = max($this->acompanhamentoTurmasPagina - 1, 1);
        $this->atualizarAcompanhamentoTurmas();
    }

    public function proximaPaginaAcompanhamentoTurmas(): void
    {
        $this->acompanhamentoTurmasPagina = min(
            $this->acompanhamentoTurmasPagina + 1,
            $this->totalPaginasAcompanhamentoTurmas()
        );
        $this->atualizarAcompanhamentoTurmas();
    }

    public function abrirWorkspaceAcompanhamento(
        int $avaliacaoId,
        int $turmaId,
        int $escolaId,
        int $serieId,
        ?int $componenteId = null,
        ?int $professorId = null
    ): void {
        abort_unless(static::canAccess(), 403);

        $linha = $this->localizarLinhaAcompanhamento(
            $avaliacaoId,
            $turmaId,
            $escolaId,
            $serieId,
            $componenteId,
            $professorId
        );

        if ($linha === null) {
            $this->fecharWorkspaceAcompanhamento();

            Notification::make()
                ->title('A avaliação selecionada não está mais disponível no seu escopo.')
                ->warning()
                ->send();

            return;
        }

        $this->workspaceAcompanhamentoLinha = $linha;
        $this->workspaceAcompanhamentoAberto = true;
        $this->workspaceAcompanhamentoKey++;
    }

    public function fecharWorkspaceAcompanhamento(): void
    {
        $estavaAberto = $this->workspaceAcompanhamentoAberto;

        $this->workspaceAcompanhamentoAberto = false;
        $this->workspaceAcompanhamentoLinha = null;

        if ($estavaAberto) {
            $this->atualizarAcompanhamentoTurmas();
        }
    }

    public function atualizarWorkspaceAcompanhamento(): void
    {
        $this->atualizarAcompanhamentoTurmas();

        return;

        $linhaAtual = $this->workspaceAcompanhamentoLinha;

        if ($linhaAtual === null) {
            return;
        }

        $linhaRevalidada = $this->localizarLinhaAcompanhamento(
            (int) ($linhaAtual['avaliacao_id'] ?? 0),
            (int) ($linhaAtual['turma_id'] ?? 0),
            (int) ($linhaAtual['escola_id'] ?? 0),
            (int) ($linhaAtual['serie_id'] ?? 0),
            isset($linhaAtual['componente_id']) ? (int) $linhaAtual['componente_id'] : null,
            isset($linhaAtual['professor_id']) ? (int) $linhaAtual['professor_id'] : null
        );

        if ($linhaRevalidada === null) {
            $this->fecharWorkspaceAcompanhamento();

            Notification::make()
                ->title('O recorte do modal deixou de ser válido para o seu escopo atual.')
                ->warning()
                ->send();

            return;
        }

        $this->workspaceAcompanhamentoLinha = $linhaRevalidada;
    }

    protected function getForms(): array
    {
        return [
            'filtrosForm',
            'filtrosAcompanhamentoForm',
        ];
    }

    public function filtrosForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('avaliacao_id')
                    ->label('Avaliação')
                    ->options(fn (): array => $this->avaliacoesOptions)
                    ->placeholder('Selecione uma avaliação')
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

    public function filtrosAcompanhamentoForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('escolas_ids')
                    ->label('Escolas')
                    ->options(fn (): array => $this->escolasOptions)
                    ->multiple()
                    ->native(false)
                    ->searchable()
                    ->preload()
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
            ])
            ->columns(4)
            ->statePath('filtrosAcompanhamento');
    }

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Relatórios Pedagógicos',
            'title' => 'Acompanhamento de Pareceres',
            'description' => 'Acompanhe o preenchimento dos pareceres por escola, turma, componente e professor. Atualizado em ' . ($this->ultimaAtualizacao ?: '-'),
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
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->can(self::PERMISSAO_ACOMPANHAR_AVALIACOES);
    }

    private function usuarioAtual(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user;
    }

    private function usuarioTemEscopoGlobal(?User $user = null): bool
    {
        $user ??= $this->usuarioAtual();

        return (bool) $user?->hasRole('Admin');
    }

    /**
     * @return array<int>|null Retorna null para acesso global.
     */
    private function escolasPermitidasIds(?User $user = null): ?array
    {
        $user ??= $this->usuarioAtual();

        if (! $user) {
            return [];
        }

        if ($this->usuarioTemEscopoGlobal($user)) {
            return null;
        }

        $escolasIds = $user->idsEscolasVinculadas();

        return $escolasIds === [] ? null : $escolasIds;
    }

    private function avaliacaoIdInicialDaUrl(): ?int
    {
        $avaliacaoId = $this->normalizarId(request()->query('avaliacao'));

        if (! $avaliacaoId) {
            return null;
        }

        return $this->avaliacaoDisponivelParaUsuario($avaliacaoId)
            ? $avaliacaoId
            : null;
    }

    private function avaliacaoDisponivelParaUsuario(int $avaliacaoId): bool
    {
        return Avaliacao::query()
            ->whereKey($avaliacaoId)
            ->whereHas('turmas', function (EloquentBuilder $turmaQuery): void {
                $this->aplicarEscopoEscolarEloquent($turmaQuery);
            })
            ->exists();
    }

    private function aplicarEscopoEscolarQuery(QueryBuilder $query, string $alias = 't'): void
    {
        $escolasIds = $this->escolasPermitidasIds();

        if ($escolasIds === null) {
            return;
        }

        if ($escolasIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($alias . '.id_escola', $escolasIds);
    }

    private function aplicarEscopoEscolarEloquent(EloquentBuilder $query): void
    {
        $escolasIds = $this->escolasPermitidasIds();

        if ($escolasIds === null) {
            return;
        }

        if ($escolasIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('id_escola', $escolasIds);
    }

    /**
     * @param array<int> $ids
     * @return array<int>
     */
    private function filtrarEscolasPermitidas(array $ids): array
    {
        $escolasIds = $this->escolasPermitidasIds();

        if ($escolasIds === null) {
            return $ids;
        }

        if ($ids === [] || $escolasIds === []) {
            return [];
        }

        return array_values(array_intersect($ids, $escolasIds));
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

    private function podeAbrirAvaliacoesProfessor(): bool
    {
        $user = $this->usuarioAtual();

        if (! $user) {
            return false;
        }

        return $user->hasPermissionLike('listar avaliacoes')
            || $user->hasPermissionLike('responder avaliacoes');
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
            ->whereHas('turmas', function (EloquentBuilder $turmaQuery): void {
                $this->aplicarEscopoEscolarEloquent($turmaQuery);
            })
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

                $this->aplicarEscopoEscolarQuery($subQuery, 't2');

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

    private function resetarPaginacaoAcompanhamentoTurmas(): void
    {
        $this->acompanhamentoTurmasPagina = 1;
    }

    private function resetarPaginacoesDashboard(): void
    {
        $this->resetarPaginacaoAcompanhamentoTurmas();

        foreach (array_keys($this->listagensPorPagina) as $listagem) {
            $this->listagensPaginas[$listagem] = 1;
        }
    }

    private function listagemPaginadaValida(?string $listagem): bool
    {
        return is_string($listagem)
            && array_key_exists($listagem, $this->listagensPorPagina);
    }

    private function normalizarListagemPorPagina(mixed $value): int
    {
        $porPagina = (int) $value;

        return in_array($porPagina, $this->listagensPorPaginaOptions, true)
            ? $porPagina
            : 5;
    }

    private function normalizarPaginacoesListagens(): void
    {
        foreach (array_keys($this->listagensPorPagina) as $listagem) {
            $this->listagensPorPagina[$listagem] = $this->normalizarListagemPorPagina(
                $this->listagensPorPagina[$listagem] ?? 5
            );

            $this->listagensPaginas[$listagem] = min(
                max((int) ($this->listagensPaginas[$listagem] ?? 1), 1),
                $this->totalPaginasListagem($listagem)
            );
        }
    }

    private function totalPaginasListagem(string $listagem): int
    {
        $total = count(match ($listagem) {
            'tabelaEscolas' => $this->tabelaEscolas,
            default => [],
        });

        $porPagina = max($this->normalizarListagemPorPagina($this->listagensPorPagina[$listagem] ?? 5), 1);

        return max((int) ceil($total / $porPagina), 1);
    }

    private function normalizarAcompanhamentoTurmasPorPagina(mixed $value): int
    {
        $porPagina = (int) $value;

        return in_array($porPagina, $this->acompanhamentoTurmasPorPaginaOptions, true)
            ? $porPagina
            : 10;
    }

    private function normalizarPaginaAcompanhamentoTurmas(?int $total = null): void
    {
        $this->acompanhamentoTurmasPorPagina = $this->normalizarAcompanhamentoTurmasPorPagina(
            $this->acompanhamentoTurmasPorPagina
        );
        $this->acompanhamentoTurmasPagina = min(
            max((int) $this->acompanhamentoTurmasPagina, 1),
            $this->totalPaginasAcompanhamentoTurmas($total)
        );
    }

    private function totalPaginasAcompanhamentoTurmas(?int $total = null): int
    {
        $total = $total ?? $this->acompanhamentoTurmasTotal;
        $porPagina = max($this->normalizarAcompanhamentoTurmasPorPagina($this->acompanhamentoTurmasPorPagina), 1);

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

        $this->aplicarEscopoEscolarQuery($query, 't');

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
                ->title('Selecione uma avaliação antes de exportar.')
                ->warning()
                ->send();

            return null;
        }

        $dados = $this->montarDashboardData(incluirAcompanhamentoCompleto: true);

        return app(RelatorioPdfRenderer::class)->download(
            'relatorios.Avaliacoes.dashboard-avaliacoes',
            [
                'cards' => $dados['cards'],
                'escolas' => $dados['tabela_escolas'],
                'componentes' => $dados['preenchimento_por_componentes'],
                'series' => $dados['preenchimento_por_series'],
                'turmasIncompletasPorEscola' => $dados['turmas_incompletas_por_escola'],
                'acompanhamentoTurmas' => $dados['acompanhamento_turmas'],
                'reportTitle' => 'Acompanhamento de Pareceres',
                'reportSubtitle' => 'Resumo analítico por escopo e preenchimento',
                'reportFilters' => $this->filtrosAplicadosFormatados(),
                'usuarioExportacao' => Auth::user(),
                'dataExportacao' => now(),
                'orientation' => 'landscape',
            ],
            'dashboard-avaliações-' . now()->format('Y-m-d_H-i') . '.pdf'
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
                ->title('Selecione uma avaliação antes de exportar.')
                ->warning()
                ->send();

            return null;
        }

        return $this->exportarXlsxAcompanhamento();
    }

    private function exportarXlsxAcompanhamento()
    {
        $dados = $this->montarDashboardData(incluirAcompanhamentoCompleto: true);
        $spreadsheet = new Spreadsheet();

        $resumoSheet = $spreadsheet->getActiveSheet();
        $resumoSheet->setTitle('Resumo');
        $linha = $this->preencherCabecalhoSheet(
            $resumoSheet,
            'Acompanhamento de Pareceres',
            $this->filtrosAplicadosFormatados()
        );

        $resumoSheet->setCellValue("A{$linha}", 'Indicador');
        $resumoSheet->setCellValue("B{$linha}", 'Valor');
        $this->estilizarHeaderLinha($resumoSheet, "A{$linha}:B{$linha}");
        $linha++;

        $indicadores = [
            '% de preenchimento geral' => ($dados['cards']['percentual_preenchimento_geral'] ?? 0) . '%',
            'Preenchimentos esperados' => $dados['cards']['preenchimentos_esperados'] ?? 0,
            'Preenchimentos respondidos' => $dados['cards']['preenchimentos_respondidos'] ?? 0,
            'Preenchimentos pendentes' => $dados['cards']['preenchimentos_pendentes'] ?? 0,
            '% de turmas preenchidas' => ($dados['cards']['percentual_turmas_preenchidas'] ?? 0) . '%',
            'Turmas preenchidas' => ($dados['cards']['turmas_preenchidas'] ?? 0) . ' de ' . ($dados['cards']['turmas_esperadas'] ?? 0),
            'Turmas incompletas' => $dados['cards']['turmas_incompletas'] ?? 0,
            'Alunos pendentes manha' => ($dados['cards']['turno_manha_alunos_pendentes'] ?? 0) . ' de ' . ($dados['cards']['turno_manha_alunos_total'] ?? 0),
            'Alunos pendentes tarde' => ($dados['cards']['turno_tarde_alunos_pendentes'] ?? 0) . ' de ' . ($dados['cards']['turno_tarde_alunos_total'] ?? 0),
        ];

        foreach ($indicadores as $label => $valor) {
            $resumoSheet->setCellValue("A{$linha}", $label);
            $resumoSheet->setCellValue("B{$linha}", $valor);
            $linha++;
        }

        $this->estilizarCorpoTabela($resumoSheet, 'A' . ($linha - count($indicadores)) . ':B' . ($linha - 1));
        $this->autoSizeColumns($resumoSheet, 2);

        $this->preencherTabelaSheet(
            $spreadsheet->createSheet()->setTitle('Escolas'),
            'Progresso por Escola',
            ['Escola', 'Turmas no escopo', 'Turmas concluidas', 'Turmas incompletas', '% turmas incompletas', '% preenchimento', 'Preenchimentos pendentes'],
            collect($dados['tabela_escolas'])->map(fn (array $item): array => [
                $item['nome'],
                $item['turmas_esperadas'],
                $item['turmas_preenchidas'],
                $item['turmas_incompletas'],
                $item['percentual_turmas_incompletas'] . '%',
                $item['percentual_preenchimento'] . '%',
                $item['preenchimentos_pendentes'],
            ])
        );

        $this->preencherTabelaSheet(
            $spreadsheet->createSheet()->setTitle('Componentes'),
            'Preenchimento por Componente Curricular',
            ['Componente', 'Preenchimentos esperados', 'Preenchimentos respondidos', 'Preenchimentos pendentes', '% preenchimento'],
            collect($dados['preenchimento_por_componentes'])->map(fn (array $item): array => [
                $item['nome'],
                $item['preenchimentos_esperados'],
                $item['preenchimentos_respondidos'],
                $item['preenchimentos_pendentes'],
                $item['percentual_preenchimento'] . '%',
            ])
        );

        $this->preencherTabelaSheet(
            $spreadsheet->createSheet()->setTitle('Series'),
            'Preenchimento por Serie',
            ['Serie', 'Preenchimentos esperados', 'Preenchimentos respondidos', 'Preenchimentos pendentes', '% preenchimento'],
            collect($dados['preenchimento_por_series'])->map(fn (array $item): array => [
                $item['nome'],
                $item['preenchimentos_esperados'],
                $item['preenchimentos_respondidos'],
                $item['preenchimentos_pendentes'],
                $item['percentual_preenchimento'] . '%',
            ])
        );

        $this->preencherTabelaSheet(
            $spreadsheet->createSheet()->setTitle('Acompanhamento'),
            'Acompanhamento de Pareceres por Turma',
            ['Escola', 'Serie', 'Turma', 'Turno', 'Componente', 'Professor', 'Esperados', 'Respondidos', 'Pendentes', '% preenchimento', 'Status'],
            collect($dados['acompanhamento_turmas'])->map(fn (array $item): array => [
                $item['escola_nome'],
                $item['serie_nome'],
                $item['turma_nome'],
                $item['turno'],
                $item['componente_nome'],
                $item['professor_nome'],
                $item['preenchimentos_esperados'],
                $item['preenchimentos_respondidos'],
                $item['preenchimentos_pendentes'],
                $item['percentual_preenchimento'] . '%',
                $item['status_label'],
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
        $this->preenchimentoPorComponentes = $dados['preenchimento_por_componentes'];
        $this->preenchimentoPorSeries = $dados['preenchimento_por_series'];
        $this->turmasIncompletasPorEscola = $dados['turmas_incompletas_por_escola'];
        $this->acompanhamentoTurmas = $dados['acompanhamento_turmas'];
        $this->acompanhamentoTurmasTotal = $dados['acompanhamento_turmas_total'];
        $this->normalizarPaginacoesListagens();
        $this->filtrosAplicados = $this->filtrosAplicadosFormatados();
        $this->ultimaAtualizacao = $this->avaliacaoSelecionada()
            ? now()->format('d/m/Y H:i:s')
            : '';
    }

    private function atualizarAcompanhamentoTurmas(): void
    {
        $this->normalizarFiltros();
        $this->normalizarFiltrosAcompanhamento();

        if (! $this->avaliacaoSelecionada()) {
            $this->acompanhamentoTurmas = [];
            $this->acompanhamentoTurmasTotal = 0;

            return;
        }

        $avaliacaoIds = $this->obterIdsAvaliacoesFiltradas();
        $acompanhamentoTurmas = $this->montarAcompanhamentoTurmas($avaliacaoIds, paginar: true);

        $this->acompanhamentoTurmas = $acompanhamentoTurmas['itens'];
        $this->acompanhamentoTurmasTotal = $acompanhamentoTurmas['total'];
    }

    /**
     * @return array{
     *     cards: array<string, int|float>,
     *     tabela_escolas: array<int, array<string, int|float|string|bool>>,
     *     preenchimento_por_componentes: array<int, array<string, int|float|string>>,
     *     preenchimento_por_series: array<int, array<string, int|float|string>>,
     *     turmas_incompletas_por_escola: array<int, array<string, int|float|string>>,
     *     acompanhamento_turmas: array<int, array<string, int|float|string>>,
     *     acompanhamento_turmas_total: int
     * }
     */
    private function montarDashboardData(bool $incluirAcompanhamentoCompleto = false): array
    {
        $this->normalizarFiltros();

        if (! $this->avaliacaoSelecionada()) {
            return $this->dashboardVazio();
        }

        $avaliacaoIds = $this->obterIdsAvaliacoesFiltradas();

        $tabelaEscolas = $this->montarTabelaEscolas($avaliacaoIds);
        $totaisPreenchimento = $this->calcularTotaisPreenchimento($avaliacaoIds, $tabelaEscolas);
        $turnos = $this->calcularPreenchimentoPorTurno($avaliacaoIds);
        $acompanhamentoTurmas = $this->montarAcompanhamentoTurmas(
            $avaliacaoIds,
            paginar: ! $incluirAcompanhamentoCompleto
        );

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
            'preenchimento_por_componentes' => $this->montarPreenchimentoPorComponentes($avaliacaoIds),
            'preenchimento_por_series' => $this->montarPreenchimentoPorSeries($avaliacaoIds),
            'turmas_incompletas_por_escola' => $this->montarTurmasIncompletasPorEscola($tabelaEscolas),
            'acompanhamento_turmas' => $acompanhamentoTurmas['itens'],
            'acompanhamento_turmas_total' => $acompanhamentoTurmas['total'],
        ];
    }

    private function dashboardVazio(): array
    {
        return [
            'cards' => [
                'preenchimentos_esperados' => 0,
                'preenchimentos_respondidos' => 0,
                'preenchimentos_pendentes' => 0,
                'percentual_preenchimento_geral' => 0.0,
                'percentual_alunos_sem_resposta_pautas' => 0.0,
                'percentual_turmas_preenchidas' => 0.0,
                'turmas_esperadas' => 0,
                'turmas_preenchidas' => 0,
                'turmas_incompletas' => 0,
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
            'preenchimento_por_componentes' => [],
            'preenchimento_por_series' => [],
            'turmas_incompletas_por_escola' => [],
            'acompanhamento_turmas' => [],
            'acompanhamento_turmas_total' => 0,
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

        $query->whereHas('turmas', function (EloquentBuilder $turmaQuery): void {
            $this->aplicarEscopoEscolarEloquent($turmaQuery);
        });

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

        $query->whereHas('turma', function (EloquentBuilder $turmaQuery): void {
            $this->aplicarEscopoEscolarEloquent($turmaQuery);
        });

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

        $this->aplicarEscopoEscolarQuery($query, 't');

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

        $this->aplicarEscopoEscolarQuery($query, 't');

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

    /**
     * @return array<int, array<string, int|float|string>>
     */
    private function montarPreenchimentoPorComponentes(array $avaliacaoIds): array
    {
        if ($avaliacaoIds === []) {
            return [];
        }

        $distinctEsperado = $this->distinctCombinacaoExpr('at.avaliacao_id', 'at.turma_id', 'p.id', 'aln.id');
        $distinctRespondido = $this->distinctCombinacaoExpr('ar.avaliacao_id', 'ar.turma_id', 'ar.pauta_id', 'ar.aluno_id');

        $esperados = (clone $this->basePreenchimentosEsperadosQuery($avaliacaoIds))
            ->leftJoin('componentes_curriculares as cc', 'cc.id', '=', 'p.componente_curricular_id')
            ->groupBy('p.componente_curricular_id', 'cc.nome')
            ->groupByRaw('COALESCE(p.componente_curricular_id, 0)')
            ->groupByRaw("COALESCE(cc.nome, 'Componente geral')")
            ->selectRaw('COALESCE(p.componente_curricular_id, 0) as agrupamento_id')
            ->selectRaw("COALESCE(cc.nome, 'Componente geral') as nome")
            ->selectRaw("COUNT(DISTINCT {$distinctEsperado}) as preenchimentos_esperados")
            ->get()
            ->keyBy(fn ($item): int => (int) $item->agrupamento_id);

        $respondidos = (clone $this->baseRespostasQuery($avaliacaoIds, ignorarAlternativas: true))
            ->leftJoin('componentes_curriculares as cc', 'cc.id', '=', 'p.componente_curricular_id')
            ->groupBy('p.componente_curricular_id', 'cc.nome')
            ->groupByRaw('COALESCE(p.componente_curricular_id, 0)')
            ->groupByRaw("COALESCE(cc.nome, 'Componente geral')")
            ->selectRaw('COALESCE(p.componente_curricular_id, 0) as agrupamento_id')
            ->selectRaw("COALESCE(cc.nome, 'Componente geral') as nome")
            ->selectRaw("COUNT(DISTINCT {$distinctRespondido}) as preenchimentos_respondidos")
            ->get()
            ->keyBy(fn ($item): int => (int) $item->agrupamento_id);

        return $this->montarLinhasPreenchimentoAgrupado($esperados, $respondidos);
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    private function montarPreenchimentoPorSeries(array $avaliacaoIds): array
    {
        if ($avaliacaoIds === []) {
            return [];
        }

        $distinctEsperado = $this->distinctCombinacaoExpr('at.avaliacao_id', 'at.turma_id', 'p.id', 'aln.id');
        $distinctRespondido = $this->distinctCombinacaoExpr('ar.avaliacao_id', 'ar.turma_id', 'ar.pauta_id', 'ar.aluno_id');

        $esperados = (clone $this->basePreenchimentosEsperadosQuery($avaliacaoIds))
            ->leftJoin('series as s', 's.id', '=', 't.id_serie')
            ->groupBy('t.id_serie', 's.nome')
            ->groupByRaw('COALESCE(t.id_serie, 0)')
            ->groupByRaw("COALESCE(s.nome, 'Serie nao informada')")
            ->selectRaw('COALESCE(t.id_serie, 0) as agrupamento_id')
            ->selectRaw("COALESCE(s.nome, 'Serie nao informada') as nome")
            ->selectRaw("COUNT(DISTINCT {$distinctEsperado}) as preenchimentos_esperados")
            ->get()
            ->keyBy(fn ($item): int => (int) $item->agrupamento_id);

        $respondidos = (clone $this->baseRespostasQuery($avaliacaoIds, ignorarAlternativas: true))
            ->leftJoin('series as s', 's.id', '=', 't.id_serie')
            ->groupBy('t.id_serie', 's.nome')
            ->groupByRaw('COALESCE(t.id_serie, 0)')
            ->groupByRaw("COALESCE(s.nome, 'Serie nao informada')")
            ->selectRaw('COALESCE(t.id_serie, 0) as agrupamento_id')
            ->selectRaw("COALESCE(s.nome, 'Serie nao informada') as nome")
            ->selectRaw("COUNT(DISTINCT {$distinctRespondido}) as preenchimentos_respondidos")
            ->get()
            ->keyBy(fn ($item): int => (int) $item->agrupamento_id);

        return $this->montarLinhasPreenchimentoAgrupado($esperados, $respondidos);
    }

    /**
     * @param Collection<int, object> $esperados
     * @param Collection<int, object> $respondidos
     * @return array<int, array<string, int|float|string>>
     */
    private function montarLinhasPreenchimentoAgrupado(Collection $esperados, Collection $respondidos): array
    {
        $ids = $esperados
            ->keys()
            ->merge($respondidos->keys())
            ->unique()
            ->values();

        $linhas = $ids
            ->map(function (int $id) use ($esperados, $respondidos): array {
                $esperado = $esperados->get($id);
                $respondido = $respondidos->get($id);
                $preenchimentosEsperados = (int) ($esperado->preenchimentos_esperados ?? 0);
                $preenchimentosRespondidos = min(
                    (int) ($respondido->preenchimentos_respondidos ?? 0),
                    $preenchimentosEsperados
                );
                $preenchimentosPendentes = max($preenchimentosEsperados - $preenchimentosRespondidos, 0);

                return [
                    'id' => $id,
                    'nome' => (string) ($esperado->nome ?? $respondido->nome ?? '-'),
                    'preenchimentos_esperados' => $preenchimentosEsperados,
                    'preenchimentos_respondidos' => $preenchimentosRespondidos,
                    'preenchimentos_pendentes' => $preenchimentosPendentes,
                    'percentual_preenchimento' => $preenchimentosEsperados > 0
                        ? round(($preenchimentosRespondidos / $preenchimentosEsperados) * 100, 1)
                        : 0.0,
                ];
            })
            ->all();

        usort($linhas, function (array $a, array $b): int {
            if ($a['percentual_preenchimento'] === $b['percentual_preenchimento']) {
                return strcmp((string) $a['nome'], (string) $b['nome']);
            }

            return $a['percentual_preenchimento'] <=> $b['percentual_preenchimento'];
        });

        return $linhas;
    }

    private function calcularTotaisPreenchimento(array $avaliacaoIds, array $tabelaEscolas): array
    {
        $esperados = (int) collect($tabelaEscolas)->sum('preenchimentos_esperados');
        $respondidos = min((int) collect($tabelaEscolas)->sum('preenchimentos_respondidos'), $esperados);
        $pendentes = max($esperados - $respondidos, 0);
        $turmasEsperadas = (int) collect($tabelaEscolas)->sum('turmas_esperadas');
        $turmasPreenchidas = (int) collect($tabelaEscolas)->sum('turmas_preenchidas');
        $turmasIncompletas = max($turmasEsperadas - $turmasPreenchidas, 0);

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
            'percentual_preenchimento_geral' => $esperados > 0
                ? round(($respondidos / $esperados) * 100, 1)
                : 0.0,
            'percentual_alunos_sem_resposta_pautas' => $esperados > 0
                ? round(($pendentes / $esperados) * 100, 1)
                : 0.0,
            'percentual_turmas_preenchidas' => $turmasEsperadas > 0
                ? round(($turmasPreenchidas / $turmasEsperadas) * 100, 1)
                : 0.0,
            'turmas_esperadas' => $turmasEsperadas,
            'turmas_preenchidas' => $turmasPreenchidas,
            'turmas_incompletas' => $turmasIncompletas,
            'percentual_escolas_preenchidas' => $totalEscolas > 0
                ? round(($escolasPreenchidas / $totalEscolas) * 100, 1)
                : 0.0,
            'total_escolas' => $totalEscolas,
            'escolas_preenchidas' => $escolasPreenchidas,
            'escolas_nao_preenchidas' => $escolasNaoPreenchidas,
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

    private function montarTabelaEscolas(array $avaliacaoIds): array
    {
        if ($avaliacaoIds === []) {
            return [];
        }

        $distinctExprEsperado = $this->distinctCombinacaoExpr('at.avaliacao_id', 'at.turma_id', 'p.id', 'aln.id');
        $distinctExprRespondido = $this->distinctCombinacaoExpr('ar.avaliacao_id', 'ar.turma_id', 'ar.pauta_id', 'ar.aluno_id');

        $esperadas = (clone $this->basePreenchimentosEsperadosQuery($avaliacaoIds))
            ->join('escolas as e', 'e.id', '=', 't.id_escola')
            ->groupBy('e.id', 'e.nome', 'at.avaliacao_id', 'at.turma_id')
            ->select(
                'e.id',
                'e.nome',
                'at.avaliacao_id',
                'at.turma_id',
                DB::raw("COUNT(DISTINCT {$distinctExprEsperado}) as preenchimentos_esperados")
            )
            ->get();

        $respondidas = (clone $this->baseRespostasQuery($avaliacaoIds, ignorarAlternativas: true))
            ->groupBy('ar.avaliacao_id', 'ar.turma_id')
            ->select(
                'ar.avaliacao_id',
                'ar.turma_id',
                DB::raw("COUNT(DISTINCT {$distinctExprRespondido}) as preenchimentos_respondidos")
            )
            ->get()
            ->keyBy(fn ($item): string => $item->avaliacao_id . ':' . $item->turma_id);

        $escolasFiltradas = $this->filtros['escolas_ids'] !== []
            ? Escola::query()
                ->where('ativo', true)
                ->whereIn('id', $this->filtros['escolas_ids'])
                ->orderBy('nome')
                ->get(['id', 'nome'])
            : collect();

        /** @var array<int, array<string, int|float|string|bool>> $linhas */
        $linhasPorEscola = [];

        foreach ($esperadas as $esperada) {
            $escolaId = (int) $esperada->id;
            $chaveTurma = $esperada->avaliacao_id . ':' . $esperada->turma_id;
            $respondida = $respondidas->get($chaveTurma);

            if (! array_key_exists($escolaId, $linhasPorEscola)) {
                $linhasPorEscola[$escolaId] = [
                    'id' => $escolaId,
                    'nome' => (string) $esperada->nome,
                    'preenchimentos_esperados' => 0,
                    'preenchimentos_respondidos' => 0,
                    'preenchimentos_pendentes' => 0,
                    'percentual_preenchimento' => 0.0,
                    'percentual_pendentes' => 0.0,
                    'turmas_esperadas' => 0,
                    'turmas_preenchidas' => 0,
                    'turmas_incompletas' => 0,
                    'turmas_com_resposta' => 0,
                    'respostas_total' => 0,
                    'percentual_turmas' => 0.0,
                    'percentual_turmas_incompletas' => 0.0,
                    'esta_preenchida' => false,
                ];
            }

            $preenchimentosEsperados = (int) ($esperada->preenchimentos_esperados ?? 0);
            $preenchimentosRespondidos = min((int) ($respondida->preenchimentos_respondidos ?? 0), $preenchimentosEsperados);

            $linhasPorEscola[$escolaId]['preenchimentos_esperados'] += $preenchimentosEsperados;
            $linhasPorEscola[$escolaId]['preenchimentos_respondidos'] += $preenchimentosRespondidos;
            $linhasPorEscola[$escolaId]['turmas_esperadas']++;
            $linhasPorEscola[$escolaId]['respostas_total'] += $preenchimentosRespondidos;

            if ($preenchimentosRespondidos > 0) {
                $linhasPorEscola[$escolaId]['turmas_com_resposta']++;
            }

            if ($preenchimentosEsperados > 0 && $preenchimentosRespondidos >= $preenchimentosEsperados) {
                $linhasPorEscola[$escolaId]['turmas_preenchidas']++;
            } else {
                $linhasPorEscola[$escolaId]['turmas_incompletas']++;
            }
        }

        $linhas = array_values(array_map(function (array $item): array {
            $preenchimentosEsperados = (int) $item['preenchimentos_esperados'];
            $preenchimentosRespondidos = min((int) $item['preenchimentos_respondidos'], $preenchimentosEsperados);
            $preenchimentosPendentes = max($preenchimentosEsperados - $preenchimentosRespondidos, 0);
            $turmasEsperadas = (int) $item['turmas_esperadas'];
            $turmasPreenchidas = (int) $item['turmas_preenchidas'];
            $turmasIncompletas = (int) $item['turmas_incompletas'];

            return [
                ...$item,
                'preenchimentos_respondidos' => $preenchimentosRespondidos,
                'preenchimentos_pendentes' => $preenchimentosPendentes,
                'percentual_preenchimento' => $preenchimentosEsperados > 0
                    ? round(($preenchimentosRespondidos / $preenchimentosEsperados) * 100, 1)
                    : 0.0,
                'percentual_pendentes' => $preenchimentosEsperados > 0
                    ? round(($preenchimentosPendentes / $preenchimentosEsperados) * 100, 1)
                    : 0.0,
                'percentual_turmas' => $turmasEsperadas > 0
                    ? round(($turmasPreenchidas / $turmasEsperadas) * 100, 1)
                    : 0.0,
                'percentual_turmas_incompletas' => $turmasEsperadas > 0
                    ? round(($turmasIncompletas / $turmasEsperadas) * 100, 1)
                    : 0.0,
                'esta_preenchida' => $turmasEsperadas > 0 && $turmasIncompletas === 0,
            ];
        }, $linhasPorEscola));

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
                'turmas_preenchidas' => 0,
                'turmas_incompletas' => 0,
                'respostas_total' => 0,
                'percentual_turmas' => 0.0,
                'percentual_turmas_incompletas' => 0.0,
                'esta_preenchida' => false,
            ];
        }

        usort($linhas, function (array $a, array $b): int {
            if ($a['turmas_incompletas'] === $b['turmas_incompletas']) {
                if ($a['percentual_turmas_incompletas'] === $b['percentual_turmas_incompletas']) {
                    return strcmp((string) $a['nome'], (string) $b['nome']);
                }

                return $b['percentual_turmas_incompletas'] <=> $a['percentual_turmas_incompletas'];
            }

            return $b['turmas_incompletas'] <=> $a['turmas_incompletas'];
        });

        return array_map(function (array $item): array {
            return [
                ...$item,
                'percentual_turmas' => round((float) $item['percentual_turmas'], 1),
                'percentual_turmas_incompletas' => round((float) $item['percentual_turmas_incompletas'], 1),
                'percentual_preenchimento' => round((float) $item['percentual_preenchimento'], 1),
                'percentual_pendentes' => round((float) $item['percentual_pendentes'], 1),
            ];
        }, $linhas);
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    private function montarTurmasIncompletasPorEscola(array $tabelaEscolas): array
    {
        return collect($tabelaEscolas)
            ->filter(fn (array $item): bool => (int) ($item['turmas_esperadas'] ?? 0) > 0)
            ->sortByDesc(fn (array $item): int => (int) ($item['turmas_incompletas'] ?? 0))
            ->take(12)
            ->map(function (array $item): array {
                $percentual = (float) ($item['percentual_turmas_incompletas'] ?? 0);

                return [
                    'id' => (int) ($item['id'] ?? 0),
                    'nome' => (string) ($item['nome'] ?? '-'),
                    'total' => (int) ($item['turmas_incompletas'] ?? 0),
                    'esperadas' => (int) ($item['turmas_esperadas'] ?? 0),
                    'percentual' => round($percentual, 1),
                    'percentual_barra' => round($percentual, 1),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{itens: array<int, array<string, int|float|string>>, total: int}
     */
    private function montarAcompanhamentoTurmas(array $avaliacaoIds, bool $paginar = true): array
    {
        return $this->montarAcompanhamentoTurmasPaginado($avaliacaoIds, $paginar);
    }

    /**
     * @return array<string, int|float|string>|null
     */
    private function localizarLinhaAcompanhamento(
        int $avaliacaoId,
        int $turmaId,
        int $escolaId,
        int $serieId,
        ?int $componenteId = null,
        ?int $professorId = null
    ): ?array {
        if (! $this->linhaAcompanhamentoPermaneceNoEscopo($avaliacaoId, $turmaId, $escolaId, $serieId)) {
            return null;
        }

        return collect($this->acompanhamentoTurmas)->first(function (array $item) use (
            $avaliacaoId,
            $turmaId,
            $escolaId,
            $serieId,
            $componenteId,
            $professorId
        ): bool {
            if (
                (int) ($item['avaliacao_id'] ?? 0) !== $avaliacaoId
                || (int) ($item['turma_id'] ?? 0) !== $turmaId
                || (int) ($item['escola_id'] ?? 0) !== $escolaId
                || (int) ($item['serie_id'] ?? 0) !== $serieId
            ) {
                return false;
            }

            $itemComponenteId = (int) ($item['componente_id'] ?? 0);
            $itemProfessorId = (int) ($item['professor_id'] ?? 0);

            return $itemComponenteId === max((int) ($componenteId ?? 0), 0)
                && $itemProfessorId === max((int) ($professorId ?? 0), 0);
        });
    }

    private function linhaAcompanhamentoPermaneceNoEscopo(int $avaliacaoId, int $turmaId, int $escolaId, int $serieId): bool
    {
        return Avaliacao::query()
            ->whereKey($avaliacaoId)
            ->whereHas('turmas', function (EloquentBuilder $turmaQuery) use ($turmaId, $escolaId, $serieId): void {
                $turmaQuery
                    ->whereKey($turmaId)
                    ->where('id_escola', $escolaId)
                    ->where('id_serie', $serieId);

                $this->aplicarEscopoEscolarEloquent($turmaQuery);
            })
            ->exists();
    }

    /**
     * @return array{itens: array<int, array<string, int|float|string>>, total: int}
     */
    private function montarAcompanhamentoTurmasPaginado(array $avaliacaoIds, bool $paginar): array
    {
        if ($avaliacaoIds === []) {
            if ($paginar) {
                $this->normalizarPaginaAcompanhamentoTurmas(0);
            }

            return ['itens' => [], 'total' => 0];
        }

        $filtros = $this->filtrosDoAcompanhamento();
        $professoresIds = $filtros['professores_ids'] ?? [];
        $distinctEsperado = $this->distinctCombinacaoExpr('at.avaliacao_id', 'at.turma_id', 'p.id', 'aln.id');
        $distinctRespondido = $this->distinctCombinacaoExpr('ar.avaliacao_id', 'ar.turma_id', 'ar.pauta_id', 'ar.aluno_id');
        $professorRespostaExpr = $professoresIds !== []
            ? 'ar.professor_id'
            : 'CASE WHEN p.componente_curricular_id IS NULL THEN NULL ELSE ar.professor_id END';
        $componenteChaveExpr = 'COALESCE(p.componente_curricular_id, 0)';
        $professorChaveExpr = 'COALESCE(tcp_acomp.professor_id, 0)';
        $professorRespostaChaveExpr = "COALESCE({$professorRespostaExpr}, 0)";

        $esperadosQuery = (clone $this->basePreenchimentosEsperadosQuery($avaliacaoIds))
            ->join('avaliacoes as av', 'av.id', '=', 'at.avaliacao_id')
            ->leftJoin('escolas as e', 'e.id', '=', 't.id_escola')
            ->leftJoin('series as s', 's.id', '=', 't.id_serie')
            ->leftJoin('componentes_curriculares as cc', 'cc.id', '=', 'p.componente_curricular_id')
            ->leftJoin('turma_componente_professor as tcp_acomp', function ($join) use ($professoresIds): void {
                $join->on('tcp_acomp.turma_id', '=', 't.id');

                if ($professoresIds !== []) {
                    $join->where(function ($join): void {
                        $join->whereNull('p.componente_curricular_id')
                            ->orOn('tcp_acomp.componente_curricular_id', '=', 'p.componente_curricular_id');
                    });

                    return;
                }

                $join->on('tcp_acomp.componente_curricular_id', '=', 'p.componente_curricular_id');
            })
            ->leftJoin('professores as pr_acomp', 'pr_acomp.id', '=', 'tcp_acomp.professor_id');

        if ($professoresIds !== []) {
            $esperadosQuery->whereIn('tcp_acomp.professor_id', $professoresIds);
        }

        $esperadosQuery
            ->groupBy(
                'at.avaliacao_id',
                'at.turma_id',
                'av.nome',
                't.nome',
                't.turno',
                'e.id',
                'e.nome',
                's.id',
                's.nome',
                'p.componente_curricular_id',
                'cc.nome',
                'tcp_acomp.professor_id',
                'pr_acomp.nome'
            )
            ->groupByRaw($componenteChaveExpr)
            ->groupByRaw($professorChaveExpr)
            ->select(
                'at.avaliacao_id',
                'at.turma_id',
                'av.nome as avaliacao_nome',
                'e.id as escola_id',
                't.nome as turma_nome',
                't.turno',
                'e.nome as escola_nome',
                's.id as serie_id',
                's.nome as serie_nome',
                'p.componente_curricular_id as componente_id',
                'cc.nome as componente_nome',
                'tcp_acomp.professor_id',
                'pr_acomp.nome as professor_nome',
                DB::raw("{$componenteChaveExpr} as componente_chave"),
                DB::raw("{$professorChaveExpr} as professor_chave"),
                DB::raw("COUNT(DISTINCT {$distinctEsperado}) as preenchimentos_esperados"),
                DB::raw('COUNT(DISTINCT p.id) as pautas_total'),
                DB::raw('COUNT(DISTINCT aln.id) as alunos_total')
            );

        $respondidosQuery = (clone $this->baseRespostasQuery($avaliacaoIds, ignorarAlternativas: true))
            ->groupBy('ar.avaliacao_id', 'ar.turma_id', 'p.componente_curricular_id')
            ->groupByRaw('COALESCE(p.componente_curricular_id, 0)')
            ->groupByRaw($professorRespostaChaveExpr)
            ->select(
                'ar.avaliacao_id',
                'ar.turma_id',
                DB::raw('COALESCE(p.componente_curricular_id, 0) as componente_chave'),
                DB::raw("{$professorRespostaChaveExpr} as professor_chave"),
                DB::raw("COUNT(DISTINCT {$distinctRespondido}) as preenchimentos_respondidos"),
                DB::raw('MAX(ar.respondido_em) as ultima_resposta_em')
            );

        $query = DB::query()
            ->fromSub($esperadosQuery, 'esperados')
            ->leftJoinSub($respondidosQuery, 'respondidos', function ($join): void {
                $join->on('respondidos.avaliacao_id', '=', 'esperados.avaliacao_id')
                    ->on('respondidos.turma_id', '=', 'esperados.turma_id')
                    ->on('respondidos.componente_chave', '=', 'esperados.componente_chave')
                    ->on('respondidos.professor_chave', '=', 'esperados.professor_chave');
            })
            ->select(
                'esperados.*',
                DB::raw('COALESCE(respondidos.preenchimentos_respondidos, 0) as preenchimentos_respondidos'),
                'respondidos.ultima_resposta_em'
            );

        $total = (int) DB::query()
            ->fromSub(clone $query, 'acompanhamento')
            ->count();

        if ($paginar) {
            $this->normalizarPaginaAcompanhamentoTurmas($total);
            $query->forPage($this->acompanhamentoTurmasPagina, $this->acompanhamentoTurmasPorPagina);
        }

        $dados = $query
            ->orderBy('esperados.escola_nome')
            ->orderBy('esperados.serie_nome')
            ->orderBy('esperados.turma_nome')
            ->orderBy('esperados.componente_nome')
            ->orderBy('esperados.professor_nome')
            ->get();

        $itens = $dados
            ->map(function ($item): array {
                $preenchimentosEsperados = (int) ($item->preenchimentos_esperados ?? 0);
                $preenchimentosRespondidos = min((int) ($item->preenchimentos_respondidos ?? 0), $preenchimentosEsperados);
                $preenchimentosPendentes = max($preenchimentosEsperados - $preenchimentosRespondidos, 0);
                $percentualPreenchimento = $preenchimentosEsperados > 0
                    ? round(($preenchimentosRespondidos / $preenchimentosEsperados) * 100, 1)
                    : 0.0;
                $status = match (true) {
                    $preenchimentosEsperados > 0 && $preenchimentosPendentes === 0 => 'concluido',
                    $preenchimentosRespondidos > 0 => 'em_andamento',
                    default => 'nao_iniciado',
                };

                return [
                    'avaliacao_id' => (int) $item->avaliacao_id,
                    'turma_id' => (int) $item->turma_id,
                    'escola_id' => (int) ($item->escola_id ?? 0),
                    'serie_id' => (int) ($item->serie_id ?? 0),
                    'componente_id' => (int) ($item->componente_id ?? 0),
                    'professor_id' => (int) ($item->professor_id ?? 0),
                    'avaliacao_nome' => (string) ($item->avaliacao_nome ?? '-'),
                    'escola_nome' => (string) ($item->escola_nome ?? '-'),
                    'serie_nome' => (string) ($item->serie_nome ?? '-'),
                    'turma_nome' => (string) ($item->turma_nome ?? '-'),
                    'turno' => (string) ($item->turno ?? '-'),
                    'componente_nome' => (string) ($item->componente_nome ?? 'Componente geral'),
                    'professor_nome' => (string) ($item->professor_nome ?? 'Professor nao vinculado'),
                    'preenchimentos_esperados' => $preenchimentosEsperados,
                    'preenchimentos_respondidos' => $preenchimentosRespondidos,
                    'preenchimentos_pendentes' => $preenchimentosPendentes,
                    'percentual_preenchimento' => $percentualPreenchimento,
                    'pautas_total' => (int) ($item->pautas_total ?? 0),
                    'alunos_total' => (int) ($item->alunos_total ?? 0),
                    'status' => $status,
                    'status_label' => match ($status) {
                        'concluido' => 'Concluido',
                        'em_andamento' => 'Em andamento',
                        default => 'Nao iniciado',
                    },
                    'ultima_resposta' => $item->ultima_resposta_em
                        ? \Illuminate\Support\Carbon::parse($item->ultima_resposta_em)->format('d/m/Y H:i')
                        : '-',
                ];
            })
            ->values()
            ->all();

        return ['itens' => $itens, 'total' => $total];
    }

    private function aplicarFiltrosTurmaQuery(QueryBuilder $query, string $alias = 't', ?array $filtros = null): void
    {
        $filtrosAtivos = $filtros ?? $this->filtros;

        $this->aplicarEscopoEscolarQuery($query, $alias);

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
        $this->filtros['escolas_ids'] = $this->filtrarEscolasPermitidas(
            $this->normalizarArrayIds($this->filtros['escolas_ids'] ?? [])
        );
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

    private function normalizarFiltrosAcompanhamento(): void
    {
        $this->filtrosAcompanhamento['series_ids'] = $this->normalizarArrayIds($this->filtrosAcompanhamento['series_ids'] ?? []);
        $this->filtrosAcompanhamento['componentes_ids'] = $this->normalizarArrayIds($this->filtrosAcompanhamento['componentes_ids'] ?? []);
        $this->filtrosAcompanhamento['escolas_ids'] = $this->filtrarEscolasPermitidas(
            $this->normalizarArrayIds($this->filtrosAcompanhamento['escolas_ids'] ?? [])
        );

        $turnosPermitidos = array_keys($this->turnosOptions);
        $this->filtrosAcompanhamento['turnos'] = collect($this->filtrosAcompanhamento['turnos'] ?? [])
            ->map(fn ($turno): string => (string) $turno)
            ->filter(fn (string $turno): bool => in_array($turno, $turnosPermitidos, true))
            ->unique()
            ->values()
            ->all();
    }

    private function filtrosDoAcompanhamento(): array
    {
        return [
            ...$this->filtros,
            'series_ids' => $this->filtrosAcompanhamento['series_ids'] ?? [],
            'turnos' => $this->filtrosAcompanhamento['turnos'] ?? [],
            'componentes_ids' => $this->filtrosAcompanhamento['componentes_ids'] ?? [],
            'escolas_ids' => $this->filtrosAcompanhamento['escolas_ids'] ?? [],
        ];
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

    /**
     * @return array<string, mixed>
     */
    private function filtrosAcompanhamentoPadrao(): array
    {
        return [
            'series_ids' => [],
            'turnos' => [],
            'componentes_ids' => [],
            'escolas_ids' => [],
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
