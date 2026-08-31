<?php

namespace App\Filament\Admin\Pages\Relatorios;

use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\User;
use App\Services\Avaliacoes\AvaliacaoDashboardOnDemandQueryService;
use App\Services\Avaliacoes\AvaliacaoDocumentoExportService;
use App\Services\Avaliacoes\AvaliacaoMigracaoLazyService;
use App\Services\Avaliacoes\AvaliacaoPersistencia;
use App\Services\Avaliacoes\AvaliacaoSnapshotService;
use App\Services\Exports\ExportRequestService;
use App\Services\PessoaScopeService;
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
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;
use UnitEnum;

class DashboardAvaliacoes extends Page implements HasForms
{
    use InteractsWithForms;

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

    public bool $workspaceAcompanhamentoTemAlteracoes = false;

    /** @var array<string, array{percentual: float, status: string, respondidos: int}> */
    public array $acompanhamentoSnapshot = [];

    /** @var array<int, string> chaves de linhas alteradas no último refresh incremental */
    public array $acompanhamentoLinhasAlteradas = [];

    public string $ultimaAtualizacaoIncremental = '';

    public function avisoFiltrosHistoricos(): ?string
    {
        if (! app(AvaliacaoPersistencia::class)->leRelacional()) {
            return null;
        }

        $usaFiltroDetalhado = ($this->filtros['professores_ids'] ?? []) !== []
            || ($this->filtros['pautas_ids'] ?? []) !== []
            || ($this->filtros['alternativas_ids'] ?? []) !== [];
        $avaliacaoId = (int) ($this->filtros['avaliacao_id'] ?? 0);

        if (! $usaFiltroDetalhado || $avaliacaoId <= 0) {
            return null;
        }

        $possuiHistorico = DB::table('avaliacao_turma_ciclos')
            ->where('avaliacao_id', $avaliacaoId)
            ->where('status', 'concluida')
            ->exists();

        return $possuiHistorico
            ? 'Filtros por professor, pauta e alternativa são aplicados somente às turmas abertas. O histórico concluído permanece resumido por turma e componente.'
            : null;
    }

    public array $listagensPaginas = [
        'tabelaEscolas' => 1,
    ];

    public array $listagensPorPagina = [
        'tabelaEscolas' => 5,
    ];

    public array $listagensPorPaginaOptions = [5, 10, 25, 50, 100];

    public array $filtrosAplicados = [];

    public string $ultimaAtualizacao = '';

    public bool $dashboardCarregado = false;

    public bool $resumoCarregado = false;

    public bool $graficosCarregados = false;

    public bool $acompanhamentoCarregado = false;

    /**
     * @var array<string, array{diretor: string, coordenacao: string, tem_diretor: bool, tem_coordenacao: bool, pode_exportar: bool, motivo_bloqueio: string}>
     */
    private array $parecerTurmaElegibilidade = [];

    public function mount(): void
    {
        $this->filtros = $this->filtrosPadrao();
        $this->filtrosAcompanhamento = $this->filtrosAcompanhamentoPadrao();

        $avaliacaoId = $this->avaliacaoIdInicialDaUrl();

        if ($avaliacaoId) {
            $this->filtros['avaliacao_id'] = $avaliacaoId;
        }

        $this->aplicarDashboardData($this->dashboardVazio());
        $this->dashboardCarregado = ! $this->avaliacaoSelecionada();
    }

    public function carregarDashboardInicial(): void
    {
        if ($this->dashboardCarregado) {
            return;
        }

        $this->carregarResumoDashboard();
    }

    public function carregarResumoDashboard(): void
    {
        $this->dashboardCarregado = false;
        $this->normalizarFiltros();
        $this->normalizarFiltrosAcompanhamento();

        if (! $this->avaliacaoSelecionada()) {
            $this->aplicarDashboardData($this->dashboardVazio());
            $this->dashboardCarregado = true;

            return;
        }

        $resumo = Cache::remember(
            $this->dashboardCacheKey('resumo'),
            now()->addSeconds($this->dashboardCacheTtl()),
            function (): array {
                $avaliacaoIds = $this->obterIdsAvaliacoesFiltradas();
                $tabelaEscolas = $this->montarTabelaEscolas($avaliacaoIds);

                return [
                    'tabela_escolas' => $tabelaEscolas,
                    'totais' => $this->calcularTotaisPreenchimento($avaliacaoIds, $tabelaEscolas),
                    'turnos' => $this->calcularPreenchimentoPorTurno($avaliacaoIds),
                ];
            }
        );
        $tabelaEscolas = $resumo['tabela_escolas'];
        $totais = $resumo['totais'];
        $turnos = $resumo['turnos'];

        $this->cards = [
            ...$totais,
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
        ];
        $this->tabelaEscolas = $tabelaEscolas;
        $this->turmasIncompletasPorEscola = $this->montarTurmasIncompletasPorEscola($tabelaEscolas);
        $this->preenchimentoPorComponentes = [];
        $this->preenchimentoPorSeries = [];
        $this->acompanhamentoTurmas = [];
        $this->acompanhamentoTurmasTotal = 0;
        $this->resumoCarregado = true;
        $this->graficosCarregados = false;
        $this->acompanhamentoCarregado = false;
        $this->filtrosAplicados = $this->filtrosAplicadosFormatados();
        $this->dashboardCarregado = true;
    }

    public function carregarGraficosDashboard(): void
    {
        if (! $this->avaliacaoSelecionada()) {
            return;
        }

        $this->normalizarFiltros();
        $graficos = Cache::remember(
            $this->dashboardCacheKey('graficos'),
            now()->addSeconds($this->dashboardCacheTtl()),
            function (): array {
                $avaliacaoIds = $this->obterIdsAvaliacoesFiltradas();

                return [
                    'componentes' => $this->montarPreenchimentoPorComponentes($avaliacaoIds),
                    'series' => $this->montarPreenchimentoPorSeries($avaliacaoIds),
                ];
            }
        );
        $this->preenchimentoPorComponentes = $graficos['componentes'];
        $this->preenchimentoPorSeries = $graficos['series'];
        $this->graficosCarregados = true;
    }

    public function carregarAcompanhamentoDashboard(): void
    {
        $acompanhamento = Cache::remember(
            $this->dashboardCacheKey('acompanhamento'),
            now()->addSeconds($this->dashboardCacheTtl()),
            function (): array {
                $avaliacaoIds = $this->obterIdsAvaliacoesFiltradas();

                return $this->montarAcompanhamentoTurmas($avaliacaoIds, paginar: true);
            }
        );
        $this->acompanhamentoTurmas = $acompanhamento['itens'];
        $this->acompanhamentoTurmasTotal = $acompanhamento['total'];
        $this->acompanhamentoCarregado = true;
    }

    public function updatedFiltros(mixed $value = null, ?string $key = null): void
    {
        $this->resetarPaginacoesDashboard();
        $this->fecharWorkspaceAcompanhamento();

        if ($key === 'avaliacao_id') {
            $this->limparFiltrosDependentes();
            $this->filtrosAcompanhamento = $this->filtrosAcompanhamentoPadrao();
        }

        $this->carregarResumoDashboard();
        $this->dispatch('dashboard-detalhes-recarregar');
    }

    public function updatedFiltrosAvaliacaoId(): void
    {
        $this->resetarPaginacoesDashboard();
        $this->fecharWorkspaceAcompanhamento();
        $this->limparFiltrosDependentes();
        $this->filtrosAcompanhamento = $this->filtrosAcompanhamentoPadrao();
        $this->carregarResumoDashboard();
        $this->dispatch('dashboard-detalhes-recarregar');
    }

    public function limparFiltros(): void
    {
        $this->filtros = $this->filtrosPadrao();
        $this->filtrosAcompanhamento = $this->filtrosAcompanhamentoPadrao();
        $this->resetarPaginacoesDashboard();
        $this->fecharWorkspaceAcompanhamento();
        $this->carregarResumoDashboard();
        $this->dispatch('dashboard-detalhes-recarregar');
    }

    public function updatedFiltrosAcompanhamento(): void
    {
        $this->workspaceAcompanhamentoAberto = false;
        $this->workspaceAcompanhamentoLinha = null;
        $this->acompanhamentoLinhasAlteradas = [];
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
        $this->workspaceAcompanhamentoTemAlteracoes = false;
        $this->workspaceAcompanhamentoKey++;
    }

    public function fecharWorkspaceAcompanhamento(): void
    {
        $deveRecarregarAcompanhamento = $this->workspaceAcompanhamentoTemAlteracoes;

        $this->workspaceAcompanhamentoAberto = false;
        $this->workspaceAcompanhamentoLinha = null;
        $this->workspaceAcompanhamentoTemAlteracoes = false;

        if ($deveRecarregarAcompanhamento) {
            $this->dispatch('dashboard-acompanhamento-recarregar');
        }
    }

    public function getPodeConcluirParecerWorkspaceProperty(): bool
    {
        $linha = $this->workspaceAcompanhamentoLinha;

        if (! $this->workspaceAcompanhamentoAberto
            || ! is_array($linha)
            || ($linha['status'] ?? null) !== 'concluido'
            || ! Gate::allows('conclude', Avaliacao::class)) {
            return false;
        }

        $ciclo = AvaliacaoTurmaCiclo::query()
            ->where('avaliacao_id', (int) ($linha['avaliacao_id'] ?? 0))
            ->where('turma_avaliativa_id', (int) ($linha['turma_id'] ?? 0))
            ->first();

        return $ciclo?->status !== AvaliacaoTurmaCiclo::STATUS_CONCLUIDA;
    }

    public function concluirParecerTurma(): void
    {
        abort_unless(static::canAccess(), 403);

        if (! $this->podeConcluirParecerWorkspace) {
            Notification::make()
                ->title('Esta avaliação não pode ser concluída neste momento.')
                ->warning()
                ->send();

            return;
        }

        $linha = $this->workspaceAcompanhamentoLinha;
        $linhaAtual = $this->localizarLinhaAcompanhamento(
            (int) $linha['avaliacao_id'],
            (int) $linha['turma_id'],
            (int) $linha['escola_id'],
            (int) $linha['serie_id'],
            (int) ($linha['componente_id'] ?? 0),
            (int) ($linha['professor_id'] ?? 0),
        );

        if ($linhaAtual === null || ($linhaAtual['status'] ?? null) !== 'concluido') {
            Notification::make()
                ->title('A turma não está mais completa ou saiu do seu escopo.')
                ->warning()
                ->send();

            return;
        }

        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user !== null, 403);

        try {
            $ciclo = app(AvaliacaoMigracaoLazyService::class)->garantirTurma(
                (int) $linhaAtual['avaliacao_id'],
                (int) $linhaAtual['turma_id'],
            );
            app(AvaliacaoSnapshotService::class)->concluir($ciclo, $user);

            $this->workspaceAcompanhamentoLinha = $linhaAtual;
            $this->workspaceAcompanhamentoTemAlteracoes = true;
            $this->workspaceAcompanhamentoKey++;
            $this->parecerTurmaElegibilidade = [];

            Notification::make()
                ->title('Avaliação da turma concluída.')
                ->body('As respostas foram convertidas para o snapshot JSON final.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Não foi possível concluir a avaliação da turma.')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function exportarParecerTurma(
        int $avaliacaoId,
        int $turmaId,
        int $escolaId,
        int $serieId,
        ?int $componenteId = null,
        ?int $professorId = null
    ): void {
        abort_unless(static::canAccess(), 403);

        if (! $this->podeExportarParecer) {
            Notification::make()
                ->title('Você não tem permissão para exportar pareceres.')
                ->warning()
                ->send();

            return;
        }

        $linha = $this->localizarLinhaAcompanhamento(
            $avaliacaoId,
            $turmaId,
            $escolaId,
            $serieId,
            $componenteId,
            $professorId
        );

        if ($linha === null) {
            Notification::make()
                ->title('A avaliação selecionada não está mais disponível no seu escopo.')
                ->warning()
                ->send();

            return;
        }

        if (($linha['status'] ?? null) !== 'concluido') {
            Notification::make()
                ->title('O parecer só pode ser exportado quando a turma estiver concluída.')
                ->warning()
                ->send();

            return;
        }

        $turma = Turma::query()->find($turmaId);

        if (! $turma) {
            Notification::make()
                ->title('A turma selecionada não está mais disponível para exportação.')
                ->warning()
                ->send();

            return;
        }

        $documentoService = app(AvaliacaoDocumentoExportService::class);
        $gestores = $documentoService->gestoresDaTurma($turma, $avaliacaoId);

        if (! $gestores['pode_exportar']) {
            Notification::make()
                ->title($gestores['motivo_bloqueio'])
                ->warning()
                ->send();

            return;
        }

        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        try {
            $filtrosExportacao = [
                'avaliacao_id' => $avaliacaoId,
                'escopo' => 'turma',
                'turma_id' => $turmaId,
            ];
            $exportRequest = app(ExportRequestService::class)->queue(
                user: $user,
                type: 'avaliacao_documento',
                format: 'pdf',
                filters: $filtrosExportacao,
                label: 'Documento de avaliação',
                metadata: ['route' => 'filament.admin.pages.dashboard-avaliacoes'],
            );

            Notification::make()
                ->title($exportRequest->wasRecentlyCreated ? 'Exportação enviada para a fila' : 'Exportação já está em andamento')
                ->body('Acompanhe o progresso pelo ícone de downloads no topo.')
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Não foi possível iniciar a exportação')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function atualizarWorkspaceAcompanhamento(): void
    {
        $this->atualizarAcompanhamentoTurmas();
    }

    /**
     * Atualiza primeiro o resumo e agenda gráficos/acompanhamento em requisições
     * separadas. A avaliação 3 excede o timeout quando tudo é recalculado no
     * mesmo ciclo Livewire.
     */
    public function atualizarDadosRecentes(bool $silencioso = false): void
    {
        abort_unless(static::canAccess(), 403);

        if (! $this->avaliacaoSelecionada()) {
            if (! $silencioso) {
                Notification::make()
                    ->title('Selecione uma avaliação para atualizar os dados.')
                    ->warning()
                    ->send();
            }

            return;
        }

        $this->parecerTurmaElegibilidade = [];
        $this->acompanhamentoLinhasAlteradas = [];
        $this->limparDashboardCache();
        $this->carregarResumoDashboard();
        $this->ultimaAtualizacaoIncremental = now()->format('d/m/Y H:i:s');
        $this->ultimaAtualizacao = $this->ultimaAtualizacaoIncremental;
        $this->dispatch('dashboard-detalhes-recarregar');

        if ($silencioso) {
            return;
        }

        Notification::make()
            ->title('Dados atuais carregados.')
            ->body('Gráficos e acompanhamento estão sendo atualizados em etapas.')
            ->success()
            ->send();
    }

    #[On('workspace-acompanhamento-alterado')]
    public function marcarWorkspaceAcompanhamentoComoAlterado(): void
    {
        $this->workspaceAcompanhamentoTemAlteracoes = true;
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
            ->statePath('filtros');
    }

    public function filtrosAcompanhamentoForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('escola_id')
                    ->label('Escola')
                    ->options(fn (): array => $this->escolasOptions)
                    ->placeholder('Todas')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->disabled(fn (): bool => ! $this->avaliacaoSelecionada())
                    ->live(),
                Select::make('serie_id')
                    ->label('Série')
                    ->options(fn (): array => $this->seriesOptions)
                    ->placeholder('Todas')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->disabled(fn (): bool => ! $this->avaliacaoSelecionada())
                    ->live(),
                Select::make('turno')
                    ->label('Turno')
                    ->options(fn (): array => $this->turnosOptions)
                    ->placeholder('Todos')
                    ->native(false)
                    ->disabled(fn (): bool => ! $this->avaliacaoSelecionada())
                    ->live(),
                Select::make('componente_id')
                    ->label('Componente')
                    ->options(fn (): array => $this->componentesOptions)
                    ->placeholder('Todos')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->disabled(fn (): bool => ! $this->avaliacaoSelecionada())
                    ->live(),
                Select::make('professor_id')
                    ->label('Professor')
                    ->options(fn (): array => $this->professoresOptions)
                    ->placeholder('Todos')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->disabled(fn (): bool => ! $this->avaliacaoSelecionada())
                    ->live(),
                Select::make('status')
                    ->label('Status do preenchimento')
                    ->options([
                        '' => 'Todos',
                        'concluido' => 'Concluído',
                        'em_andamento' => 'Em andamento',
                        'nao_iniciado' => 'Não iniciado',
                    ])
                    ->placeholder('Todos')
                    ->native(false)
                    ->disabled(fn (): bool => ! $this->avaliacaoSelecionada())
                    ->live(),
            ])
            ->columns(4)
            ->statePath('filtrosAcompanhamento');
    }

    public function getHeader(): ?View
    {
        $quando = $this->ultimaAtualizacaoIncremental !== ''
            ? $this->ultimaAtualizacaoIncremental
            : ($this->ultimaAtualizacao ?: '-');

        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Relatórios Pedagógicos',
            'title' => 'Acompanhamento de Pareceres',
            'description' => 'Acompanhe o preenchimento dos pareceres por escola, turma, componente e professor. Use Atualizar para recalcular com os dados atuais. Atualizado em '.$quando,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('atualizarDadosRecentes')
                ->label('Atualizar')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->tooltip(fn (): string => $this->ultimaAtualizacaoIncremental !== ''
                    ? 'Última verificação: '.$this->ultimaAtualizacaoIncremental
                    : 'Recalcula os indicadores diretamente com os dados atuais')
                ->visible(fn (): bool => $this->avaliacaoSelecionada())
                ->action(fn () => $this->atualizarDadosRecentes()),

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

        return Gate::allows('follow', Avaliacao::class);
    }

    public function getPodeVerProgressoPorEscolaProperty(): bool
    {
        return Gate::allows('viewProgressBySchool', Avaliacao::class);
    }

    public function getPodeVerPendenciaPorEscolaProperty(): bool
    {
        $user = $this->usuarioAtual();

        if (! $user || $this->usuarioTemEscopoGlobal($user)) {
            return true;
        }

        return count($user->idsEscolasVinculadas()) !== 1;
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

        return app(PessoaScopeService::class)->hasGlobalAccess($user);
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

        return app(PessoaScopeService::class)->escolaIdsDosVinculos($user);
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

        $query->whereIn($alias.'.id_escola', $escolasIds);
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
     * @param  array<int>  $ids
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
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return Gate::allows('exportReports')
            || Gate::allows('export', Avaliacao::class);
    }

    public function getPodeExportarParecerProperty(): bool
    {
        return Gate::allows('export', Avaliacao::class);
    }

    private function podeAbrirAvaliacoesProfessor(): bool
    {
        return Gate::allows('accessProfessorPage', Avaliacao::class);
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
                    ->from('avaliacao_aluno_documentos as d')
                    ->whereColumn('d.avaliacao_id', 'at.avaliacao_id')
                    ->whereColumn('d.turma_id', 't.id')
                    ->where(function (QueryBuilder $q) use ($filtros): void {
                        foreach ($filtros['alternativas_ids'] as $alternativaId) {
                            $q->orWhereRaw(
                                'JSON_CONTAINS(COALESCE(d.alternativa_ids, JSON_ARRAY()), CAST(? AS JSON), \'$\')',
                                [(string) (int) $alternativaId]
                            );
                        }
                    });

                if ($filtros['pautas_ids'] !== []) {
                    $subQuery->where(function (QueryBuilder $q) use ($filtros): void {
                        foreach ($filtros['pautas_ids'] as $pautaId) {
                            $q->orWhereRaw(
                                'JSON_CONTAINS(COALESCE(d.pauta_ids_respondidas, JSON_ARRAY()), CAST(? AS JSON), \'$\')',
                                [(string) (int) $pautaId]
                            );
                        }
                    });
                }

                if (($filtros['professores_ids'] ?? []) !== []) {
                    $subQuery->where(function (QueryBuilder $q) use ($filtros): void {
                        foreach ($filtros['professores_ids'] as $professorId) {
                            $q->orWhereRaw(
                                'JSON_CONTAINS(COALESCE(d.professor_ids, JSON_ARRAY()), CAST(? AS JSON), \'$\')',
                                [(string) (int) $professorId]
                            );
                        }
                    });
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
                    ->from('avaliacao_aluno_documentos as d')
                    ->join('turmas as t2', 't2.id', '=', 'd.turma_id')
                    ->whereColumn('d.avaliacao_id', 'ap.avaliacao_id')
                    ->whereRaw(
                        'JSON_CONTAINS(COALESCE(d.pauta_ids_respondidas, JSON_ARRAY()), CAST(p.id AS JSON), \'$\')'
                    );

                $this->aplicarEscopoEscolarQuery($subQuery, 't2');

                if ($filtros['professores_ids'] !== []) {
                    $subQuery->where(function (QueryBuilder $q) use ($filtros): void {
                        foreach ($filtros['professores_ids'] as $professorId) {
                            $q->orWhereRaw(
                                'JSON_CONTAINS(COALESCE(d.professor_ids, JSON_ARRAY()), CAST(? AS JSON), \'$\')',
                                [(int) $professorId]
                            );
                        }
                    });
                }

                if ($filtros['alternativas_ids'] !== []) {
                    $subQuery->where(function (QueryBuilder $q) use ($filtros): void {
                        foreach ($filtros['alternativas_ids'] as $alternativaId) {
                            $q->orWhereRaw(
                                'JSON_CONTAINS(COALESCE(d.alternativa_ids, JSON_ARRAY()), CAST(? AS JSON), \'$\')',
                                [(int) $alternativaId]
                            );
                        }
                    });
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
                    ->from('avaliacao_aluno_documentos as d')
                    ->whereColumn('d.avaliacao_id', 'at.avaliacao_id')
                    ->whereColumn('d.turma_id', 't.id')
                    ->where(function (QueryBuilder $q) use ($filtros): void {
                        foreach ($filtros['alternativas_ids'] as $alternativaId) {
                            $q->orWhereRaw(
                                'JSON_CONTAINS(COALESCE(d.alternativa_ids, JSON_ARRAY()), CAST(? AS JSON), \'$\')',
                                [(int) $alternativaId]
                            );
                        }
                    });

                if (($filtros['pautas_ids'] ?? []) !== []) {
                    $subQuery->where(function (QueryBuilder $q) use ($filtros): void {
                        foreach ($filtros['pautas_ids'] as $pautaId) {
                            $q->orWhereRaw(
                                'JSON_CONTAINS(COALESCE(d.pauta_ids_respondidas, JSON_ARRAY()), CAST(? AS JSON), \'$\')',
                                [(int) $pautaId]
                            );
                        }
                    });
                }

                if (($filtros['professores_ids'] ?? []) !== []) {
                    $subQuery->where(function (QueryBuilder $q) use ($filtros): void {
                        foreach ($filtros['professores_ids'] as $professorId) {
                            $q->orWhereRaw(
                                'JSON_CONTAINS(COALESCE(d.professor_ids, JSON_ARRAY()), CAST(? AS JSON), \'$\')',
                                [(int) $professorId]
                            );
                        }
                    });
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
            'dashboard-avaliações-'.now()->format('Y-m-d_H-i').'.pdf'
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
        $spreadsheet = new Spreadsheet;

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
            '% de preenchimento geral' => ($dados['cards']['percentual_preenchimento_geral'] ?? 0).'%',
            'Preenchimentos esperados' => $dados['cards']['preenchimentos_esperados'] ?? 0,
            'Preenchimentos respondidos' => $dados['cards']['preenchimentos_respondidos'] ?? 0,
            'Preenchimentos pendentes' => $dados['cards']['preenchimentos_pendentes'] ?? 0,
            '% de turmas preenchidas' => ($dados['cards']['percentual_turmas_preenchidas'] ?? 0).'%',
            'Turmas preenchidas' => ($dados['cards']['turmas_preenchidas'] ?? 0).' de '.($dados['cards']['turmas_esperadas'] ?? 0),
            'Turmas incompletas' => $dados['cards']['turmas_incompletas'] ?? 0,
            'Alunos pendentes manha' => ($dados['cards']['turno_manha_alunos_pendentes'] ?? 0).' de '.($dados['cards']['turno_manha_alunos_total'] ?? 0),
            'Alunos pendentes tarde' => ($dados['cards']['turno_tarde_alunos_pendentes'] ?? 0).' de '.($dados['cards']['turno_tarde_alunos_total'] ?? 0),
        ];

        foreach ($indicadores as $label => $valor) {
            $resumoSheet->setCellValue("A{$linha}", $label);
            $resumoSheet->setCellValue("B{$linha}", $valor);
            $linha++;
        }

        $this->estilizarCorpoTabela($resumoSheet, 'A'.($linha - count($indicadores)).':B'.($linha - 1));
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
                $item['percentual_turmas_incompletas'].'%',
                $item['percentual_preenchimento'].'%',
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
                $item['percentual_preenchimento'].'%',
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
                $item['percentual_preenchimento'].'%',
            ])
        );

        $this->preencherTabelaSheet(
            $spreadsheet->createSheet()->setTitle('Acompanhamento'),
            'Acompanhamento de Pareceres por Turma',
            ['Escola', 'Serie', 'Turma', 'Turno', '% preenchimento da turma', 'Status'],
            collect($dados['acompanhamento_turmas'])->map(fn (array $item): array => [
                $item['escola_nome'],
                $item['serie_nome'],
                $item['turma_nome'],
                $item['turno'],
                $item['percentual_preenchimento'].'%',
                $item['status_label'],
            ])
        );

        $spreadsheet->setActiveSheetIndex(0);

        return $this->downloadSpreadsheet(
            $spreadsheet,
            'dashboard-avaliacoes-'.now()->format('Y-m-d_H-i').'.xlsx'
        );
    }

    private function aplicarDashboardData(array $dados): void
    {
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

    public function atualizarAcompanhamentoTurmas(): void
    {
        $this->parecerTurmaElegibilidade = [];
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
     * Recarrega KPIs e listagem de acompanhamento sem depender do cache
     * e sem montar exportações/PDF. Usado pelo botão Atualizar.
     */
    private function atualizarMetricasLeves(): void
    {
        $this->normalizarFiltros();
        $this->normalizarFiltrosAcompanhamento();

        if (! $this->avaliacaoSelecionada()) {
            $this->aplicarDashboardData($this->dashboardVazio());

            return;
        }

        $avaliacaoIds = $this->obterIdsAvaliacoesFiltradas();
        $tabelaEscolas = $this->montarTabelaEscolas($avaliacaoIds);
        $totaisPreenchimento = $this->calcularTotaisPreenchimento($avaliacaoIds, $tabelaEscolas);
        $turnos = $this->calcularPreenchimentoPorTurno($avaliacaoIds);
        $acompanhamentoTurmas = $this->montarAcompanhamentoTurmas($avaliacaoIds, paginar: true);

        $this->cards = [
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
        ];
        $this->tabelaEscolas = $tabelaEscolas;
        $this->preenchimentoPorComponentes = $this->montarPreenchimentoPorComponentes($avaliacaoIds);
        $this->preenchimentoPorSeries = $this->montarPreenchimentoPorSeries($avaliacaoIds);
        $this->turmasIncompletasPorEscola = $this->montarTurmasIncompletasPorEscola($tabelaEscolas);
        $this->acompanhamentoTurmas = $acompanhamentoTurmas['itens'];
        $this->acompanhamentoTurmasTotal = $acompanhamentoTurmas['total'];
        $this->normalizarPaginacoesListagens();
        $this->filtrosAplicados = $this->filtrosAplicadosFormatados();
        $this->dashboardCarregado = true;
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
    ): void {
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
    ): QueryBuilder {
        $filtrosAtivos = $filtros ?? $this->filtros;

        if ($avaliacaoIds === []) {
            return DB::table(DB::raw('(select 1 as avaliacao_id, 1 as turma_id, 1 as pauta_id, 1 as aluno_id, 1 as alternativa_id, null as professor_id, null as respondido_em where 1 = 0) as ar'))
                ->whereRaw('1 = 0');
        }

        $query = app(AvaliacaoDashboardOnDemandQueryService::class)->respostas($avaliacaoIds);

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

        $query = app(AvaliacaoDashboardOnDemandQueryService::class)->esperados($avaliacaoIds);

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
                ->where('tcp.tem_professor', true)
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

        $seriesSemFatos = DB::table('avaliacao_turma as at')
            ->join('turmas as t', 't.id', '=', 'at.turma_id')
            ->join('series as s', 's.id', '=', 't.id_serie')
            ->join('avaliacao_pauta as ap', 'ap.avaliacao_id', '=', 'at.avaliacao_id')
            ->join('pautas as p', 'p.id', '=', 'ap.pauta_id')
            ->whereIn('at.avaliacao_id', $avaliacaoIds)
            ->where('p.status', true)
            ->where(function (QueryBuilder $query): void {
                $query->whereNull('p.serie_id')
                    ->orWhereColumn('p.serie_id', 't.id_serie');
            });

        $this->aplicarEscopoEscolarQuery($seriesSemFatos, 't');

        if ($this->filtros['series_ids'] !== []) {
            $seriesSemFatos->whereIn('t.id_serie', $this->filtros['series_ids']);
        }

        if ($this->filtros['turnos'] !== []) {
            $seriesSemFatos->whereIn('t.turno', $this->filtros['turnos']);
        }

        if ($this->filtros['escolas_ids'] !== []) {
            $seriesSemFatos->whereIn('t.id_escola', $this->filtros['escolas_ids']);
        }

        if ($this->filtros['componentes_ids'] !== []) {
            $seriesSemFatos->whereIn('p.componente_curricular_id', $this->filtros['componentes_ids']);
        }

        if ($this->filtros['pautas_ids'] !== []) {
            $seriesSemFatos->whereIn('p.id', $this->filtros['pautas_ids']);
        }

        if ($this->filtros['professores_ids'] !== []) {
            $seriesSemFatos
                ->join('turma_componente_professor as tcp', 'tcp.turma_id', '=', 't.id')
                ->whereIn('tcp.professor_id', $this->filtros['professores_ids'])
                ->where('tcp.tem_professor', true)
                ->whereColumn('tcp.componente_curricular_id', 'p.componente_curricular_id');
        }

        $seriesSemFatos
            ->select('t.id_serie as agrupamento_id', 's.nome')
            ->distinct()
            ->get()
            ->each(function (object $serie) use ($esperados): void {
                $serieId = (int) $serie->agrupamento_id;

                if (! $esperados->has($serieId)) {
                    $esperados->put($serieId, (object) [
                        'agrupamento_id' => $serieId,
                        'nome' => (string) $serie->nome,
                        'preenchimentos_esperados' => 0,
                    ]);
                }
            });

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
     * @param  Collection<int, object>  $esperados
     * @param  Collection<int, object>  $respondidos
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
            ->keyBy(fn ($item): string => $item->avaliacao_id.':'.$item->turma_id);

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
            $chaveTurma = $esperada->avaliacao_id.':'.$esperada->turma_id;
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

        $esperadosQuery = (clone $this->basePreenchimentosEsperadosQuery($avaliacaoIds, $filtros))
            ->join('avaliacoes as av', 'av.id', '=', 'at.avaliacao_id')
            ->leftJoin('escolas as e', 'e.id', '=', 't.id_escola')
            ->leftJoin('series as s', 's.id', '=', 't.id_serie');

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
                's.nome'
            )
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
                DB::raw("COUNT(DISTINCT {$distinctEsperado}) as preenchimentos_esperados"),
                DB::raw('COUNT(DISTINCT p.id) as pautas_total'),
                DB::raw('COUNT(DISTINCT aln.id) as alunos_total')
            );

        // O professor limita o par turma/componente atual. A resposta não depende
        // do professor gravado no payload, que é apenas um snapshot histórico.
        $filtrosRespostas = [...$filtros, 'professores_ids' => []];
        $respondidosQuery = clone $this->baseRespostasQuery(
            $avaliacaoIds,
            ignorarAlternativas: true,
            filtros: $filtrosRespostas,
        );

        if ($professoresIds !== []) {
            $respondidosQuery
                ->join('turma_componente_professor as tcp_resposta', function ($join): void {
                    $join->on('tcp_resposta.turma_id', '=', 't.id')
                        ->where('tcp_resposta.tem_professor', true)
                        ->where(function ($join): void {
                            $join->whereNull('p.componente_curricular_id')
                                ->orOn('tcp_resposta.componente_curricular_id', '=', 'p.componente_curricular_id');
                        });
                })
                ->whereIn('tcp_resposta.professor_id', $professoresIds);
        }

        $respondidosQuery
            ->groupBy('ar.avaliacao_id', 'ar.turma_id')
            ->select(
                'ar.avaliacao_id',
                'ar.turma_id',
                DB::raw("COUNT(DISTINCT {$distinctRespondido}) as preenchimentos_respondidos"),
                DB::raw('MAX(ar.respondido_em) as ultima_resposta_em')
            );

        $acompanhamentoQuery = DB::query()
            ->fromSub($esperadosQuery, 'esperados')
            ->leftJoinSub($respondidosQuery, 'respondidos', function ($join): void {
                $join->on('respondidos.avaliacao_id', '=', 'esperados.avaliacao_id')
                    ->on('respondidos.turma_id', '=', 'esperados.turma_id');
            })
            ->select(
                'esperados.*',
                DB::raw('COALESCE(respondidos.preenchimentos_respondidos, 0) as preenchimentos_respondidos'),
                'respondidos.ultima_resposta_em'
            );

        $query = DB::query()
            ->fromSub($acompanhamentoQuery, 'acompanhamento')
            ->select('acompanhamento.*');

        $statusFiltro = filled($filtros['status_preenchimento'] ?? null)
            ? (string) $filtros['status_preenchimento']
            : null;

        // Status é calculado em PHP; com esse filtro não paginamos no SQL.
        $total = 0;
        if ($paginar && $statusFiltro === null) {
            $total = (int) DB::query()
                ->fromSub(clone $query, 'acompanhamento')
                ->count();
            $this->normalizarPaginaAcompanhamentoTurmas($total);
            $query->forPage($this->acompanhamentoTurmasPagina, $this->acompanhamentoTurmasPorPagina);
        }

        $dados = $query
            ->orderBy('acompanhamento.escola_nome')
            ->orderBy('acompanhamento.serie_nome')
            ->orderBy('acompanhamento.turma_nome')
            ->get();

        $turmasDaPagina = Turma::query()
            ->whereIn('id', $dados->pluck('turma_id')->map(fn ($id): int => (int) $id)->unique()->values()->all())
            ->get()
            ->keyBy('id');

        $itens = $dados
            ->map(function ($item) use ($turmasDaPagina): array {
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
                $parecerElegibilidade = $this->parecerElegibilidadeDaTurma(
                    (int) $item->avaliacao_id,
                    (int) $item->turma_id,
                    $turmasDaPagina->get((int) $item->turma_id)
                );

                $linha = [
                    'avaliacao_id' => (int) $item->avaliacao_id,
                    'turma_id' => (int) $item->turma_id,
                    'escola_id' => (int) ($item->escola_id ?? 0),
                    'serie_id' => (int) ($item->serie_id ?? 0),
                    'componente_id' => 0,
                    'professor_id' => 0,
                    'avaliacao_nome' => (string) ($item->avaliacao_nome ?? '-'),
                    'escola_nome' => (string) ($item->escola_nome ?? '-'),
                    'serie_nome' => (string) ($item->serie_nome ?? '-'),
                    'turma_nome' => (string) ($item->turma_nome ?? '-'),
                    'turno' => (string) ($item->turno ?? '-'),
                    'componente_nome' => 'Todos os componentes',
                    'professor_nome' => 'Todos os professores',
                    'preenchimentos_esperados' => $preenchimentosEsperados,
                    'preenchimentos_respondidos' => $preenchimentosRespondidos,
                    'preenchimentos_pendentes' => $preenchimentosPendentes,
                    'percentual_preenchimento' => $percentualPreenchimento,
                    'pautas_total' => (int) ($item->pautas_total ?? 0),
                    'alunos_total' => (int) ($item->alunos_total ?? 0),
                    'status' => $status,
                    'status_label' => match ($status) {
                        'concluido' => 'Concluído',
                        'em_andamento' => 'Em andamento',
                        default => 'Não iniciado',
                    },
                    'parecer_exportavel' => $parecerElegibilidade['pode_exportar'],
                    'parecer_exportavel_motivo' => $parecerElegibilidade['motivo_bloqueio'],
                    'ultima_resposta' => $item->ultima_resposta_em
                        ? Carbon::parse($item->ultima_resposta_em)->format('d/m/Y H:i')
                        : '-',
                ];

                $linha['chave'] = $this->chaveLinhaAcompanhamento($linha);
                $linha['alterado_recentemente'] = in_array($linha['chave'], $this->acompanhamentoLinhasAlteradas, true);

                return $linha;
            })
            ->when(
                $statusFiltro !== null,
                fn (Collection $colecao): Collection => $colecao->filter(
                    fn (array $linha): bool => ($linha['status'] ?? '') === $statusFiltro
                )->values()
            )
            ->values()
            ->all();

        if ($statusFiltro !== null) {
            $total = count($itens);

            if ($paginar) {
                $this->normalizarPaginaAcompanhamentoTurmas($total);
                $offset = max(($this->acompanhamentoTurmasPagina - 1) * $this->acompanhamentoTurmasPorPagina, 0);
                $itens = array_slice($itens, $offset, $this->acompanhamentoTurmasPorPagina);
            }
        } elseif (! $paginar) {
            $total = count($itens);
        }

        return ['itens' => $itens, 'total' => $total];
    }

    /**
     * @return array{diretor: string, coordenacao: string, tem_diretor: bool, tem_coordenacao: bool, pode_exportar: bool, motivo_bloqueio: string}
     */
    private function parecerElegibilidadeDaTurma(int $avaliacaoId, int $turmaId, ?Turma $turma = null): array
    {
        $chave = "{$avaliacaoId}-{$turmaId}";

        if (array_key_exists($chave, $this->parecerTurmaElegibilidade)) {
            return $this->parecerTurmaElegibilidade[$chave];
        }

        $turma ??= Turma::query()->find($turmaId);

        if (! $turma) {
            return $this->parecerTurmaElegibilidade[$chave] = [
                'diretor' => '',
                'coordenacao' => '',
                'tem_diretor' => false,
                'tem_coordenacao' => false,
                'pode_exportar' => false,
                'motivo_bloqueio' => 'A turma não possui vínculo com Diretor(a) ou Coordenador(a).',
            ];
        }

        return $this->parecerTurmaElegibilidade[$chave] = app(AvaliacaoDocumentoExportService::class)
            ->gestoresDaTurma($turma, $avaliacaoId);
    }

    private function aplicarFiltrosTurmaQuery(QueryBuilder $query, string $alias = 't', ?array $filtros = null): void
    {
        $filtrosAtivos = $filtros ?? $this->filtros;

        $this->aplicarEscopoEscolarQuery($query, $alias);

        if ($filtrosAtivos['series_ids'] !== []) {
            $query->whereIn($alias.'.id_serie', $filtrosAtivos['series_ids']);
        }

        if ($filtrosAtivos['turnos'] !== []) {
            $query->whereIn($alias.'.turno', $filtrosAtivos['turnos']);
        }

        if ($filtrosAtivos['escolas_ids'] !== []) {
            $query->whereIn($alias.'.id_escola', $filtrosAtivos['escolas_ids']);
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
     * @param  array<int|string>  $ids
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
        $this->filtrosAcompanhamento['escola_id'] = $this->normalizarEscolaAcompanhamento(
            $this->filtrosAcompanhamento['escola_id'] ?? null
        );
        $this->filtrosAcompanhamento['serie_id'] = $this->normalizarId($this->filtrosAcompanhamento['serie_id'] ?? null);
        $this->filtrosAcompanhamento['componente_id'] = $this->normalizarId($this->filtrosAcompanhamento['componente_id'] ?? null);
        $this->filtrosAcompanhamento['professor_id'] = $this->normalizarId($this->filtrosAcompanhamento['professor_id'] ?? null);
        $turno = $this->filtrosAcompanhamento['turno'] ?? null;
        $turno = filled($turno) ? (string) $turno : null;
        $this->filtrosAcompanhamento['turno'] = array_key_exists((string) $turno, $this->turnosOptions)
            ? $turno
            : null;
        $status = (string) ($this->filtrosAcompanhamento['status'] ?? '');
        $this->filtrosAcompanhamento['status'] = in_array($status, ['concluido', 'em_andamento', 'nao_iniciado'], true)
            ? $status
            : null;
    }

    private function filtrosDoAcompanhamento(): array
    {
        $componentes = array_filter([(int) ($this->filtrosAcompanhamento['componente_id'] ?? 0)]);
        $professores = array_filter([(int) ($this->filtrosAcompanhamento['professor_id'] ?? 0)]);

        return [
            ...$this->filtros,
            'series_ids' => array_values(array_unique(array_filter(array_merge(
                $this->filtros['series_ids'] ?? [],
                array_filter([(int) ($this->filtrosAcompanhamento['serie_id'] ?? 0)])
            )))),
            'turnos' => array_values(array_unique(array_filter(array_merge(
                $this->filtros['turnos'] ?? [],
                filled($this->filtrosAcompanhamento['turno'] ?? null)
                    ? [(string) $this->filtrosAcompanhamento['turno']]
                    : []
            )))),
            'componentes_ids' => array_values(array_unique(array_filter(array_merge(
                $this->filtros['componentes_ids'] ?? [],
                $componentes
            )))),
            'escolas_ids' => array_values(array_unique(array_filter(array_merge(
                $this->filtros['escolas_ids'] ?? [],
                array_filter([(int) ($this->filtrosAcompanhamento['escola_id'] ?? 0)])
            )))),
            'professores_ids' => array_values(array_unique(array_filter(array_merge(
                $this->filtros['professores_ids'] ?? [],
                $professores
            )))),
            'status_preenchimento' => $this->filtrosAcompanhamento['status'] ?? null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $itens
     * @return array<string, array{percentual: float, status: string, respondidos: int}>
     */
    private function capturarSnapshotAcompanhamento(array $itens): array
    {
        $snapshot = [];

        foreach ($itens as $item) {
            $chave = $this->chaveLinhaAcompanhamento($item);
            $snapshot[$chave] = [
                'percentual' => (float) ($item['percentual_preenchimento'] ?? 0),
                'status' => (string) ($item['status'] ?? ''),
                'respondidos' => (int) ($item['preenchimentos_respondidos'] ?? 0),
            ];
        }

        return $snapshot;
    }

    /**
     * @param  array<string, array{percentual: float, status: string, respondidos: int}>  $antes
     * @param  array<string, array{percentual: float, status: string, respondidos: int}>  $depois
     * @return array<int, string>
     */
    private function diffSnapshotAcompanhamento(array $antes, array $depois): array
    {
        $alteradas = [];

        foreach ($depois as $chave => $atual) {
            $anterior = $antes[$chave] ?? null;

            if ($anterior === null) {
                $alteradas[] = $chave;

                continue;
            }

            if (
                (float) $anterior['percentual'] !== (float) $atual['percentual']
                || (string) $anterior['status'] !== (string) $atual['status']
                || (int) $anterior['respondidos'] !== (int) $atual['respondidos']
            ) {
                $alteradas[] = $chave;
            }
        }

        return array_values($alteradas);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function chaveLinhaAcompanhamento(array $item): string
    {
        return implode(':', [
            (int) ($item['avaliacao_id'] ?? 0),
            (int) ($item['turma_id'] ?? 0),
            (int) ($item['escola_id'] ?? 0),
            (int) ($item['serie_id'] ?? 0),
            (int) ($item['componente_id'] ?? 0),
            (int) ($item['professor_id'] ?? 0),
        ]);
    }

    private function cardsDiferem(array $antes, array $depois): bool
    {
        $chaves = [
            'preenchimentos_esperados',
            'preenchimentos_respondidos',
            'preenchimentos_pendentes',
            'percentual_preenchimento_geral',
            'turmas_preenchidas',
            'turmas_esperadas',
        ];

        foreach ($chaves as $chave) {
            if ((string) ($antes[$chave] ?? '') !== (string) ($depois[$chave] ?? '')) {
                return true;
            }
        }

        return false;
    }

    private function normalizarId(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    /**
     * @param  array<int, mixed>  $values
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
            'escola_id' => null,
            'serie_id' => null,
            'turno' => null,
            'componente_id' => null,
            'professor_id' => null,
            'status' => null,
        ];
    }

    private function normalizarEscolaAcompanhamento(mixed $value): ?int
    {
        $escolaId = $this->normalizarId($value);

        if (! $escolaId) {
            return null;
        }

        return in_array($escolaId, $this->filtrarEscolasPermitidas([$escolaId]), true)
            ? $escolaId
            : null;
    }

    private function distinctCombinacaoExpr(string ...$colunas): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return implode(" || '|#|' || ", $colunas);
        }

        return 'CONCAT('.implode(", '|#|', ", $colunas).')';
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
            $this->estilizarCorpoTabela($sheet, 'A'.($linha - $rows->count()).":{$ultimaColuna}".($linha - 1));
        }

        $this->autoSizeColumns($sheet, count($headers));
    }

    /**
     * @param  array<string, string>  $filtros
     */
    private function preencherCabecalhoSheet(Worksheet $sheet, string $titulo, array $filtros): int
    {
        $sheet->setCellValue('A1', $titulo);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', 'Gerado em '.now()->format('d/m/Y H:i').' por '.(Auth::user()?->name ?? 'Sistema'));
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

    private function dashboardCacheTtl(): int
    {
        return max((int) config('performance.cache_ttl.avaliacoes_dashboard', 45), 1);
    }

    private function dashboardCacheKey(string $secao): string
    {
        $contexto = [
            'usuario' => Auth::id(),
            'filtros' => $this->filtros,
            'filtros_acompanhamento' => $secao === 'acompanhamento' ? $this->filtrosAcompanhamento : [],
            'pagina' => $secao === 'acompanhamento' ? $this->acompanhamentoTurmasPagina : null,
            'por_pagina' => $secao === 'acompanhamento' ? $this->acompanhamentoTurmasPorPagina : null,
        ];

        return 'avaliacoes-dashboard:'.$secao.':'.hash('sha256', serialize($contexto));
    }

    private function limparDashboardCache(): void
    {
        foreach (['resumo', 'graficos', 'acompanhamento'] as $secao) {
            Cache::forget($this->dashboardCacheKey($secao));
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
