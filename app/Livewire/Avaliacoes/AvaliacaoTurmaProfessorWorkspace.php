<?php

namespace App\Livewire\Avaliacoes;

use App\Models\Avaliacao;
use App\Models\Pauta;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Services\Avaliacoes\AvaliacaoDashboardProgressService;
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

    protected ?Collection $turmasDaAvaliacaoProfessorCache = null;

    protected ?Collection $componentesNavegacaoCache = null;

    protected ?array $progressoAvaliacoesProfessorCache = null;

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

        if (! $this->avaliacao || ! $this->avaliacoesDisponiveis->contains('id', (int) $this->avaliacao)) {
            return $this->turmasDaAvaliacaoProfessorCache = collect();
        }

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
            })
            ->select('turmas.*')
            ->distinct()
            ->with(['escola:id,nome', 'serie:id,nome']);

        $this->aplicarEscopoTurmasNavegacao($query, 'turmas');

        return $this->turmasDaAvaliacaoProfessorCache = $query
            ->orderBy('turmas.id_escola')
            ->orderBy('turmas.id_serie')
            ->orderBy('turmas.nome')
            ->get();
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

    public function getProgressoAvaliacoesProfessorProperty(): array
    {
        if (is_array($this->progressoAvaliacoesProfessorCache)) {
            return $this->progressoAvaliacoesProfessorCache;
        }

        $ids = $this->avaliacoesDisponiveis
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $dados = app(AvaliacaoDashboardProgressService::class)->batch(
            $ids,
            $this->escolasPermitidasIds(),
            $this->deveFiltrarPorProfessor() ? $this->professorIds : null,
        );

        return $this->progressoAvaliacoesProfessorCache = collect($dados)
            ->mapWithKeys(fn ($item, $avaliacaoId): array => [
                (int) $avaliacaoId => (int) round((float) ($item->percentual ?? 0)),
            ])
            ->all();
    }

    public function agrupaNavegacaoPorEscola(): bool
    {
        return Auth::user()?->hasRole('Admin') ?? false;
    }

    public function selecionarEscolaNavegacao(int $escolaId): void
    {
        abort_unless(
            $this->agrupaNavegacaoPorEscola()
                && $this->turmasDaAvaliacaoProfessor->contains('id_escola', $escolaId),
            403,
        );

        $this->escolaNavegacaoId = $escolaId;
        $this->serieNavegacaoId = null;
        $this->turmasExpandidas = [];
        $this->fecharComponente();
    }

    public function selecionarSerieNavegacao(int $serieId): void
    {
        $turmas = $this->turmasDaAvaliacaoProfessor
            ->when($this->agrupaNavegacaoPorEscola(), fn (Collection $itens) => $itens
                ->where('id_escola', (int) $this->escolaNavegacaoId));

        abort_unless($turmas->contains('id_serie', $serieId), 403);

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

        $turmas = parent::getTurmasDisponiveisProperty();

        if (! $this->turma) {
            return $turmas;
        }

        return $this->turmasDisponiveisCache = $turmas
            ->filter(fn (Turma $turma): bool => (int) $turma->id === (int) $this->turma)
            ->values();
    }

    public function definirVisualizacao(string $visualizacao): void
    {
        if (! in_array($visualizacao, ['pautas', 'alunos'], true) || $this->visualizacao === $visualizacao) {
            return;
        }

        $this->visualizacao = $visualizacao;
        $this->pautaModalId = null;
        $this->alunoEmFoco = null;
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
    }

    public function selecionarAlunoModal(int $alunoId): void
    {
        abort_unless(
            $this->turma && $this->alunosDaTurma((int) $this->turma)->contains('id', $alunoId),
            403,
        );

        $this->alunoEmFoco = $alunoId;
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
