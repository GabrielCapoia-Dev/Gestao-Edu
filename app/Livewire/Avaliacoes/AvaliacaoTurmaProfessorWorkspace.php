<?php

namespace App\Livewire\Avaliacoes;

use App\Models\Avaliacao;
use App\Models\Pauta;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Services\Avaliacoes\AvaliacaoDashboardProgressService;
use App\Services\Avaliacoes\AvaliacaoPersistencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AvaliacaoTurmaProfessorWorkspace extends AvaliacaoTurmaWorkspace
{
    public ?int $componenteModalId = null;

    public ?int $pautaModalId = null;

    public ?int $escolaNavegacaoId = null;

    public ?int $serieNavegacaoId = null;

    public array $progressoAvaliacoesProfessor = [];

    public bool $progressoAvaliacoesProfessorPronto = false;

    public array $progressoNavegacao = [];

    protected ?Collection $turmasDaAvaliacaoProfessorCache = null;

    protected ?Collection $escolasNavegacaoCache = null;

    protected ?Collection $seriesNavegacaoCache = null;

    protected ?Collection $componentesNavegacaoCache = null;

    protected ?int $totalTurmasNavegacaoCache = null;

    public function mount(
        ?int $avaliacaoId = null,
        ?int $turmaId = null,
        ?int $escolaId = null,
        ?int $serieId = null,
        ?int $initialComponenteId = null,
        string $modo = 'professor',
        bool $canEdit = false,
    ): void {
        parent::mount($avaliacaoId, $turmaId, $escolaId, $serieId, $initialComponenteId, $modo, $canEdit);

        if ($this->modoAcompanhamento()) {
            return;
        }

        if (! $this->avaliacao) {
            $this->carregarProgressoAvaliacoesProfessor();

            return;
        }

        $this->carregarProgressoNavegacao();

        if ($this->turma && ! $this->serie) {
            $turmaSelecionada = $this->novaConsultaTurmasNavegacao()
                ->where('turmas.id', (int) $this->turma)
                ->select(['turmas.id_serie', 'turmas.id_escola'])
                ->first();
            $this->serie = $turmaSelecionada?->id_serie ? (int) $turmaSelecionada->id_serie : null;
            $this->escola = $turmaSelecionada?->id_escola ? (int) $turmaSelecionada->id_escola : null;
        }

        $this->serieNavegacaoId = $this->serie;
        $this->escolaNavegacaoId = $this->agrupaNavegacaoPorEscola() ? $this->escola : null;

        if ($this->turma) {
            $this->turmasExpandidas = [(int) $this->turma];
        }

        if ($this->turma && $initialComponenteId !== null) {
            $this->abrirComponente((int) $this->turma, $initialComponenteId);
        }
    }

    public function interfaceProfessorEmLista(): bool
    {
        return true;
    }

    protected function deveCarregarDadosAoSelecionarTurma(): bool
    {
        return false;
    }

    public function getAvaliacoesDisponiveisProperty(): Collection
    {
        if ($this->avaliacoesDisponiveisCache instanceof Collection) {
            return $this->avaliacoesDisponiveisCache;
        }

        $query = Avaliacao::query()->pendentesParaData(now());
        $this->aplicarEscopoNavegacao($query);

        return $this->avaliacoesDisponiveisCache = $query
            ->with('tipo:id,nome')
            ->orderBy('data_inicio')
            ->get();
    }

    public function getAvaliacaoAtualProperty(): ?Avaliacao
    {
        if ($this->avaliacaoAtualCacheCarregado) {
            return $this->avaliacaoAtualCache;
        }

        $avaliacaoPermitida = $this->avaliacoesDisponiveis->firstWhere('id', (int) $this->avaliacao);
        if (! $avaliacaoPermitida || ! $this->turma || $this->componenteModalId === null) {
            $this->avaliacaoAtualCache = $avaliacaoPermitida;
            $this->avaliacaoAtualCacheCarregado = true;

            return $this->avaliacaoAtualCache;
        }

        if (! $this->turmasDaAvaliacaoProfessor->contains('id', (int) $this->turma)
            || ! $this->componentesNavegacaoDaTurma->contains(
                fn (array $item): bool => (int) $item['componente_id'] === $this->componenteModalId,
            )) {
            $this->avaliacaoAtualCacheCarregado = true;

            return $this->avaliacaoAtualCache = null;
        }

        $componenteId = $this->componenteModalId;
        $this->avaliacaoAtualCache = Avaliacao::query()
            ->pendentesParaData(now())
            ->whereKey((int) $this->avaliacao)
            ->with([
                'tipo' => fn ($tipo) => $tipo->with([
                    'alternativas' => fn ($alternativas) => $alternativas->where('status', true),
                ]),
                'pautas' => fn ($pautas) => $pautas
                    ->where('status', true)
                    ->where(fn ($series) => $series
                        ->whereNull('serie_id')
                        ->orWhere('serie_id', (int) $this->serie))
                    ->where(fn ($componentes) => $componenteId > 0
                        ? $componentes->where('componente_curricular_id', $componenteId)
                        : $componentes->whereNull('componente_curricular_id'))
                    ->with([
                        'componente:id,nome',
                        'tipo' => fn ($tipo) => $tipo->with([
                            'alternativas' => fn ($alternativas) => $alternativas->where('status', true),
                        ]),
                        'alternativas' => fn ($alternativas) => $alternativas->where('status', true),
                    ]),
                'turmas' => fn ($turmas) => $turmas
                    ->whereKey((int) $this->turma)
                    ->with(['escola:id,nome', 'serie:id,nome']),
            ])
            ->first();
        $this->avaliacaoAtualCacheCarregado = true;

        return $this->avaliacaoAtualCache;
    }

    public function getTurmasDaAvaliacaoProfessorProperty(): Collection
    {
        if ($this->turmasDaAvaliacaoProfessorCache instanceof Collection) {
            return $this->turmasDaAvaliacaoProfessorCache;
        }

        $serieId = (int) ($this->serieNavegacaoId ?? $this->serie ?? 0);
        if ($serieId <= 0 || ! $this->avaliacao || ! $this->avaliacoesDisponiveis->contains('id', (int) $this->avaliacao)) {
            return $this->turmasDaAvaliacaoProfessorCache = collect();
        }

        $query = $this->novaConsultaTurmasNavegacao()
            ->where('turmas.id_serie', $serieId)
            ->when(
                $this->agrupaNavegacaoPorEscola(),
                fn (Builder $turmas) => $turmas->where('turmas.id_escola', (int) $this->escolaNavegacaoId),
            )
            ->select('turmas.*')
            ->distinct()
            ->with(['escola:id,nome', 'serie:id,nome']);

        return $this->turmasDaAvaliacaoProfessorCache = $query
            ->orderBy('turmas.nome')
            ->get();
    }

    public function getEscolasNavegacaoProperty(): Collection
    {
        if ($this->escolasNavegacaoCache instanceof Collection) {
            return $this->escolasNavegacaoCache;
        }

        if (! $this->agrupaNavegacaoPorEscola() || ! $this->avaliacao) {
            return $this->escolasNavegacaoCache = collect();
        }

        return $this->escolasNavegacaoCache = $this->novaConsultaTurmasNavegacao()
            ->join('escolas as e_nav', 'e_nav.id', '=', 'turmas.id_escola')
            ->selectRaw('turmas.id_escola as escola_id, e_nav.nome as escola_nome, COUNT(DISTINCT turmas.id) as turmas_total')
            ->groupBy('turmas.id_escola', 'e_nav.nome')
            ->orderBy('e_nav.nome')
            ->get();
    }

    public function getSeriesNavegacaoProperty(): Collection
    {
        if ($this->seriesNavegacaoCache instanceof Collection) {
            return $this->seriesNavegacaoCache;
        }

        if (! $this->avaliacao || ($this->agrupaNavegacaoPorEscola() && ! $this->escolaNavegacaoId)) {
            return $this->seriesNavegacaoCache = collect();
        }

        return $this->seriesNavegacaoCache = $this->novaConsultaTurmasNavegacao()
            ->join('series as s_nav', 's_nav.id', '=', 'turmas.id_serie')
            ->join('escolas as e_nav', 'e_nav.id', '=', 'turmas.id_escola')
            ->when(
                $this->agrupaNavegacaoPorEscola(),
                fn (Builder $turmas) => $turmas->where('turmas.id_escola', (int) $this->escolaNavegacaoId),
            )
            ->selectRaw('turmas.id_serie as serie_id, s_nav.nome as serie_nome, GROUP_CONCAT(DISTINCT e_nav.nome) as escolas_nome, COUNT(DISTINCT turmas.id) as turmas_total')
            ->groupBy('turmas.id_serie', 's_nav.nome')
            ->orderBy('s_nav.nome')
            ->get();
    }

    public function getTotalTurmasNavegacaoProperty(): int
    {
        return $this->totalTurmasNavegacaoCache ??= $this->avaliacao
            ? $this->novaConsultaTurmasNavegacao()->distinct()->count('turmas.id')
            : 0;
    }

    public function getComponentesNavegacaoDaTurmaProperty(): Collection
    {
        if ($this->componentesNavegacaoCache instanceof Collection) {
            return $this->componentesNavegacaoCache;
        }

        if (! $this->turma || ! $this->serie) {
            return $this->componentesNavegacaoCache = collect();
        }

        $pautas = DB::table('avaliacao_pauta as ap_nav')
            ->join('pautas as p_nav', 'p_nav.id', '=', 'ap_nav.pauta_id')
            ->leftJoin('componentes_curriculares as c_nav', 'c_nav.id', '=', 'p_nav.componente_curricular_id')
            ->where('ap_nav.avaliacao_id', (int) $this->avaliacao)
            ->where('p_nav.status', true)
            ->where(fn ($series) => $series
                ->whereNull('p_nav.serie_id')
                ->orWhere('p_nav.serie_id', (int) $this->serie))
            ->selectRaw('COALESCE(p_nav.componente_curricular_id, 0) as componente_id')
            ->selectRaw("COALESCE(c_nav.nome, 'Geral') as componente_nome")
            ->selectRaw('COUNT(DISTINCT p_nav.id) as pautas_total');

        $this->aplicarEscopoProfessorNasPautas($pautas, 'turmas', 'p_nav', (int) $this->turma);

        $pautas = $pautas
            ->groupBy('p_nav.componente_curricular_id', 'c_nav.nome')
            ->orderBy('c_nav.nome')
            ->get();

        $componentesIds = $pautas
            ->pluck('componente_id')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values()
            ->all();
        $professores = TurmaComponenteProfessor::query()
            ->where('turma_id', (int) $this->turma)
            ->whereIn('componente_curricular_id', $componentesIds)
            ->where('tem_professor', true)
            ->when($this->deveFiltrarPorProfessor(), fn ($query) => $query->whereIn('professor_id', $this->professorIds))
            ->with('professor:id,nome')
            ->get()
            ->groupBy('componente_curricular_id');

        return $this->componentesNavegacaoCache = $pautas
            ->map(function (object $pauta) use ($professores): array {
                $componenteId = (int) $pauta->componente_id;
                $nomes = $professores->get($componenteId, collect())
                    ->pluck('professor.nome')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');

                return [
                    'componente_id' => $componenteId,
                    'componente_nome' => (string) $pauta->componente_nome,
                    'professor_nome' => $nomes !== '' ? $nomes : ($componenteId === 0 ? 'Componente geral' : 'Sem professor'),
                    'pautas_total' => (int) $pauta->pautas_total,
                ];
            })
            ->values();
    }

    /** @return array{preenchidas: int, total: int, percentual: int} */
    public function progressoDoComponenteNaTurma(int $turmaId, int $componenteId): array
    {
        $pautas = $this->pautasDaTurma($turmaId)
            ->filter(fn (Pauta $pauta): bool => (int) ($pauta->componente_curricular_id ?? 0) === $componenteId)
            ->values();

        if ($pautas->isEmpty()) {
            return $this->progressoNavegacao['componentes_por_turma'][$turmaId][$componenteId]
                ?? ['preenchidas' => 0, 'total' => 0, 'percentual' => 0];
        }
        $preenchidas = $pautas->sum(fn ($pauta): int => (int) ($this->progressoPorPauta[$turmaId][$pauta->id]['preenchidas'] ?? 0));
        $total = $pautas->sum(fn ($pauta): int => (int) ($this->progressoPorPauta[$turmaId][$pauta->id]['total'] ?? 0));

        return [
            'preenchidas' => $preenchidas,
            'total' => $total,
            'percentual' => $total > 0 ? min(100, (int) round(($preenchidas / $total) * 100)) : 0,
        ];
    }

    public function carregarProgressoAvaliacoesProfessor(): void
    {
        if ($this->progressoAvaliacoesProfessorPronto || $this->avaliacao) {
            return;
        }

        $ids = $this->avaliacoesDisponiveis
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $service = app(AvaliacaoDashboardProgressService::class);
        $dados = app(AvaliacaoPersistencia::class)->leRelacional()
            ? $service->detalhadoRapido(
                $ids,
                $this->escolasPermitidasIds(),
                $this->deveFiltrarPorProfessor() ? $this->professorIds : null,
            )
            : $service->batch(
                $ids,
                $this->escolasPermitidasIds(),
                $this->deveFiltrarPorProfessor() ? $this->professorIds : null,
            );

        $this->progressoAvaliacoesProfessor = collect($dados)->mapWithKeys(
            fn ($item, $avaliacaoId): array => [
                (int) $avaliacaoId => (int) round((float) (is_array($item) ? $item['percentual'] : $item->percentual ?? 0)),
            ],
        )->all();
        $this->progressoAvaliacoesProfessorPronto = true;
    }

    private function carregarProgressoNavegacao(): void
    {
        if (! $this->avaliacao || ! app(AvaliacaoPersistencia::class)->leRelacional()) {
            return;
        }

        $this->progressoNavegacao = app(AvaliacaoDashboardProgressService::class)
            ->detalhadoRapido(
                [(int) $this->avaliacao],
                $this->escolasPermitidasIds(),
                $this->deveFiltrarPorProfessor() ? $this->professorIds : null,
            )[(int) $this->avaliacao] ?? [];
    }

    public function agrupaNavegacaoPorEscola(): bool
    {
        return Auth::user()?->hasRole('Admin') ?? false;
    }

    public function selecionarEscolaNavegacao(int $escolaId): void
    {
        abort_unless(
            $this->agrupaNavegacaoPorEscola()
                && $this->escolasNavegacao->contains('escola_id', $escolaId),
            403,
        );

        $this->escolaNavegacaoId = $escolaId;
        $this->serieNavegacaoId = null;
        $this->turmasExpandidas = [];
        $this->fecharComponente();
    }

    public function selecionarSerieNavegacao(int $serieId): void
    {
        abort_unless(
            $this->seriesNavegacao->contains(fn (object $serie): bool => (int) $serie->serie_id === $serieId),
            403,
        );

        $this->serieNavegacaoId = $serieId;
        $this->turmasExpandidas = [];
        $this->fecharComponente();
    }

    public function voltarNavegacao(): void
    {
        if ($this->serieNavegacaoId !== null) {
            $this->serieNavegacaoId = null;
            $this->turmasExpandidas = [];
            $this->fecharComponente();

            return;
        }

        if ($this->agrupaNavegacaoPorEscola()) {
            $this->escolaNavegacaoId = null;
        }
    }

    public function getTurmasDisponiveisProperty(): Collection
    {
        if ($this->turmasDisponiveisCache instanceof Collection) {
            return $this->turmasDisponiveisCache;
        }

        if ($this->componenteModalId === null) {
            return $this->turmasDisponiveisCache = $this->turma
                ? $this->turmasDaAvaliacaoProfessor->where('id', (int) $this->turma)->values()
                : collect();
        }

        $turmas = parent::getTurmasDisponiveisProperty();

        if (! $this->turma) {
            return $turmas;
        }

        return $this->turmasDisponiveisCache = $turmas
            ->filter(fn (Turma $turma): bool => (int) $turma->id === (int) $this->turma)
            ->values();
    }

    public function updatedTurma(): void
    {
        $turma = $this->turma
            ? $this->turmasDaAvaliacaoProfessor->firstWhere('id', (int) $this->turma)
            : null;

        $this->serie = $turma?->id_serie ? (int) $turma->id_serie : null;
        $this->escola = $turma?->id_escola ? (int) $turma->id_escola : null;
        $this->serieEscola = $this->serie && $this->escola ? $this->escola.':'.$this->serie : null;
        $this->limparDadosDoEscopo(true);
    }

    public function definirVisualizacao(string $visualizacao): void
    {
        if (! in_array($visualizacao, ['pautas', 'alunos'], true) || $this->visualizacao === $visualizacao) {
            return;
        }

        $this->visualizacao = $visualizacao;
        $this->pautaModalId = null;
        $this->alunoEmFoco = null;
        $this->pautaEmMassaGlobal = null;
        $this->alunoEmMassaGlobal = null;
        $this->avaliacaoEmMassaGlobal = null;
    }

    public function abrirTurma(int $turmaId): void
    {
        abort_unless($this->turmasDaAvaliacaoProfessor->contains('id', $turmaId), 403);

        if ($this->turma === $turmaId && $this->turmaEstaExpandida($turmaId)) {
            $this->turmasExpandidas = [];
            $this->fecharComponente();

            return;
        }

        $this->turma = $turmaId;
        $this->updatedTurma();
        $this->turmasExpandidas = [$turmaId];
        $this->componentesNavegacaoCache = null;
        $this->fecharComponente();
    }

    public function abrirComponente(int $turmaId, int $componenteId): void
    {
        abort_unless($this->turma === $turmaId, 403);
        abort_unless(
            $this->componentesNavegacaoDaTurma
                ->contains(fn (array $grupo): bool => (int) $grupo['componente_id'] === $componenteId),
            403,
        );

        $this->componenteModalId = $componenteId;
        $this->pautaModalId = null;
        $this->alunoEmFoco = null;
        $this->limparDadosDoEscopo();
        $this->carregarDadosDoEscopo();
        $this->turmaEmMassaGlobal = $turmaId;
        $this->componenteEmMassaGlobal = $componenteId;
    }

    public function fecharComponente(): void
    {
        $estavaAberto = $this->componenteModalId !== null;
        $this->componenteModalId = null;
        $this->pautaModalId = null;
        $this->alunoEmFoco = null;

        if ($estavaAberto) {
            $this->limparDadosDoEscopo();
        }
    }

    public function selecionarPautaModal(int $pautaId): void
    {
        abort_unless(
            $this->pautasDoComponenteModal->contains('id', $pautaId),
            403,
        );

        $this->pautaModalId = $pautaId;
        $this->pautaEmMassaGlobal = $pautaId;
        $this->alunoEmMassaGlobal = null;
        $this->avaliacaoEmMassaGlobal = null;
    }

    public function selecionarAlunoModal(int $alunoId): void
    {
        abort_unless(
            $this->turma && $this->alunosDaTurma((int) $this->turma)->contains('id', $alunoId),
            403,
        );

        $this->alunoEmFoco = $alunoId;
        $this->alunoEmMassaGlobal = $alunoId;
        $this->pautaEmMassaGlobal = null;
        $this->avaliacaoEmMassaGlobal = null;
    }

    public function getAlternativasEmMassaModalProperty(): Collection
    {
        $alternativas = $this->visualizacao === 'pautas' && $this->pautaModal
            ? collect($this->alternativasDaPauta((int) $this->pautaModal->id))
            : $this->alternativasEmMassaDisponiveis;

        return $alternativas
            ->reject(fn (array $alternativa): bool => (bool) ($alternativa['tem_observacao'] ?? false))
            ->unique(fn (array $alternativa): int => (int) $alternativa['id'])
            ->sortBy(fn (array $alternativa): string => (string) $alternativa['nome'])
            ->values();
    }

    public function aplicarEmMassaNoModal(): void
    {
        abort_unless(
            $this->turma
                && $this->componenteModalId !== null
                && (($this->visualizacao === 'pautas' && $this->pautaModalId)
                    || ($this->visualizacao === 'alunos' && $this->alunoEmFoco)),
            403,
        );

        $this->turmaEmMassaGlobal = (int) $this->turma;
        $this->componenteEmMassaGlobal = $this->componenteModalId;
        $this->pautaEmMassaGlobal = $this->visualizacao === 'pautas' ? $this->pautaModalId : null;
        $this->alunoEmMassaGlobal = $this->visualizacao === 'alunos' ? $this->alunoEmFoco : null;
        $this->aplicarEmMassaNaSerie();
        $this->carregarRespostas();
        $this->avaliacaoEmMassaGlobal = null;
    }

    public function getGrupoComponenteModalProperty(): ?array
    {
        if (! $this->turma || $this->componenteModalId === null) {
            return null;
        }

        return $this->gruposPorComponenteDaTurma((int) $this->turma)
            ->first(fn (array $grupo): bool => (int) $grupo['componente_id'] === $this->componenteModalId);
    }

    public function getPautasDoComponenteModalProperty(): Collection
    {
        return collect($this->grupoComponenteModal['pautas'] ?? [])->values();
    }

    public function getTurmaModalProperty(): ?Turma
    {
        if (! $this->turma) {
            return null;
        }

        return $this->turmasDaAvaliacaoProfessor->firstWhere('id', (int) $this->turma);
    }

    public function getPautaModalProperty(): ?Pauta
    {
        if ($this->pautaModalId === null) {
            return null;
        }

        return $this->pautasDoComponenteModal->firstWhere('id', $this->pautaModalId);
    }

    private function aplicarEscopoNavegacao(Builder $query): void
    {
        $query->whereExists(function ($escopo): void {
            $escopo
                ->selectRaw('1')
                ->from('avaliacao_turma as at_nav')
                ->join('turmas as t_nav', 't_nav.id', '=', 'at_nav.turma_id')
                ->join('avaliacao_pauta as ap_nav', 'ap_nav.avaliacao_id', '=', 'at_nav.avaliacao_id')
                ->join('pautas as p_nav', 'p_nav.id', '=', 'ap_nav.pauta_id')
                ->whereColumn('at_nav.avaliacao_id', 'avaliacoes.id')
                ->where('p_nav.status', true)
                ->where(fn ($series) => $series
                    ->whereNull('p_nav.serie_id')
                    ->orWhereColumn('p_nav.serie_id', 't_nav.id_serie'));

            $this->aplicarEscopoTurmasNavegacao($escopo, 't_nav');
            $this->aplicarEscopoProfessorNasPautas($escopo, 't_nav', 'p_nav');
        });
    }

    private function novaConsultaTurmasNavegacao(): Builder
    {
        $query = Turma::query()
            ->join('avaliacao_turma as at_nav', 'at_nav.turma_id', '=', 'turmas.id')
            ->where('at_nav.avaliacao_id', (int) $this->avaliacao)
            ->whereExists(function ($pautas): void {
                $pautas
                    ->selectRaw('1')
                    ->from('avaliacao_pauta as ap_nav')
                    ->join('pautas as p_nav', 'p_nav.id', '=', 'ap_nav.pauta_id')
                    ->where('ap_nav.avaliacao_id', (int) $this->avaliacao)
                    ->where('p_nav.status', true)
                    ->where(fn ($series) => $series
                        ->whereNull('p_nav.serie_id')
                        ->orWhereColumn('p_nav.serie_id', 'turmas.id_serie'));

                $this->aplicarEscopoProfessorNasPautas($pautas, 'turmas', 'p_nav');
            });

        $this->aplicarEscopoTurmasNavegacao($query, 'turmas');

        return $query;
    }

    private function aplicarEscopoTurmasNavegacao($query, string $alias): void
    {
        $escolasIds = $this->escolasPermitidasIds();

        if (is_array($escolasIds)) {
            $escolasIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn($alias.'.id_escola', $escolasIds);
        }

        if ($this->deveRestringirAsTurmasDoProfessor()) {
            $query->whereIn($alias.'.id', $this->turmaIdsProfessor);
        }
    }

    private function aplicarEscopoProfessorNasPautas(
        $query,
        string $turmaAlias,
        string $pautaAlias,
        ?int $turmaId = null,
    ): void {
        if (! $this->deveFiltrarPorProfessor()) {
            return;
        }

        $query->where(function ($pautas) use ($turmaAlias, $pautaAlias, $turmaId): void {
            $pautas
                ->whereNull($pautaAlias.'.componente_curricular_id')
                ->orWhereExists(function ($vinculos) use ($turmaAlias, $pautaAlias, $turmaId): void {
                    $vinculos
                        ->selectRaw('1')
                        ->from('turma_componente_professor as tcp_nav')
                        ->where('tcp_nav.tem_professor', true)
                        ->whereIn('tcp_nav.professor_id', $this->professorIds)
                        ->whereColumn('tcp_nav.componente_curricular_id', $pautaAlias.'.componente_curricular_id');

                    $turmaId === null
                        ? $vinculos->whereColumn('tcp_nav.turma_id', $turmaAlias.'.id')
                        : $vinculos->where('tcp_nav.turma_id', $turmaId);
                });
        });
    }
}
