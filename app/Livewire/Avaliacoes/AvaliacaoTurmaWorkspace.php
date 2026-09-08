<?php

namespace App\Livewire\Avaliacoes;

use App\Models\Alternativa;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\AvaliacaoAlunoDocumento;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\Pauta;
use App\Models\Professor;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use App\Exceptions\AvaliacaoRespostaConcorrenteException;
use App\Services\Avaliacoes\AvaliacaoAlunoDocumentoService;
use App\Services\Avaliacoes\AvaliacaoPersistencia;
use App\Services\Avaliacoes\AvaliacaoRespostaStore;
use App\Services\Avaliacoes\AvaliacaoSnapshotService;
use App\Services\Avaliacoes\AvaliacaoTurmaCicloService;
use App\Services\Exports\ExportRequestService;
use App\Services\Avaliacoes\TurmaAvaliacaoAlunoScopeService;
use App\Services\PessoaScopeService;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class AvaliacaoTurmaWorkspace extends Component
{
    private const LIMITE_CARACTERES_TEXTO = 1500;

    public ?int $avaliacaoId = null;

    public ?int $turmaId = null;

    public ?int $escolaId = null;

    public ?int $serieId = null;

    public ?int $initialComponenteId = null;

    public string $modo = 'professor';

    public bool $canEdit = false;

    public ?int $avaliacao = null;

    public ?int $serie = null;

    public ?int $escola = null;

    public ?string $serieEscola = null;

    public ?int $turma = null;

    public array $respostas = [];

    public array $respostaVersoes = [];

    public array $respostasPersistidas = [];

    public array $informacoesComplementares = [];

    public array $informacoesComplementaresPersistidas = [];

    public array $informacaoVersoes = [];

    public array $informacoesComplementaresBloqueadas = [];

    public array $alternativasPorPauta = [];

    public ?int $avaliacaoEmMassaGlobal = null;

    public ?int $turmaEmMassaGlobal = null;

    public ?int $alunoEmMassaGlobal = null;

    public ?int $componenteEmMassaGlobal = null;

    public array $pautasExpandidas = [];

    public array $alunosExpandidos = [];

    public array $turmasExpandidas = [];

    public array $componentesExpandidos = [];

    public string $visualizacao = 'pautas';

    public ?int $alunoEmFoco = null;

    public array $professorIds = [];

    public array $turmaIdsProfessor = [];

    public array $componentesPorTurma = [];

    public array $professoresPorTurmaComponente = [];

    protected array $nomesProfessoresPorId = [];

    public array $ciclosPorTurma = [];

    public string $componenteWorkspaceId = '';

    protected ?Collection $avaliacoesDisponiveisCache = null;

    protected ?Avaliacao $avaliacaoAtualCache = null;

    protected bool $avaliacaoAtualCacheCarregado = false;

    protected ?Collection $turmasDisponiveisCache = null;

    protected ?Collection $pautasBaseDisponiveisCache = null;

    protected ?Collection $pautasDisponiveisCache = null;

    protected ?Collection $alunosPorTurmaCache = null;

    protected ?Collection $alunosDaSerieCache = null;

    protected ?Collection $documentosAvaliacaoCache = null;

    /** @var array<int, Collection> */
    protected array $pautasPorTurmaCache = [];

    /** @var array<int, Turma> */
    protected array $turmasDaSeriePorIdCache = [];

    /** @var array<int, Aluno> */
    protected array $alunosPorIdCache = [];

    /** @var array<int, int> */
    protected array $turmaIdPorAlunoIdCache = [];

    /** @var array<int, array<int, array>> */
    protected array $alternativasPorPautaPorIdCache = [];

    protected bool $professoresPrecarregados = false;

    protected ?array $progressoConsolidadoCache = null;

    protected ?array $componentesDisponiveisNoWorkspaceCache = null;

    protected bool $escolasPermitidasIdsCacheCarregado = false;

    protected ?array $escolasPermitidasIdsCache = null;

    public function mount(
        ?int $avaliacaoId = null,
        ?int $turmaId = null,
        ?int $escolaId = null,
        ?int $serieId = null,
        ?int $initialComponenteId = null,
        string $modo = 'professor',
        bool $canEdit = false
    ): void {
        $this->avaliacaoId = $avaliacaoId;
        $this->turmaId = $turmaId;
        $this->escolaId = $escolaId;
        $this->serieId = $serieId;
        $this->initialComponenteId = $initialComponenteId;
        $this->modo = in_array($modo, ['professor', 'acompanhamento'], true) ? $modo : 'professor';
        $this->canEdit = $canEdit;

        $visualizacaoQuery = (string) request()->query('visualizacao', '');
        if (in_array($visualizacaoQuery, ['pautas', 'alunos'], true)) {
            $this->visualizacao = $visualizacaoQuery;
        }

        $this->alunoEmFoco = $this->normalizarQueryId(request()->query('aluno'));
        if (! $this->modoAcompanhamento()) {
            $this->sincronizarVinculosProfessor();
        }

        if ($this->modoAcompanhamento()) {
            $this->avaliacao = $avaliacaoId;
            $this->turma = $turmaId;
            $this->escola = $escolaId;
            $this->serie = $serieId;
            $this->turmaEmMassaGlobal = $turmaId;
            $this->serieEscola = $this->escola && $this->serie
                ? $this->chaveSerieEscola($this->escola, $this->serie)
                : null;
            $this->componenteWorkspaceId = $initialComponenteId !== null ? (string) $initialComponenteId : '';
            $this->turmasExpandidas = $turmaId ? [$turmaId] : [];
        } else {
            $avaliacaoQuery = $this->normalizarQueryId(request()->query('avaliacao'));
            $serieQuery = $this->normalizarQueryId(request()->query('serie'));
            $escolaQuery = $this->normalizarQueryId(request()->query('escola'));
            $turmaQuery = $this->normalizarQueryId(request()->query('turma'));

            $this->avaliacao = $avaliacaoId ?: $avaliacaoQuery;

            if ($turmaId) {
                $this->turma = $turmaId;
                $this->serie = $this->serieDaTurma($turmaId);
                $this->escola = $escolaId ?: $this->escolaDaTurma($turmaId);
                $this->sincronizarSerieEscola();
            } elseif ($turmaQuery) {
                $this->turma = $turmaQuery;
                $this->serie = $this->serieDaTurma($turmaQuery);
                $this->escola = $this->escolaDaTurma($turmaQuery);
                $this->sincronizarSerieEscola();
            } elseif ($serieId) {
                $this->serie = $serieId;
                $this->escola = $escolaId ?: $this->escolaDaSerie($serieId);
                $this->sincronizarSerieEscola();
            } elseif ($serieQuery) {
                $this->serie = $serieQuery;
                $this->escola = $escolaQuery ?: $this->escolaDaSerie($serieQuery);
                $this->sincronizarSerieEscola();
            }
        }

        if ($this->avaliacao && $this->serie) {
            $this->carregarDadosDoEscopo();

            if (! $this->modoAcompanhamento() && $this->turma) {
                $this->turmasExpandidas = [(int) $this->turma];

                if ($this->alunoEmFoco && ! $this->alunosDaTurma((int) $this->turma)->contains('id', $this->alunoEmFoco)) {
                    $this->alunoEmFoco = null;
                }
            }
        }
    }

    public function render()
    {
        return view('livewire.avaliacoes.avaliacao-turma-workspace');
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="dav-dashboard-loading" role="status" aria-live="polite">
                <div class="dav-dashboard-loading__pulse"></div>
                <div>
                    <strong>Carregando avaliação...</strong>
                    <span>Aguarde enquanto os dados da turma são preparados.</span>
                </div>
            </div>
        HTML;
    }

    public function modoAcompanhamento(): bool
    {
        return $this->modo === 'acompanhamento';
    }

    public function podeResponder(): bool
    {
        return $this->canEdit
            && ($this->avaliacaoAtual?->estaAbertaParaPreenchimento() ?? false);
    }

    public function podePreencherEmMassa(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $this->podeResponder()
            && ($user && Gate::forUser($user)->allows('fillBulk', Avaliacao::class));
    }

    public function podeExportarTurma(int $turmaId): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user !== null
            && $this->avaliacaoAtual !== null
            && $this->turmasDaSerieDisponiveis->contains('id', $turmaId)
            && (Gate::forUser($user)->allows('export', Avaliacao::class)
                || Gate::forUser($user)->allows('conclude', Avaliacao::class));
    }

    public function podeConcluirTurma(int $turmaId): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        $progresso = $this->progressoPorTurma[$turmaId] ?? [];
        $ciclo = $this->ciclosPorTurma[$turmaId] ?? null;

        return app(AvaliacaoPersistencia::class)->leRelacional()
            && $user !== null
            && Gate::forUser($user)->allows('conclude', Avaliacao::class)
            && $this->podeExportarTurma($turmaId)
            && (int) ($progresso['total'] ?? 0) > 0
            && (bool) ($progresso['concluida'] ?? false)
            && in_array($ciclo['status'] ?? null, [AvaliacaoTurmaCiclo::STATUS_ABERTA, AvaliacaoTurmaCiclo::STATUS_REABERTA], true);
    }

    public function exportarTurma(int $turmaId): void
    {
        abort_unless($this->podeExportarTurma($turmaId), 403);
        $this->enfileirarExportacaoTurma($turmaId);
    }

    public function concluirTurmaEExportar(int $turmaId): void
    {
        abort_unless($this->podeConcluirTurma($turmaId), 403);

        /** @var User $user */
        $user = Auth::user();
        $cicloId = (int) ($this->ciclosPorTurma[$turmaId]['id'] ?? 0);
        app(AvaliacaoSnapshotService::class)->concluir($cicloId, $user);
        $this->enfileirarExportacaoTurma($turmaId);
        $this->carregarCiclosDoEscopo();
        $this->carregarRespostas();
        $this->carregarInformacoesComplementares();
        $this->limparCachesDeProgresso();

        Notification::make()
            ->title('Turma concluída e exportação enviada para a fila.')
            ->success()
            ->send();
    }

    public function podeAlternarVisualizacao(): bool
    {
        return true;
    }

    public function updatedAvaliacao(): void
    {
        if ($this->modoAcompanhamento()) {
            return;
        }

        $this->serie = null;
        $this->escola = null;
        $this->serieEscola = null;
        $this->turma = null;
        $this->limparDadosDoEscopo(true);
    }

    public function updatedSerieEscola(): void
    {
        if ($this->modoAcompanhamento()) {
            return;
        }

        $this->turma = null;
        $this->aplicarSerieEscolaSelecionada();
        $this->limparDadosDoEscopo(true);

        if (! $this->escopoSerieSelecionadoEhValido()) {
            $this->serie = null;
            $this->escola = null;
            $this->serieEscola = null;

            return;
        }

        $this->carregarDadosDoEscopo();
    }

    public function updatedTurma(): void
    {
        if ($this->modoAcompanhamento()) {
            return;
        }

        $this->serie = $this->turma ? $this->serieDaTurma((int) $this->turma) : null;
        $this->escola = $this->turma ? $this->escolaDaTurma((int) $this->turma) : null;
        $this->sincronizarSerieEscola();
        $this->limparDadosDoEscopo(true);
        $this->carregarDadosDoEscopo();
    }

    public function updatedTurmaEmMassaGlobal(): void
    {
        $this->alunoEmMassaGlobal = null;
        $this->componenteEmMassaGlobal = null;
    }

    public function updatedComponenteWorkspaceId(): void
    {
        if (! $this->modoAcompanhamento()) {
            return;
        }

        $this->componenteWorkspaceId = '';
        $this->limparDadosDoEscopo();
        $this->turmasExpandidas = $this->turma ? [$this->turma] : [];
        $this->carregarDadosDoEscopo();
    }

    public function definirVisualizacao(string $visualizacao): void
    {
        if (! in_array($visualizacao, ['pautas', 'alunos'], true)) {
            return;
        }

        if ($this->visualizacao === $visualizacao) {
            return;
        }

        $this->visualizacao = $visualizacao;

        if (! $this->modoAcompanhamento()) {
            return;
        }

        if ($visualizacao === 'pautas') {
            $this->alunosExpandidos = [];

            return;
        }

        $this->pautasExpandidas = [];
    }

    public function alternarTurma(int $turmaId): void
    {
        $indice = array_search($turmaId, $this->turmasExpandidas, true);

        if ($indice !== false) {
            unset($this->turmasExpandidas[$indice]);
            $this->turmasExpandidas = array_values($this->turmasExpandidas);

            return;
        }

        $this->turmasExpandidas[] = $turmaId;
    }

    public function turmaEstaExpandida(int $turmaId): bool
    {
        return in_array($turmaId, $this->turmasExpandidas, true);
    }

    public function alternarComponente(int $turmaId, int $componenteId): void
    {
        $chave = $this->chaveExpansao($turmaId, $componenteId);
        $indice = array_search($chave, $this->componentesExpandidos, true);

        if ($indice !== false) {
            unset($this->componentesExpandidos[$indice]);
            $this->componentesExpandidos = array_values($this->componentesExpandidos);

            return;
        }

        $this->componentesExpandidos[] = $chave;
    }

    public function componenteEstaExpandido(int $turmaId, int $componenteId): bool
    {
        return in_array($this->chaveExpansao($turmaId, $componenteId), $this->componentesExpandidos, true);
    }

    public function alternarPauta(int $turmaId, int $pautaId): void
    {
        $chave = $this->chaveExpansao($turmaId, $pautaId);
        $indice = array_search($chave, $this->pautasExpandidas, true);

        if ($indice !== false) {
            unset($this->pautasExpandidas[$indice]);
            $this->pautasExpandidas = array_values($this->pautasExpandidas);

            return;
        }

        if ($this->modoAcompanhamento()) {
            $prefixoTurma = $turmaId.':';
            $this->pautasExpandidas = array_values(array_filter(
                $this->pautasExpandidas,
                fn (string $item): bool => ! str_starts_with($item, $prefixoTurma)
            ));
        }

        $this->pautasExpandidas[] = $chave;
    }

    public function pautaEstaExpandida(int $turmaId, int $pautaId): bool
    {
        return in_array($this->chaveExpansao($turmaId, $pautaId), $this->pautasExpandidas, true);
    }

    public function rotuloTurma(Turma $turma): string
    {
        $nome = trim((string) $turma->nome);

        if ($nome === '') {
            return 'Turma';
        }

        return preg_match('/^turma\b/i', $nome) === 1 ? $nome : 'Turma '.$nome;
    }

    public function nomeTurma(Turma $turma): string
    {
        return implode(' - ', array_filter([
            $turma->escola?->nome,
            $turma->serie?->nome,
            $this->rotuloTurma($turma),
        ]));
    }

    public function alternarAluno(int $turmaId, int $alunoId): void
    {
        $chave = $this->chaveExpansao($turmaId, $alunoId);
        $indice = array_search($chave, $this->alunosExpandidos, true);

        if ($indice !== false) {
            unset($this->alunosExpandidos[$indice]);
            $this->alunosExpandidos = array_values($this->alunosExpandidos);

            return;
        }

        if ($this->modoAcompanhamento()) {
            $prefixoTurma = $turmaId.':';
            $this->alunosExpandidos = array_values(array_filter(
                $this->alunosExpandidos,
                fn (string $item): bool => ! str_starts_with($item, $prefixoTurma)
            ));
        }

        $this->alunosExpandidos[] = $chave;
    }

    public function alunoEstaExpandido(int $turmaId, int $alunoId): bool
    {
        return in_array($this->chaveExpansao($turmaId, $alunoId), $this->alunosExpandidos, true);
    }

    public function updated(string $name): void
    {
        if (str_starts_with($name, 'informacoesComplementares.')) {
            [, $componenteId, $alunoId] = array_pad(explode('.', $name), 3, null);

            if (is_numeric($componenteId) && is_numeric($alunoId)
                && ! $this->alunoEstaBloqueadoParaAvaliacao((int) $alunoId)
                && ! $this->informacaoComplementarEstaBloqueada((int) $componenteId, (int) $alunoId)) {
                $this->autoSalvarInformacaoComplementar((int) $componenteId, (int) $alunoId);
                $this->informacoesComplementaresPersistidas[(int) $componenteId][(int) $alunoId] =
                    $this->informacoesComplementares[(int) $componenteId][(int) $alunoId] ?? null;
            }

            // Autosave already updates the input and its persisted state. A
            // complete Blade render here repeats the whole evaluation matrix
            // for every keystroke and turns concurrent use into CPU pressure.
            $this->skipRender();

            return;
        }

        if (! str_starts_with($name, 'respostas.')) {
            return;
        }

        $partes = explode('.', $name);
        if (count($partes) < 4) {
            return;
        }

        [, $pautaId, $alunoId, $campo] = $partes;
        if (! is_numeric($pautaId) || ! is_numeric($alunoId)
            || ! in_array($campo, ['alternativa_id', 'observacao'], true)
            || $this->alunoEstaBloqueadoParaAvaliacao((int) $alunoId)
            || $this->respostaEstaBloqueada((int) $pautaId, (int) $alunoId)) {
            return;
        }

        $this->autoSalvarResposta((int) $pautaId, (int) $alunoId, $campo);
        $this->skipRender();
    }

    public function salvarAlteracoes(): void
    {
        $this->abortSeNaoPuderResponder();

        $respostasAlteradas = [];
        $informacoesAlteradas = [];

        foreach ($this->respostas as $pautaId => $respostasPorAluno) {
            foreach ($respostasPorAluno as $alunoId => $resposta) {
                $atual = [
                    'alternativa_id' => (int) ($resposta['alternativa_id'] ?? 0) ?: null,
                    'observacao' => $this->limitarTextoCampo($resposta['observacao'] ?? '') ?: null,
                ];
                $persistida = [
                    'alternativa_id' => (int) ($this->respostasPersistidas[$pautaId][$alunoId]['alternativa_id'] ?? 0) ?: null,
                    'observacao' => $this->limitarTextoCampo($this->respostasPersistidas[$pautaId][$alunoId]['observacao'] ?? '') ?: null,
                ];

                if ($atual !== $persistida) {
                    $respostasAlteradas[] = [(int) $pautaId, (int) $alunoId];
                }
            }
        }

        foreach ($this->informacoesComplementares as $componenteId => $informacoesPorAluno) {
            foreach ($informacoesPorAluno as $alunoId => $texto) {
                $atual = $this->limitarTextoCampo($texto) ?: null;
                $persistida = $this->limitarTextoCampo($this->informacoesComplementaresPersistidas[$componenteId][$alunoId] ?? '') ?: null;

                if ($atual !== $persistida) {
                    $informacoesAlteradas[] = [(int) $componenteId, (int) $alunoId];
                }
            }
        }

        if ($respostasAlteradas === [] && $informacoesAlteradas === []) {
            $this->dispatch('avaliacao-salva');
            Notification::make()->title('Alterações confirmadas.')->success()->send();

            return;
        }

        foreach ($respostasAlteradas as [$pautaId, $alunoId]) {
            $alternativaId = (int) ($this->respostas[$pautaId][$alunoId]['alternativa_id'] ?? 0);
            if ($alternativaId > 0
                && $this->alternativaRequerObservacao($pautaId, $alternativaId)
                && $this->limitarTextoCampo($this->respostas[$pautaId][$alunoId]['observacao'] ?? '') === '') {
                Notification::make()
                    ->title('Existem observações obrigatórias não preenchidas.')
                    ->warning()
                    ->send();

                return;
            }
        }

        $registrosRespostas = [];
        foreach ($respostasAlteradas as [$pautaId, $alunoId]) {
            if ($this->respostaEstaBloqueada($pautaId, $alunoId)) {
                continue;
            }

            $aluno = $this->alunoDaSerieSelecionada($alunoId);
            $turmaId = (int) ($aluno ? ($this->turmaAvaliativaDoAluno($aluno)?->id ?? $aluno->id_turma) : 0);
            $pauta = $this->pautasDaTurma($turmaId)->firstWhere('id', $pautaId);
            if (! $aluno || $this->alunoEstaBloqueadoParaAvaliacao($aluno) || ! $pauta) {
                continue;
            }

            $alternativaId = (int) ($this->respostas[$pautaId][$alunoId]['alternativa_id'] ?? 0);
            $alternativa = $this->alternativaDaPauta($pautaId, $alternativaId);
            $observacao = $this->limitarTextoCampo($this->respostas[$pautaId][$alunoId]['observacao'] ?? '');
            $temObservacao = (bool) ($alternativa['tem_observacao'] ?? false);

            if (! $alternativa || ($temObservacao && $observacao === '')) {
                $dados = ['alternativa_id' => null, 'observacao' => null];
            } else {
                $dados = [
                    'alternativa_id' => $alternativaId,
                    'observacao' => $temObservacao ? $observacao : null,
                    'professor_id' => $this->professorIdParaRegistro($turmaId, $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null),
                    'componente_curricular_id' => $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null,
                    'respondido_em' => now(),
                ];
            }

            $registrosRespostas[] = [
                'chave' => $pautaId.':'.$alunoId,
                'avaliacao_id' => (int) $this->avaliacao,
                'turma_avaliativa_id' => $turmaId,
                'aluno' => $aluno,
                'pauta_id' => $pautaId,
                'dados' => $dados,
                'expected_version' => ($this->respostaVersoes[$pautaId][$alunoId] ?? 0) > 0
                    ? (int) $this->respostaVersoes[$pautaId][$alunoId]
                    : null,
                'expected_values' => [
                    'alternativa_id' => $this->respostasPersistidas[$pautaId][$alunoId]['alternativa_id'] ?? null,
                    'observacao' => $this->respostasPersistidas[$pautaId][$alunoId]['observacao'] ?? null,
                ],
            ];
        }

        $registrosInformacoes = [];
        foreach ($informacoesAlteradas as [$componenteId, $alunoId]) {
            if ($this->informacaoComplementarEstaBloqueada($componenteId, $alunoId)) {
                continue;
            }

            $aluno = $this->alunoDaSerieSelecionada($alunoId);
            $turmaId = (int) ($aluno ? ($this->turmaAvaliativaDoAluno($aluno)?->id ?? $aluno->id_turma) : 0);
            if (! $aluno || $this->alunoEstaBloqueadoParaAvaliacao($aluno) || $turmaId <= 0) {
                continue;
            }

            $registrosInformacoes[] = [
                'chave' => $componenteId.':'.$alunoId,
                'avaliacao_id' => (int) $this->avaliacao,
                'turma_avaliativa_id' => $turmaId,
                'aluno' => $aluno,
                'componente_id' => $componenteId,
                'texto' => $this->limitarTextoCampo($this->informacoesComplementares[$componenteId][$alunoId] ?? '') ?: null,
                'professor_id' => $this->professorIdParaRegistro($turmaId, $componenteId > 0 ? $componenteId : null),
                'expected_version' => ($this->informacaoVersoes[$componenteId][$alunoId] ?? 0) > 0
                    ? (int) $this->informacaoVersoes[$componenteId][$alunoId]
                    : null,
            ];
        }

        $store = app(AvaliacaoRespostaStore::class);
        try {
            $resultado = $store->salvarAlteracoesEmMassa($registrosRespostas, []);
            foreach ($registrosRespostas as $registro) {
                $alunoId = (int) $registro['aluno']->id;
                $pautaId = (int) $registro['pauta_id'];
                $alternativaId = $registro['dados']['alternativa_id'] ?? null;
                $this->respostaVersoes[$pautaId][$alunoId] = (int) ($resultado['respostas'][$registro['chave']] ?? 0);
                $this->respostasPersistidas[$pautaId][$alunoId] = [
                    'alternativa_id' => $alternativaId ? (int) $alternativaId : null,
                    'observacao' => $registro['dados']['observacao'] ?? null,
                ];
            }
        } catch (AvaliacaoRespostaConcorrenteException $exception) {
            $this->carregarRespostas();
            Notification::make()->title($exception->getMessage())->warning()->send();
        }

        try {
            $resultado = $store->salvarAlteracoesEmMassa([], $registrosInformacoes);
            foreach ($registrosInformacoes as $registro) {
                $alunoId = (int) $registro['aluno']->id;
                $componenteId = (int) $registro['componente_id'];
                $this->informacaoVersoes[$componenteId][$alunoId] = (int) ($resultado['informacoes'][$registro['chave']] ?? 0);
                $this->informacoesComplementaresPersistidas[$componenteId][$alunoId] = $registro['texto'];
            }
        } catch (AvaliacaoRespostaConcorrenteException $exception) {
            $this->carregarInformacoesComplementares();
            Notification::make()->title($exception->getMessage())->warning()->send();
        }

        $this->limparCachesDeProgresso();
        $this->dispatch('avaliacao-salva');
        Notification::make()->title('Alterações salvas com sucesso.')->success()->send();
        $this->emitirAtualizacaoDoWorkspaceAcompanhamento();
    }

    public function aplicarEmMassaNaSerie(): void
    {
        $this->abortSeNaoPuderPreencherEmMassa();

        if (! $this->avaliacao || ! $this->serie) {
            Notification::make()
                ->title('Selecione uma avaliação e um escopo válido para aplicar em massa.')
                ->warning()
                ->send();

            return;
        }

        $alternativaId = (int) ($this->avaliacaoEmMassaGlobal ?? 0);

        if ($alternativaId <= 0) {
            Notification::make()
                ->title('Selecione uma alternativa para aplicar em massa.')
                ->warning()
                ->send();

            return;
        }

        $payload = [];
        $pendencias = [];
        $totalIgnoradoPorPreenchimento = 0;
        $alunosPendentesTransferenciaIgnorados = [];
        $pautasIgnoradas = 0;
        $agora = now();

        $turmasAlvo = $this->turmasAlvoAvaliacaoEmMassa();

        if ($turmasAlvo->isEmpty()) {
            Notification::make()
                ->title('Selecione uma turma válida para aplicar em massa.')
                ->warning()
                ->send();

            return;
        }

        if (
            $this->alunoEmMassaGlobal
            && $turmasAlvo->every(fn (Turma $turma): bool => $this->alunosAlvoAvaliacaoEmMassa((int) $turma->id)->isEmpty())
        ) {
            Notification::make()
                ->title('Selecione um aluno válido para aplicar em massa.')
                ->warning()
                ->send();

            return;
        }

        if (
            $this->componenteEmMassaGlobal !== null
            && $turmasAlvo->every(fn (Turma $turma): bool => $this->pautasAlvoAvaliacaoEmMassa((int) $turma->id)->isEmpty())
        ) {
            Notification::make()
                ->title('Selecione um componente válido para aplicar em massa.')
                ->warning()
                ->send();

            return;
        }

        if ($turmasAlvo->every(
            fn (Turma $turma): bool => $this->alunosAlvoAvaliacaoEmMassa((int) $turma->id)->isEmpty()
                || $this->pautasAlvoAvaliacaoEmMassa((int) $turma->id)->isEmpty()
        )) {
            Notification::make()
                ->title('O aluno e o componente selecionados não pertencem ao mesmo escopo.')
                ->warning()
                ->send();

            return;
        }

        foreach ($turmasAlvo as $turma) {
            $turmaId = (int) $turma->id;

            foreach ($this->pautasAlvoAvaliacaoEmMassa($turmaId) as $pauta) {
                $alternativa = $this->alternativaDaPauta((int) $pauta->id, $alternativaId);

                if (! $alternativa) {
                    $pautasIgnoradas++;

                    continue;
                }

                foreach ($this->alunosAlvoAvaliacaoEmMassa($turmaId) as $aluno) {
                    $alunoId = (int) $aluno->id;

                    if ($this->alunoEstaBloqueadoParaAvaliacao($aluno)) {
                        $alunosPendentesTransferenciaIgnorados[$alunoId] = true;

                        continue;
                    }

                    if ($this->respostaEstaBloqueada((int) $pauta->id, $alunoId)) {
                        $totalIgnoradoPorPreenchimento++;

                        continue;
                    }

                    if ($this->respostaTemObservacao((int) $pauta->id, $alunoId)) {
                        $totalIgnoradoPorPreenchimento++;

                        continue;
                    }

                    $this->respostas[$pauta->id][$alunoId]['alternativa_id'] = $alternativaId;

                    $observacaoInformada = $this->limitarTextoCampo($this->respostas[$pauta->id][$alunoId]['observacao'] ?? '');
                    $temObservacao = (bool) ($alternativa['tem_observacao'] ?? false);

                    if (! $temObservacao) {
                        $this->respostas[$pauta->id][$alunoId]['observacao'] = null;
                        $observacaoInformada = '';
                    }

                    if ($temObservacao && $observacaoInformada === '') {
                        $pendencias[$turmaId.':'.$pauta->id][] = $alunoId;

                        continue;
                    }

                    $payload[$alunoId][(int) $pauta->id] = [
                        'alternativa_id' => $alternativaId,
                        'observacao' => $temObservacao ? $observacaoInformada : null,
                        'professor_id' => $this->professorIdParaRegistro($turmaId, $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null),
                        'componente_curricular_id' => $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null,
                        'respondido_em' => $agora,
                    ];
                }
            }
        }

        if ($payload === [] && $pendencias === []) {
            $mensagem = $alunosPendentesTransferenciaIgnorados !== []
                ? 'Alunos pendentes de transferência foram mantidos bloqueados.'
                : 'Respostas já preenchidas foram mantidas.';

            Notification::make()
                ->title('Nenhuma resposta alterada.')
                ->body($mensagem)
                ->warning()
                ->send();

            return;
        }

        foreach ($pendencias as $chave => $alunosIds) {
            [, $pautaId] = array_map('intval', explode(':', $chave));

            foreach ($alunosIds as $alunoId) {
                $payload[(int) $alunoId][$pautaId] = ['alternativa_id' => null];
            }
        }

        $alunosPorId = $this->alunosDaSerie
            ->keyBy(fn (Aluno $aluno): int => (int) $aluno->id);
        $alunosAlterados = collect(array_keys($payload))
            ->map(fn ($alunoId) => $alunosPorId->get((int) $alunoId))
            ->filter()
            ->values();

        app(AvaliacaoRespostaStore::class)->salvarPautasEmMassaParaAlunos(
            (int) $this->avaliacao,
            $alunosAlterados,
            $payload,
            $alunosAlterados->mapWithKeys(fn (Aluno $aluno): array => [
                (int) $aluno->id => (int) ($this->turmaAvaliativaDoAluno($aluno)?->id ?? $aluno->id_turma),
            ])->all(),
        );

        $mensagens = [];

        if ($pautasIgnoradas > 0) {
            $mensagens[] = $pautasIgnoradas.' pauta(s) não possuem esta alternativa.';
        }

        if ($alunosPendentesTransferenciaIgnorados !== []) {
            $mensagens[] = count($alunosPendentesTransferenciaIgnorados).' aluno(s) pendente(s) de transferência foram ignorados.';
        }

        if ($totalIgnoradoPorPreenchimento > 0) {
            $mensagens[] = $totalIgnoradoPorPreenchimento.' resposta(s) já preenchida(s) ou bloqueada(s) foram mantidas.';
        }

        Notification::make()
            ->title('Avaliação em massa aplicada.')
            ->body(implode(' ', $mensagens))
            ->success()
            ->send();

        $this->limparCachesDeProgresso();
        $this->emitirAtualizacaoDoWorkspaceAcompanhamento();
    }

    public function salvarRespostas(): void
    {
        $this->abortSeNaoPuderResponder();

        if (! $this->avaliacao || ! $this->serie) {
            Notification::make()
                ->title('Selecione uma avaliação e um escopo válido para continuar.')
                ->warning()
                ->send();

            return;
        }

        if ($this->pautasDisponiveis->isEmpty() || $this->alunosDaSerie->isEmpty()) {
            Notification::make()
                ->title('Não há pautas ou alunos disponíveis para avaliação.')
                ->warning()
                ->send();

            return;
        }

        $faltandoResposta = 0;
        $faltandoObservacao = 0;
        /** @var array<int, array<int, array>> $payloadPorAluno */
        $payloadPorAluno = [];
        $agora = now();

        foreach ($this->turmasDaSerieDisponiveis as $turma) {
            $turmaId = (int) $turma->id;

            foreach ($this->pautasDaTurma($turmaId) as $pauta) {
                foreach ($this->alunosDaTurma($turmaId) as $aluno) {
                    if ($this->alunoEstaBloqueadoParaAvaliacao($aluno)) {
                        continue;
                    }

                    if ($this->respostaEstaBloqueada((int) $pauta->id, (int) $aluno->id)) {
                        if (! $this->respostaEstaCompleta($pauta, (int) $aluno->id)) {
                            $faltandoResposta++;
                        }

                        continue;
                    }

                    $alternativaId = (int) ($this->respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? 0);
                    $alternativa = $this->alternativaDaPauta((int) $pauta->id, $alternativaId);

                    if (! $alternativa) {
                        $faltandoResposta++;

                        continue;
                    }

                    $observacaoInformada = $this->limitarTextoCampo($this->respostas[$pauta->id][$aluno->id]['observacao'] ?? '');
                    $temObservacao = (bool) ($alternativa['tem_observacao'] ?? false);

                    if ($temObservacao && $observacaoInformada === '') {
                        $faltandoObservacao++;

                        continue;
                    }

                    $payloadPorAluno[(int) $aluno->id][(int) $pauta->id] = [
                        'alternativa_id' => $alternativaId,
                        'observacao' => $temObservacao ? $observacaoInformada : null,
                        'professor_id' => $this->professorIdParaRegistro($turmaId, $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null),
                        'componente_curricular_id' => $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null,
                        'respondido_em' => $agora,
                    ];
                }
            }
        }

        if ($faltandoResposta > 0 || $faltandoObservacao > 0) {
            $mensagens = [];

            if ($faltandoResposta > 0) {
                $mensagens[] = 'Preencha todas as combinações de aluno e pauta.';
            }

            if ($faltandoObservacao > 0) {
                $mensagens[] = 'Algumas alternativas exigem observação obrigatória.';
            }

            Notification::make()
                ->title('Existem pendências no preenchimento.')
                ->body(implode(' ', $mensagens))
                ->warning()
                ->send();

            return;
        }

        if ($payloadPorAluno !== []) {
            $alunos = collect(array_keys($payloadPorAluno))
                ->map(fn ($alunoId) => $this->alunoDaSerieSelecionada((int) $alunoId))
                ->filter()
                ->values();

            app(AvaliacaoRespostaStore::class)->salvarPautasEmMassaParaAlunos(
                (int) $this->avaliacao,
                $alunos,
                $payloadPorAluno,
                $alunos->mapWithKeys(fn (Aluno $aluno): array => [
                    (int) $aluno->id => (int) ($this->turmaAvaliativaDoAluno($aluno)?->id ?? $aluno->id_turma),
                ])->all(),
            );
        }

        Notification::make()
            ->title('Avaliação salva com sucesso.')
            ->success()
            ->send();

        $this->limparCachesDeProgresso();
        $this->emitirAtualizacaoDoWorkspaceAcompanhamento();
    }

    public function alternativaRequerObservacao(int $pautaId, ?int $alternativaId): bool
    {
        $alternativa = $this->alternativaDaPauta($pautaId, $alternativaId);

        return (bool) ($alternativa['tem_observacao'] ?? false);
    }

    public function placeholderObservacaoAlternativa(int $pautaId, ?int $alternativaId): string
    {
        $alternativa = $this->alternativaDaPauta($pautaId, $alternativaId);
        $placeholder = trim((string) ($alternativa['observacao'] ?? ''));

        return $placeholder !== '' ? $placeholder : 'Observação obrigatória';
    }

    public function alternativasDaPauta(int $pautaId): array
    {
        return $this->alternativasPorPauta[$pautaId] ?? [];
    }

    public function getAlternativasEmMassaDisponiveisProperty(): Collection
    {
        return collect($this->alternativasPorPauta)
            ->flatten(1)
            ->filter(fn (array $alternativa): bool => isset($alternativa['id'], $alternativa['nome']))
            ->unique(fn (array $alternativa): int => (int) $alternativa['id'])
            ->sortBy(fn (array $alternativa): string => (string) $alternativa['nome'])
            ->values();
    }

    public function getAvaliacoesDisponiveisProperty(): Collection
    {
        if ($this->avaliacoesDisponiveisCache instanceof Collection) {
            return $this->avaliacoesDisponiveisCache;
        }

        $query = Avaliacao::query()
            ->pendentesParaData(now());

        if ($this->modoAcompanhamento()) {
            $query->whereKey((int) $this->avaliacao);
            $query->whereHas('turmas', function ($turmas): void {
                if ($this->turma) {
                    $turmas->whereKey((int) $this->turma);
                }

                $this->aplicarEscopoEscolasPermitidas($turmas);
            });
        } else {
            $query->whereHas('turmas', function ($turmas): void {
                if ($this->avaliacao && $this->turma) {
                    $turmas->whereKey((int) $this->turma);
                }

                $this->aplicarEscopoEscolasPermitidas($turmas);
            });
        }

        if ($this->deveRestringirAsTurmasDoProfessor()) {
            $query->whereHas('turmas', function ($turmas): void {
                $turmas->whereIn('turmas.id', $this->turmaIdsProfessor);
            });
        }

        // O cache local da instância já evita consultas duplicadas durante a
        // requisição. Persistir este grafo Eloquent completo no Redis produz
        // valores com vários megabytes e pode estourar a memória ao desserializar.
        $avaliacoes = $query
            ->with([
                'tipo' => fn ($tipo) => $tipo->with([
                    'alternativas' => fn ($alternativas) => $alternativas->where('status', true),
                ]),
                'pautas' => fn ($pautas) => $pautas
                    ->where('status', true)
                    ->when($this->serie, fn ($query) => $query
                        ->where(fn ($serieQuery) => $serieQuery
                            ->whereNull('serie_id')
                            ->orWhere('serie_id', (int) $this->serie)))
                    ->with([
                        'componente:id,nome',
                        'tipo' => fn ($tipo) => $tipo->with([
                            'alternativas' => fn ($alternativas) => $alternativas->where('status', true),
                        ]),
                        'alternativas' => fn ($alternativas) => $alternativas->where('status', true),
                    ]),
                'turmas' => function ($turmas): void {
                    if ($this->turma) {
                        $turmas->whereKey((int) $this->turma);
                        $this->aplicarEscopoEscolasPermitidas($turmas);
                    }

                    $turmas->with(['escola:id,nome', 'serie:id,nome']);
                },
            ])
            ->orderBy('data_inicio')
            ->get();

        return $this->avaliacoesDisponiveisCache = $avaliacoes
            ->filter(fn (Avaliacao $avaliacao): bool => $this->filtrarTurmasDaAvaliacao($avaliacao)->isNotEmpty())
            ->values();
    }

    public function getAvaliacaoAtualProperty(): ?Avaliacao
    {
        if ($this->avaliacaoAtualCacheCarregado) {
            return $this->avaliacaoAtualCache;
        }

        $avaliacao = $this->avaliacoesDisponiveis->firstWhere('id', (int) $this->avaliacao);
        $this->avaliacaoAtualCache = $avaliacao;
        $this->avaliacaoAtualCacheCarregado = true;

        return $avaliacao;
    }

    public function getTurmasDisponiveisProperty(): Collection
    {
        if ($this->turmasDisponiveisCache instanceof Collection) {
            return $this->turmasDisponiveisCache;
        }

        if (! $this->avaliacaoAtual) {
            return $this->turmasDisponiveisCache = collect();
        }

        $turmas = $this->filtrarTurmasDaAvaliacao($this->avaliacaoAtual);

        if ($this->modoAcompanhamento() && $this->turma) {
            return $this->turmasDisponiveisCache = $turmas
                ->filter(fn (Turma $turma): bool => (int) $turma->id === (int) $this->turma)
                ->values();
        }

        return $this->turmasDisponiveisCache = $turmas;
    }

    public function getPautasBaseDisponiveisProperty(): Collection
    {
        if ($this->pautasBaseDisponiveisCache instanceof Collection) {
            return $this->pautasBaseDisponiveisCache;
        }

        if (! $this->avaliacaoAtual || ! $this->serie) {
            return $this->pautasBaseDisponiveisCache = collect();
        }

        $pautas = collect();

        foreach ($this->turmasDaSerieDisponiveis as $turma) {
            $pautas = $pautas->merge($this->filtrarPautasDaTurma($this->avaliacaoAtual->pautas, $turma, false));
        }

        return $this->pautasBaseDisponiveisCache = $pautas->unique('id')->values();
    }

    public function getPautasDisponiveisProperty(): Collection
    {
        if ($this->pautasDisponiveisCache instanceof Collection) {
            return $this->pautasDisponiveisCache;
        }

        $pautas = $this->pautasBaseDisponiveis;

        if ($pautas->isEmpty()) {
            return $this->pautasDisponiveisCache = collect();
        }

        $pautas = $pautas->values();

        $this->carregarAlternativasPorPauta($pautas);

        return $this->pautasDisponiveisCache = $pautas
            ->filter(fn (Pauta $pauta): bool => $this->alternativasDaPauta((int) $pauta->id) !== [])
            ->values();
    }

    public function getSeriesPorEscolaDisponiveisProperty(): Collection
    {
        return $this->turmasDisponiveis
            ->filter(fn (Turma $turma): bool => $turma->serie !== null && $turma->escola !== null)
            ->groupBy(fn (Turma $turma): string => $this->chaveSerieEscola((int) $turma->id_escola, (int) $turma->id_serie))
            ->map(function (Collection $turmas): array {
                /** @var Turma $turma */
                $turma = $turmas->first();

                return [
                    'value' => $this->chaveSerieEscola((int) $turma->id_escola, (int) $turma->id_serie),
                    'escola_id' => (int) $turma->id_escola,
                    'escola_nome' => (string) ($turma->escola?->nome ?? 'Escola sem nome'),
                    'serie_id' => (int) $turma->id_serie,
                    'serie_nome' => (string) ($turma->serie?->nome ?? 'Série sem nome'),
                ];
            })
            ->sortBy(fn (array $escopo): string => mb_strtolower($escopo['escola_nome'].'|'.$escopo['serie_nome']))
            ->values();
    }

    public function getTurmasDaSerieDisponiveisProperty(): Collection
    {
        if (! $this->serie) {
            return collect();
        }

        return $this->turmasDisponiveis
            ->filter(fn (Turma $turma): bool => (int) $turma->id_serie === (int) $this->serie
                && (! $this->escola || (int) $turma->id_escola === (int) $this->escola))
            ->values();
    }

    public function getAlunosPorTurmaProperty(): Collection
    {
        if ($this->alunosPorTurmaCache instanceof Collection) {
            return $this->alunosPorTurmaCache;
        }

        $turmas = $this->turmasDaSerieDisponiveis;
        $turmasIds = $turmas
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($turmasIds === []) {
            $this->alunosPorIdCache = [];
            $this->turmaIdPorAlunoIdCache = [];

            return $this->alunosPorTurmaCache = collect();
        }

        $escopos = app(TurmaAvaliacaoAlunoScopeService::class)->escoposPorTurma($turmas);

        $origens = collect($escopos)
            ->pluck('turma_origem_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $alunosPorOrigem = Aluno::query()
            ->whereIn('id_turma', $origens)
            ->whereIn('status', [
                Aluno::STATUS_MATRICULADO,
                Aluno::STATUS_PENDENTE,
            ])
            ->orderBy('nome')
            ->get([
                'id',
                'nome',
                'cgm',
                'id_turma',
                'status',
                'pendencia_origem_aluno_id',
                'tipo_vinculo',
            ])
            ->groupBy(fn (Aluno $aluno): int => (int) $aluno->id_turma)
            ->map(fn (Collection $alunos): Collection => $alunos->values());

        $alunosPorTurma = collect($escopos)
            ->mapWithKeys(fn (array $escopo, int $turmaId): array => [
                $turmaId => $alunosPorOrigem->get(
                    (int) $escopo['turma_origem_id'],
                    collect()
                ),
            ]);

        $this->turmaIdPorAlunoIdCache = [];
        $this->alunosPorIdCache = [];
        foreach ($alunosPorTurma as $turmaId => $alunos) {
            foreach ($alunos as $aluno) {
                $alunoId = (int) $aluno->id;
                $this->alunosPorIdCache[$alunoId] = $aluno;
                $this->turmaIdPorAlunoIdCache[$alunoId] = (int) $turmaId;
            }
        }

        return $this->alunosPorTurmaCache = $alunosPorTurma;
    }

    private function carregarIndicesDeAlunos(): void
    {
        if ($this->alunosPorIdCache !== [] || $this->alunosPorTurma->isEmpty()) {
            return;
        }

        foreach ($this->alunosPorTurma as $turmaId => $alunos) {
            foreach ($alunos as $aluno) {
                $alunoId = (int) $aluno->id;
                $this->alunosPorIdCache[$alunoId] = $aluno;
                $this->turmaIdPorAlunoIdCache[$alunoId] = (int) $turmaId;
            }
        }
    }

    public function getAlunosDaSerieProperty(): Collection
    {
        if ($this->alunosDaSerieCache instanceof Collection) {
            return $this->alunosDaSerieCache;
        }

        return $this->alunosDaSerieCache = $this->alunosPorTurma
            ->flatMap(fn (Collection $alunos): Collection => $alunos)
            ->values();
    }

    public function alunosDaTurma(int $turmaId): Collection
    {
        return $this->alunosPorTurma->get($turmaId, collect());
    }

    private function turmaAvaliativaDoAluno(Aluno $aluno): ?Turma
    {
        $turmaId = $this->turmaIdPorAlunoIdCache[(int) $aluno->id] ?? null;

        if ($turmaId === null) {
            $this->carregarIndicesDeAlunos();
            $turmaId = $this->turmaIdPorAlunoIdCache[(int) $aluno->id] ?? null;
        }

        return $turmaId === null
            ? null
            : ($this->turmasDaSeriePorIdCache[$turmaId]
                ??= $this->turmasDaSerieDisponiveis->firstWhere('id', $turmaId));
    }

    public function getAlunosEmMassaDisponiveisProperty(): Collection
    {
        return $this->turmasAlvoAvaliacaoEmMassa()
            ->filter(fn (Turma $turma): bool => $this->pautasAlvoAvaliacaoEmMassa((int) $turma->id)->isNotEmpty())
            ->flatMap(fn (Turma $turma): Collection => $this->alunosRespondiveisDaTurma((int) $turma->id))
            ->sortBy(fn (Aluno $aluno): string => mb_strtolower($this->rotuloAlunoEmMassa($aluno)))
            ->values();
    }

    public function rotuloAlunoEmMassa(Aluno $aluno): string
    {
        $turma = $this->turmaAvaliativaDoAluno($aluno);

        return implode(' - ', array_filter([
            $aluno->nome,
            $turma ? $this->rotuloTurma($turma) : null,
            $aluno->cgm ? 'CGM '.$aluno->cgm : null,
        ]));
    }

    public function getComponentesEmMassaDisponiveisProperty(): array
    {
        $opcoes = [];

        foreach ($this->turmasAlvoAvaliacaoEmMassa() as $turma) {
            if ($this->alunosAlvoAvaliacaoEmMassa((int) $turma->id)->isEmpty()) {
                continue;
            }

            foreach ($this->pautasDaTurma((int) $turma->id) as $pauta) {
                $componenteId = (int) ($pauta->componente_curricular_id ?? 0);
                $opcoes[$componenteId] = $pauta->componente?->nome ?? 'Geral (sem componente específico)';
            }
        }

        asort($opcoes);

        return $opcoes;
    }

    public function alunoEstaBloqueadoParaAvaliacao(Aluno|int $aluno): bool
    {
        if (is_int($aluno)) {
            $aluno = $this->alunoDaSerieSelecionada($aluno);
        }

        return $aluno instanceof Aluno
            && $aluno->status === Aluno::STATUS_PENDENTE
            && (int) $aluno->pendencia_origem_aluno_id > 0;
    }

    public function pautasDaTurma(int $turmaId): Collection
    {
        if (array_key_exists($turmaId, $this->pautasPorTurmaCache)) {
            return $this->pautasPorTurmaCache[$turmaId];
        }

        $turma = $this->turmasDaSerieDisponiveis->firstWhere('id', $turmaId);

        if (! $turma || ! $this->avaliacaoAtual) {
            return $this->pautasPorTurmaCache[$turmaId] = collect();
        }

        return $this->pautasPorTurmaCache[$turmaId] = $this->pautasDisponiveis
            ->filter(fn (Pauta $pauta): bool => $this->pautaEhDaSerieDaTurma($pauta, $turma)
                && $this->pautaEhRelevanteParaTurma($pauta, $turma))
            ->values();
    }

    public function getProgressoProperty(): array
    {
        $total = 0;
        $preenchidas = 0;

        foreach ($this->progressoPorTurma as $progressoTurma) {
            $total += (int) ($progressoTurma['total'] ?? 0);
            $preenchidas += (int) ($progressoTurma['preenchidas'] ?? 0);
        }

        return [
            'preenchidas' => $preenchidas,
            'total' => $total,
        ];
    }

    public function getProgressoPorTurmaProperty(): array
    {
        return $this->progressoConsolidado()['turmas'];
    }

    public function getProgressoPorPautaProperty(): array
    {
        return $this->progressoConsolidado()['pautas'];
    }

    public function getProgressoPorAlunoProperty(): array
    {
        return $this->progressoConsolidado()['alunos'];
    }

    /** @return array{turmas: array, pautas: array, alunos: array} */
    private function progressoConsolidado(): array
    {
        if (is_array($this->progressoConsolidadoCache)) {
            return $this->progressoConsolidadoCache;
        }

        $turmas = [];
        $pautas = [];
        $alunos = [];

        foreach ($this->turmasDaSerieDisponiveis as $turma) {
            $turmaId = (int) $turma->id;
            $pautasDaTurma = $this->pautasDaTurma($turmaId);
            $alunosDaTurma = $this->alunosDaTurma($turmaId);
            $alunosRespondiveis = $alunosDaTurma
                ->reject(fn (Aluno $aluno): bool => $this->alunoEstaBloqueadoParaAvaliacao($aluno))
                ->values();
            $preenchidasTurma = 0;
            $preenchidasPorPauta = [];
            $preenchidasPorAluno = [];

            foreach ($pautasDaTurma as $pauta) {
                $pautaId = (int) $pauta->id;
                $preenchidasPorPauta[$pautaId] = 0;

                foreach ($alunosRespondiveis as $aluno) {
                    $alunoId = (int) $aluno->id;

                    if (! $this->respostaEstaCompleta($pauta, $alunoId)) {
                        continue;
                    }

                    $preenchidasTurma++;
                    $preenchidasPorPauta[$pautaId]++;
                    $preenchidasPorAluno[$alunoId] = ($preenchidasPorAluno[$alunoId] ?? 0) + 1;
                }
            }

            $turmas[$turmaId] = $this->montarResumoProgresso(
                $preenchidasTurma,
                $pautasDaTurma->count() * $alunosRespondiveis->count(),
            );

            foreach ($pautasDaTurma as $pauta) {
                $pautaId = (int) $pauta->id;
                $pautas[$turmaId][$pautaId] = $this->montarResumoProgresso(
                    $preenchidasPorPauta[$pautaId] ?? 0,
                    $alunosRespondiveis->count(),
                );
            }

            foreach ($alunosDaTurma as $aluno) {
                $alunoId = (int) $aluno->id;
                $alunos[$alunoId] = $this->alunoEstaBloqueadoParaAvaliacao($aluno)
                    ? $this->montarResumoProgresso(0, 0)
                    : $this->montarResumoProgresso($preenchidasPorAluno[$alunoId] ?? 0, $pautasDaTurma->count());
            }
        }

        return $this->progressoConsolidadoCache = [
            'turmas' => $turmas,
            'pautas' => $pautas,
            'alunos' => $alunos,
        ];
    }

    public function pautasAgrupadasPorComponenteDaTurma(int $turmaId): Collection
    {
        return $this->pautasDaTurma($turmaId)
            ->groupBy(fn (Pauta $pauta): string => $pauta->componente?->nome ?? 'Geral (sem componente especifico)');
    }

    public function gruposPorComponenteDaTurma(int $turmaId): Collection
    {
        return $this->pautasDaTurma($turmaId)
            ->groupBy(fn (Pauta $pauta): string => (string) ($pauta->componente_curricular_id ?? 0))
            ->map(function (Collection $pautasDoComponente, string $componenteKey) use ($turmaId): array {
                $componenteId = (int) $componenteKey;
                $primeiraPauta = $pautasDoComponente->first();
                $componenteNome = $primeiraPauta?->componente?->nome ?? 'Geral (sem componente específico)';
                $professorNome = $this->nomeProfessorDoComponenteNaTurma($turmaId, $componenteId);

                return [
                    'componente_id' => $componenteId,
                    'componente_nome' => $componenteNome,
                    'professor_nome' => $professorNome,
                    'titulo' => $componenteNome.' - '.$professorNome,
                    'pautas' => $pautasDoComponente->values(),
                ];
            })
            ->sortBy(fn (array $grupo): string => mb_strtolower($grupo['titulo']))
            ->values();
    }

    public function getComponentesDisponiveisNoWorkspaceProperty(): array
    {
        if (is_array($this->componentesDisponiveisNoWorkspaceCache)) {
            return $this->componentesDisponiveisNoWorkspaceCache;
        }

        $opcoes = [];

        foreach ($this->pautasBaseDisponiveis as $pauta) {
            if ($pauta->componente_curricular_id === null) {
                $opcoes['0'] = 'Geral (sem componente específico)';

                continue;
            }

            $opcoes[(string) $pauta->componente_curricular_id] = (string) ($pauta->componente?->nome ?? 'Componente');
        }

        asort($opcoes);

        return $this->componentesDisponiveisNoWorkspaceCache = $opcoes;
    }

    public function respostaEstaBloqueada(int $pautaId, int $alunoId): bool
    {
        // Bloqueio apenas por pendência de transferência (aluno destino).
        $aluno = $this->alunosPorIdCache[$alunoId] ?? $this->alunoDaSerieSelecionada($alunoId);

        if (! $aluno instanceof Aluno) {
            return false;
        }

        $turmaId = $this->turmaIdPorAlunoIdCache[$alunoId]
            ?? (int) ($this->turmaAvaliativaDoAluno($aluno)?->id ?? 0);

        return $this->alunoEstaBloqueadoParaAvaliacao($aluno)
            || (($this->ciclosPorTurma[$turmaId]['status'] ?? null) === AvaliacaoTurmaCiclo::STATUS_CONCLUIDA);
    }

    public function informacaoComplementarEstaBloqueada(int $componenteId, int $alunoId): bool
    {
        return $this->respostaEstaBloqueada(0, $alunoId);
    }

    private function carregarDadosDoEscopo(): void
    {
        if (! $this->avaliacao || ! $this->serie) {
            return;
        }

        $this->limparCachesDoEscopo();
        $this->precarregarProfessoresPorTurmaComponente();
        $this->getPautasDisponiveisProperty();
        $this->getAlunosPorTurmaProperty();
        $this->carregarCiclosDoEscopo();
        $this->carregarRespostas();
        $this->carregarInformacoesComplementares();
        $this->limparCachesDeProgresso();
    }

    private function limparDadosDoEscopo(bool $limparTurmasExpandidas = false): void
    {
        $this->respostas = [];
        $this->respostaVersoes = [];
        $this->respostasPersistidas = [];
        $this->informacoesComplementares = [];
        $this->informacoesComplementaresPersistidas = [];
        $this->informacaoVersoes = [];
        $this->informacoesComplementaresBloqueadas = [];
        $this->alternativasPorPauta = [];
        $this->avaliacaoEmMassaGlobal = null;
        $this->alunoEmMassaGlobal = null;
        $this->componenteEmMassaGlobal = null;
        $this->pautasExpandidas = [];
        $this->alunosExpandidos = [];
        $this->componentesExpandidos = [];
        $this->ciclosPorTurma = [];

        if (! $this->modoAcompanhamento()) {
            $this->turmaEmMassaGlobal = null;
        }

        $this->limparCachesDoEscopo();

        if ($limparTurmasExpandidas) {
            $this->turmasExpandidas = [];
        }
    }

    private function deveFiltrarPorProfessor(): bool
    {
        if ($this->modoAcompanhamento()) {
            return false;
        }

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || $this->professorIds === []) {
            return false;
        }

        if ($user->hasPermissionLike('listar avaliacoes')) {
            return false;
        }

        return true;
    }

    private function deveRestringirAsTurmasDoProfessor(): bool
    {
        return ! $this->modoAcompanhamento() && $this->turmaIdsProfessor !== [];
    }

    private function abortSeNaoPuderResponder(): void
    {
        abort_unless($this->podeResponder(), 403);
    }

    private function abortSeNaoPuderPreencherEmMassa(): void
    {
        abort_unless($this->podePreencherEmMassa(), 403);
    }

    private function sincronizarVinculosProfessor(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $this->professorIds = $user->professores()
            ->where('ativo', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($this->professorIds === []) {
            return;
        }

        $vinculos = TurmaComponenteProfessor::query()
            ->whereIn('professor_id', $this->professorIds)
            ->where('tem_professor', true)
            ->get(['turma_id', 'componente_curricular_id']);

        $this->turmaIdsProfessor = $vinculos
            ->pluck('turma_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->componentesPorTurma = $vinculos
            ->groupBy('turma_id')
            ->map(fn (Collection $items): array => $items
                ->pluck('componente_curricular_id')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all())
            ->toArray();
    }

    private function filtrarTurmasDaAvaliacao(Avaliacao $avaliacao): Collection
    {
        return $avaliacao->turmas
            ->filter(function (Turma $turma) use ($avaliacao): bool {
                if (! $this->turmaEstaNoEscopo($turma)) {
                    return false;
                }

                return $this->filtrarPautasDaTurma($avaliacao->pautas, $turma, false)->isNotEmpty();
            })
            ->sortBy(fn (Turma $turma): string => mb_strtolower(implode('|', [
                (string) ($turma->serie?->nome ?? ''),
                (string) ($turma->escola?->nome ?? ''),
                (string) ($turma->nome ?? ''),
            ])))
            ->values();
    }

    private function turmaEstaNoEscopo(Turma $turma): bool
    {
        $escolasIds = $this->escolasPermitidasIds();

        if ($escolasIds !== null && ! in_array((int) $turma->id_escola, $escolasIds, true)) {
            return false;
        }

        if ($this->deveRestringirAsTurmasDoProfessor() && ! in_array((int) $turma->id, $this->turmaIdsProfessor, true)) {
            return false;
        }

        return true;
    }

    private function filtrarPautasDaTurma(Collection $pautas, Turma $turma, bool $aplicarFiltroComponenteLocal = true): Collection
    {
        return $pautas
            ->filter(function (Pauta $pauta) use ($turma, $aplicarFiltroComponenteLocal): bool {
                if (! $this->pautaEhDaSerieDaTurma($pauta, $turma)) {
                    return false;
                }

                if (! $this->pautaEhRelevanteParaTurma($pauta, $turma)) {
                    return false;
                }

                return ! $aplicarFiltroComponenteLocal || ! $this->modoAcompanhamento() || $this->componenteWorkspaceId === '';
            })
            ->values();
    }

    private function pautaEhDaSerieDaTurma(Pauta $pauta, Turma $turma): bool
    {
        return $pauta->serie_id === null || (int) $pauta->serie_id === (int) $turma->id_serie;
    }

    private function pautaEhRelevanteParaTurma(Pauta $pauta, Turma $turma): bool
    {
        if (! $this->deveFiltrarPorProfessor()) {
            return true;
        }

        $componentesProfessor = $this->componentesPorTurma[(int) $turma->id] ?? [];

        if ($componentesProfessor === []) {
            return false;
        }

        if ($pauta->componente_curricular_id === null) {
            return true;
        }

        return in_array((int) $pauta->componente_curricular_id, $componentesProfessor, true);
    }

    private function carregarAlternativasPorPauta(Collection $pautas): void
    {
        $this->alternativasPorPauta = [];
        $this->alternativasPorPautaPorIdCache = [];

        if (! $this->avaliacaoAtual || $pautas->isEmpty()) {
            return;
        }

        $pautasIds = $pautas->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
        $overrides = DB::table('avaliacao_pauta_alternativa')
            ->where('avaliacao_id', (int) $this->avaliacaoAtual->id)
            ->whereIn('pauta_id', $pautasIds)
            ->get(['pauta_id', 'alternativa_id'])
            ->groupBy('pauta_id')
            ->map(fn (Collection $rows): array => $rows
                ->pluck('alternativa_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all());

        $overrideIds = $overrides
            ->flatten(1)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $alternativasOverride = Alternativa::query()
            ->whereIn('id', $overrideIds)
            ->where('status', true)
            ->orderBy('nome')
            ->get()
            ->keyBy('id');

        $alternativasTipo = $this->avaliacaoAtual->tipo?->alternativas
            ? $this->avaliacaoAtual->tipo->alternativas->where('status', true)->values()
            : collect();

        foreach ($pautas as $pauta) {
            $alternativas = collect();
            $ids = $overrides->get((int) $pauta->id, []);

            if ($ids !== []) {
                $alternativas = collect($ids)
                    ->map(fn ($alternativaId) => $alternativasOverride->get((int) $alternativaId))
                    ->filter()
                    ->values();
            }

            if ($alternativas->isEmpty()) {
                $alternativas = $pauta->tipo?->alternativas
                    ? $pauta->tipo->alternativas->where('status', true)->values()
                    : collect();
            }

            if ($alternativas->isEmpty()) {
                $alternativas = $alternativasTipo;
            }

            if ($alternativas->isEmpty()) {
                $alternativas = $pauta->alternativas->where('status', true)->values();
            }

            $this->alternativasPorPauta[(int) $pauta->id] = $alternativas
                ->map(fn (Alternativa $alternativa): array => [
                    'id' => (int) $alternativa->id,
                    'nome' => (string) $alternativa->nome,
                    'tem_observacao' => (bool) $alternativa->tem_observacao,
                    'observacao' => (string) ($alternativa->observacao ?? ''),
                ])
                ->values()
                ->all();

            $this->alternativasPorPautaPorIdCache[(int) $pauta->id] = collect(
                $this->alternativasPorPauta[(int) $pauta->id]
            )->keyBy(fn (array $alternativa): int => (int) $alternativa['id'])->all();
        }
    }

    private function precarregarProfessoresPorTurmaComponente(): void
    {
        if ($this->professoresPrecarregados) {
            return;
        }

        $this->professoresPrecarregados = true;

        if ($this->professoresPorTurmaComponente !== []) {
            return;
        }

        $turmasIds = $this->turmasDaSerieDisponiveis
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $componentesIds = $this->pautasBaseDisponiveis
            ->pluck('componente_curricular_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->professoresPorTurmaComponente = [];

        if ($turmasIds === [] || $componentesIds === []) {
            return;
        }

        $this->professoresPorTurmaComponente = TurmaComponenteProfessor::query()
            ->whereIn('turma_id', $turmasIds)
            ->whereIn('componente_curricular_id', $componentesIds)
            ->get(['turma_id', 'componente_curricular_id', 'professor_id'])
            ->groupBy(fn (TurmaComponenteProfessor $vinculo): string => (int) $vinculo->turma_id.':'.(int) $vinculo->componente_curricular_id)
            ->map(fn (Collection $vinculos): array => $vinculos
                ->pluck('professor_id')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all())
            ->toArray();

        $professorIds = collect($this->professoresPorTurmaComponente)
            ->flatten()
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $this->nomesProfessoresPorId = $professorIds->isEmpty()
            ? []
            : Professor::query()->whereIn('id', $professorIds->all())->pluck('nome', 'id')->all();
    }

    private function carregarRespostas(): void
    {
        $pautas = $this->pautasDisponiveis;
        $alunos = $this->alunosDaSerie;

        if ($pautas->isEmpty() || $alunos->isEmpty()) {
            $this->respostas = [];
            $this->alternativasPorPauta = [];

            return;
        }

        $persistencia = app(AvaliacaoPersistencia::class);
        $service = app(AvaliacaoAlunoDocumentoService::class);
        $store = app(AvaliacaoRespostaStore::class);
        $turmaAvaliativaIds = $this->turmasDaSerieDisponiveis
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $documentos = $persistencia->leRelacional()
            ? collect()
            : $service->documentosDaAvaliacaoParaAlunos((int) $this->avaliacao, $alunos);
        if (! $persistencia->leRelacional()) {
            $this->documentosAvaliacaoCache = $documentos;
        }
        $respostasRelacionais = $persistencia->leRelacional()
            ? $store->respostasDaAvaliacaoParaAlunos(
                (int) $this->avaliacao,
                $alunos->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                $turmaAvaliativaIds,
            )
            : collect();

        $pendenciasPorAluno = $alunos
            ->where('status', Aluno::STATUS_PENDENTE)
            ->pluck('pendencia_origem_aluno_id', 'id')
            ->filter();

        $documentosOrigem = collect();
        $respostasOrigemRelacionais = collect();
        if ($pendenciasPorAluno->isNotEmpty()) {
            if ($persistencia->leRelacional()) {
                $respostasOrigemRelacionais = $store->respostasDaAvaliacaoParaAlunos(
                    (int) $this->avaliacao,
                    $pendenciasPorAluno->values()->map(fn ($id): int => (int) $id)->all(),
                    $turmaAvaliativaIds,
                );
            } else {
                $documentosOrigem = $service->documentosDaAvaliacaoParaAlunos(
                    (int) $this->avaliacao,
                    $pendenciasPorAluno->values()->all()
                );
            }
        }

        $nomesAlternativas = Alternativa::query()
            ->whereIn('id', $persistencia->leRelacional()
                ? $respostasOrigemRelacionais->flatten(1)->pluck('alternativa_id')->filter()->unique()->all()
                : $documentos->flatMap(fn (AvaliacaoAlunoDocumento $d) => $d->alternativa_ids ?? [])->unique()->all())
            ->pluck('nome', 'id');

        $respostas = [];
        $versoes = [];

        foreach ($this->turmasDaSerieDisponiveis as $turma) {
            $turmaId = (int) $turma->id;

            foreach ($this->pautasDaTurma($turmaId) as $pauta) {
                foreach ($this->alunosDaTurma($turmaId) as $aluno) {
                    $documento = $documentos->get((int) $aluno->id);
                    $respostaPayload = $persistencia->leRelacional()
                        ? $respostasRelacionais->get((int) $aluno->id)?->get((int) $pauta->id)
                        : $documento?->respostaDaPauta((int) $pauta->id);

                    $origemId = (int) ($pendenciasPorAluno->get((int) $aluno->id) ?? 0);
                    $referenciaOrigem = null;

                    if ($origemId > 0) {
                        $docOrigem = $documentosOrigem->get($origemId);
                        $respOrigem = $persistencia->leRelacional()
                            ? $respostasOrigemRelacionais->get($origemId)?->get((int) $pauta->id)
                            : $docOrigem?->respostaDaPauta((int) $pauta->id);
                        if ($respOrigem) {
                            $altId = (int) ($respOrigem['alternativa_id'] ?? 0);
                            $referenciaOrigem = [
                                'alternativa' => (string) ($nomesAlternativas[$altId] ?? ''),
                                'observacao' => (string) ($respOrigem['observacao'] ?? ''),
                            ];
                        }
                    }

                    $respostas[$pauta->id][$aluno->id] = [
                        'alternativa_id' => $respostaPayload['alternativa_id'] ?? null,
                        'observacao' => $respostaPayload['observacao'] ?? null,
                        'bloqueada' => $this->alunoEstaBloqueadoParaAvaliacao($aluno),
                        'origem_referencia' => $referenciaOrigem,
                    ];
                    $versoes[$pauta->id][$aluno->id] = (int) ($respostaPayload['version'] ?? 0);
                }
            }
        }

        $this->respostas = $respostas;
        $this->respostaVersoes = $versoes;
        $this->respostasPersistidas = collect($respostas)->map(fn (array $porAluno): array => collect($porAluno)->map(fn (array $item): array => [
            'alternativa_id' => $item['alternativa_id'] ?? null,
            'observacao' => $item['observacao'] ?? null,
        ])->all())->all();
    }

    private function carregarInformacoesComplementares(): void
    {
        $alunos = $this->alunosDaSerie;
        $alunosIds = $alunos
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($alunosIds === []) {
            $this->informacoesComplementares = [];
            $this->informacoesComplementaresPersistidas = [];
            $this->informacoesComplementaresBloqueadas = [];

            return;
        }

        $persistencia = app(AvaliacaoPersistencia::class);
        $documentos = $persistencia->leRelacional()
            ? collect()
            : ($this->documentosAvaliacaoCache
                ??= app(AvaliacaoAlunoDocumentoService::class)
                    ->documentosDaAvaliacaoParaAlunos((int) $this->avaliacao, $alunos));
        $informacoesRelacionais = $persistencia->leRelacional()
            ? app(AvaliacaoRespostaStore::class)->informacoesDaAvaliacaoParaAlunos(
                (int) $this->avaliacao,
                $alunosIds,
                $this->turmasDaSerieDisponiveis
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            )
            : collect();

        $informacoes = [];
        $bloqueadas = [];
        $versoes = [];

        foreach ($this->pautasDisponiveis as $pauta) {
            $componenteId = (int) ($pauta->componente_curricular_id ?? 0);

            foreach ($alunos as $aluno) {
                $alunoId = (int) $aluno->id;
                $info = $persistencia->leRelacional()
                    ? $informacoesRelacionais->get($alunoId)?->get($componenteId)
                    : $documentos->get($alunoId)?->informacaoDoComponente($componenteId);
                $informacoes[$componenteId][$alunoId] = (string) ($info['texto'] ?? '');
                $bloqueadas[$componenteId][$alunoId] = $this->alunoEstaBloqueadoParaAvaliacao($aluno);
                $versoes[$componenteId][$alunoId] = (int) ($info['version'] ?? 0);
            }
        }

        $this->informacoesComplementares = $informacoes;
        $this->informacoesComplementaresPersistidas = $informacoes;
        $this->informacoesComplementaresBloqueadas = $bloqueadas;
        $this->informacaoVersoes = $versoes;
    }

    private function carregarCiclosDoEscopo(): void
    {
        if (! $this->avaliacao) {
            $this->ciclosPorTurma = [];

            return;
        }

        $turmaIds = $this->turmasDaSerieDisponiveis
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->ciclosPorTurma = AvaliacaoTurmaCiclo::query()
            ->where('avaliacao_id', (int) $this->avaliacao)
            ->whereIn('turma_avaliativa_id', $turmaIds)
            ->get(['id', 'turma_avaliativa_id', 'status', 'versao_conclusao', 'snapshot_evento_atual_id'])
            ->mapWithKeys(fn (AvaliacaoTurmaCiclo $ciclo): array => [
                (int) $ciclo->turma_avaliativa_id => [
                    'id' => (int) $ciclo->id,
                    'status' => (string) $ciclo->status,
                    'versao_conclusao' => (int) $ciclo->versao_conclusao,
                    'snapshot_evento_atual_id' => $ciclo->snapshot_evento_atual_id,
                ],
            ])
            ->all();
    }

    private function enfileirarExportacaoTurma(int $turmaId): void
    {
        /** @var User $user */
        $user = Auth::user();

        app(ExportRequestService::class)->queue(
            user: $user,
            type: 'avaliacao_documento',
            format: 'pdf',
            filters: [
                'avaliacao_id' => (int) $this->avaliacao,
                'escopo' => 'turma',
                'turma_id' => $turmaId,
            ],
            label: 'Documento de avaliação da turma',
            metadata: ['origem' => 'workspace_avaliacao'],
        );
    }

    private function autoSalvarResposta(int $pautaId, int $alunoId, string $campoAlterado): void
    {
        $this->abortSeNaoPuderResponder();

        if (! $this->avaliacao || ! $this->serie || $this->respostaEstaBloqueada($pautaId, $alunoId)) {
            return;
        }

        $aluno = $this->alunoDaSerieSelecionada($alunoId);

        if (! $aluno || $this->alunoEstaBloqueadoParaAvaliacao($aluno)) {
            return;
        }

        $turmaId = (int) ($this->turmaAvaliativaDoAluno($aluno)?->id ?? $aluno->id_turma);
        $pauta = $this->pautasDaTurma($turmaId)->firstWhere('id', $pautaId);

        if (! $pauta) {
            return;
        }

        $alternativaId = (int) ($this->respostas[$pautaId][$alunoId]['alternativa_id'] ?? 0);
        $alternativa = $this->alternativaDaPauta($pautaId, $alternativaId);

        if (! $alternativa) {
            $this->removerRespostaPersistida($pautaId, $alunoId);

            return;
        }

        $observacaoInformada = $this->limitarTextoCampo($this->respostas[$pautaId][$alunoId]['observacao'] ?? '');
        $temObservacao = (bool) ($alternativa['tem_observacao'] ?? false);

        if (! $temObservacao) {
            $this->respostas[$pautaId][$alunoId]['observacao'] = null;
            $observacaoInformada = '';
        }

        if ($temObservacao && $observacaoInformada === '') {
            $this->removerRespostaPersistida($pautaId, $alunoId);

            return;
        }

        try {
            $dados = [
                'alternativa_id' => $alternativaId,
                'observacao' => $temObservacao ? $observacaoInformada : null,
                'professor_id' => $this->professorIdParaRegistro($turmaId, $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null),
                'componente_curricular_id' => $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null,
                'respondido_em' => now(),
            ];

            $expectedValues = collect(['alternativa_id', 'observacao'])
                ->filter(fn (string $campo): bool => array_key_exists($campo, $dados))
                ->mapWithKeys(fn (string $campo): array => [
                    $campo => $this->respostasPersistidas[$pautaId][$alunoId][$campo] ?? null,
                ])->all();

            $this->respostaVersoes[$pautaId][$alunoId] = app(AvaliacaoRespostaStore::class)->salvarPauta(
                (int) $this->avaliacao,
                $turmaId,
                $aluno,
                $pautaId,
                $dados,
                ($this->respostaVersoes[$pautaId][$alunoId] ?? 0) > 0
                    ? (int) $this->respostaVersoes[$pautaId][$alunoId]
                    : null,
                $expectedValues,
            );
            $this->respostasPersistidas[$pautaId][$alunoId] = [
                'alternativa_id' => $alternativaId,
                'observacao' => $temObservacao ? $observacaoInformada : null,
            ];
        } catch (AvaliacaoRespostaConcorrenteException $exception) {
            $this->carregarRespostas();
            Notification::make()->title($exception->getMessage())->warning()->send();

            return;
        }

        $this->limparCachesDeProgresso();
        $this->emitirAtualizacaoDoWorkspaceAcompanhamento();
    }

    private function autoSalvarInformacaoComplementar(int $componenteId, int $alunoId): void
    {
        $this->abortSeNaoPuderResponder();

        if (! $this->avaliacao || ! $this->serie || $this->informacaoComplementarEstaBloqueada($componenteId, $alunoId)) {
            return;
        }

        $aluno = $this->alunoDaSerieSelecionada($alunoId);

        if (! $aluno || $this->alunoEstaBloqueadoParaAvaliacao($aluno)) {
            return;
        }

        $turmaId = (int) ($this->turmaAvaliativaDoAluno($aluno)?->id ?? $aluno->id_turma);
        $informacoes = $this->limitarTextoCampo($this->informacoesComplementares[$componenteId][$alunoId] ?? '');
        try {
            $this->informacaoVersoes[$componenteId][$alunoId] = app(AvaliacaoRespostaStore::class)->salvarInformacao(
                (int) $this->avaliacao,
                $turmaId,
                $aluno,
            $componenteId,
            $informacoes !== '' ? $informacoes : null,
            $this->professorIdParaRegistro($turmaId, $componenteId > 0 ? $componenteId : null),
                ($this->informacaoVersoes[$componenteId][$alunoId] ?? 0) > 0
                    ? (int) $this->informacaoVersoes[$componenteId][$alunoId]
                    : null,
            );
        } catch (AvaliacaoRespostaConcorrenteException $exception) {
            $this->carregarInformacoesComplementares();
            Notification::make()->title($exception->getMessage())->warning()->send();

            return;
        }

        $this->limparCachesDeProgresso();
        $this->emitirAtualizacaoDoWorkspaceAcompanhamento();
    }

    private function removerRespostaPersistida(int $pautaId, int $alunoId): void
    {
        $this->abortSeNaoPuderResponder();

        if (! $this->avaliacao || ! $this->serie || $this->respostaEstaBloqueada($pautaId, $alunoId)) {
            return;
        }

        $aluno = $this->alunoDaSerieSelecionada($alunoId);

        if (! $aluno || $this->alunoEstaBloqueadoParaAvaliacao($aluno)) {
            return;
        }

        $turmaId = (int) ($this->turmaAvaliativaDoAluno($aluno)?->id ?? $aluno->id_turma);

        try {
            app(AvaliacaoRespostaStore::class)->removerPauta(
                (int) $this->avaliacao,
                $turmaId,
                $aluno,
                $pautaId,
                ($this->respostaVersoes[$pautaId][$alunoId] ?? 0) > 0
                    ? (int) $this->respostaVersoes[$pautaId][$alunoId]
                    : null,
            );
            $this->respostaVersoes[$pautaId][$alunoId] = 0;
            $this->respostasPersistidas[$pautaId][$alunoId] = ['alternativa_id' => null, 'observacao' => null];
        } catch (AvaliacaoRespostaConcorrenteException $exception) {
            $this->carregarRespostas();
            Notification::make()->title($exception->getMessage())->warning()->send();

            return;
        }

        $this->limparCachesDeProgresso();
        $this->emitirAtualizacaoDoWorkspaceAcompanhamento();
    }

    private function respostaEstaCompleta(Pauta $pauta, int $alunoId): bool
    {
        $alternativaId = (int) ($this->respostas[$pauta->id][$alunoId]['alternativa_id'] ?? 0);

        if ($alternativaId <= 0) {
            return false;
        }

        $alternativa = $this->alternativaDaPauta((int) $pauta->id, $alternativaId);

        if (! $alternativa) {
            return false;
        }

        if (! ((bool) ($alternativa['tem_observacao'] ?? false))) {
            return true;
        }

        return trim((string) ($this->respostas[$pauta->id][$alunoId]['observacao'] ?? '')) !== '';
    }

    private function respostaTemObservacao(int $pautaId, int $alunoId): bool
    {
        return trim((string) ($this->respostas[$pautaId][$alunoId]['observacao'] ?? '')) !== '';
    }

    private function alunosRespondiveisDaTurma(int $turmaId): Collection
    {
        return $this->alunosDaTurma($turmaId)
            ->reject(fn (Aluno $aluno): bool => $this->alunoEstaBloqueadoParaAvaliacao($aluno))
            ->values();
    }

    private function professorIdParaRegistro(int $turmaId, ?int $componenteId): ?int
    {
        if (! $componenteId) {
            return null;
        }

        $this->precarregarProfessoresPorTurmaComponente();

        $cacheKey = $turmaId.':'.$componenteId;

        if (! array_key_exists($cacheKey, $this->professoresPorTurmaComponente)) {
            $this->professoresPorTurmaComponente[$cacheKey] = TurmaComponenteProfessor::query()
                ->where('turma_id', $turmaId)
                ->where('componente_curricular_id', $componenteId)
                ->pluck('professor_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $professoresIds = $this->professoresPorTurmaComponente[$cacheKey];

        if ($this->deveFiltrarPorProfessor() && $this->professorIds !== []) {
            $professoresIds = array_values(array_intersect($professoresIds, $this->professorIds));
        }

        return $professoresIds[0] ?? null;
    }

    private function nomeProfessorDoComponenteNaTurma(int $turmaId, int $componenteId): string
    {
        if ($componenteId <= 0) {
            return 'Sem Professor';
        }

        $professorId = $this->professorIdParaRegistro($turmaId, $componenteId);

        if (! $professorId) {
            return 'Sem Professor';
        }

        $this->precarregarProfessoresPorTurmaComponente();

        if (! array_key_exists($professorId, $this->nomesProfessoresPorId)) {
            $this->nomesProfessoresPorId = Professor::query()
                ->whereIn('id', collect($this->professoresPorTurmaComponente)
                    ->flatten()
                    ->map(fn ($id): int => (int) $id)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all())
                ->pluck('nome', 'id')
                ->all();
        }

        $nome = (string) ($this->nomesProfessoresPorId[$professorId] ?? '');

        return $nome !== '' ? $nome : 'Sem Professor';
    }

    private function turmasAlvoAvaliacaoEmMassa(): Collection
    {
        $turmaId = (int) ($this->turmaEmMassaGlobal ?? 0);

        if ($turmaId <= 0 || $this->modoAcompanhamento()) {
            return $this->turmasDaSerieDisponiveis;
        }

        return $this->turmasDaSerieDisponiveis
            ->filter(fn (Turma $turma): bool => (int) $turma->id === $turmaId)
            ->values();
    }

    private function alunosAlvoAvaliacaoEmMassa(int $turmaId): Collection
    {
        $alunos = $this->alunosDaTurma($turmaId);
        $alunoId = (int) ($this->alunoEmMassaGlobal ?? 0);

        if ($alunoId <= 0) {
            return $alunos;
        }

        return $alunos
            ->filter(fn (Aluno $aluno): bool => (int) $aluno->id === $alunoId)
            ->values();
    }

    private function pautasAlvoAvaliacaoEmMassa(int $turmaId): Collection
    {
        $pautas = $this->pautasDaTurma($turmaId);

        if ($this->componenteEmMassaGlobal === null) {
            return $pautas;
        }

        $componenteId = (int) $this->componenteEmMassaGlobal;

        return $pautas
            ->filter(fn (Pauta $pauta): bool => (int) ($pauta->componente_curricular_id ?? 0) === $componenteId)
            ->values();
    }

    private function alternativaDaPauta(int $pautaId, ?int $alternativaId): ?array
    {
        if (! $alternativaId) {
            return null;
        }

        if (array_key_exists($pautaId, $this->alternativasPorPautaPorIdCache)) {
            return $this->alternativasPorPautaPorIdCache[$pautaId][$alternativaId] ?? null;
        }

        return collect($this->alternativasDaPauta($pautaId))
            ->first(fn (array $alternativa): bool => (int) ($alternativa['id'] ?? 0) === (int) $alternativaId);
    }

    private function escolaDaTurma(int $turmaId): ?int
    {
        $turma = $this->turmasDisponiveis->firstWhere('id', $turmaId);

        return $turma?->id_escola ? (int) $turma->id_escola : null;
    }

    private function escolaDaSerie(int $serieId): ?int
    {
        $escolasIds = $this->turmasDisponiveis
            ->filter(fn (Turma $turma): bool => (int) $turma->id_serie === $serieId)
            ->pluck('id_escola')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        return $escolasIds->isNotEmpty() ? (int) $escolasIds->first() : null;
    }

    private function serieDaTurma(int $turmaId): ?int
    {
        $turma = $this->turmasDisponiveis->firstWhere('id', $turmaId);

        return $turma?->id_serie ? (int) $turma->id_serie : null;
    }

    private function aplicarSerieEscolaSelecionada(): void
    {
        if (! $this->serieEscola) {
            $this->serie = null;
            $this->escola = null;

            return;
        }

        $partes = explode(':', $this->serieEscola);

        if (count($partes) !== 2 || ! is_numeric($partes[0]) || ! is_numeric($partes[1])) {
            $this->serie = null;
            $this->escola = null;
            $this->serieEscola = null;

            return;
        }

        $this->escola = (int) $partes[0];
        $this->serie = (int) $partes[1];
    }

    private function sincronizarSerieEscola(): void
    {
        $this->serieEscola = $this->serie && $this->escola
            ? $this->chaveSerieEscola((int) $this->escola, (int) $this->serie)
            : null;
    }

    private function escopoSerieSelecionadoEhValido(): bool
    {
        if (! $this->serie) {
            return false;
        }

        return $this->seriesPorEscolaDisponiveis
            ->contains(fn (array $escopo): bool => (int) $escopo['serie_id'] === (int) $this->serie
                && (int) $escopo['escola_id'] === (int) $this->escola);
    }

    private function alunoDaSerieSelecionada(int $alunoId): ?Aluno
    {
        if (! array_key_exists($alunoId, $this->alunosPorIdCache)) {
            $this->carregarIndicesDeAlunos();
        }

        return $this->alunosPorIdCache[$alunoId] ?? null;
    }

    private function montarResumoProgresso(int $preenchidas, int $total): array
    {
        $percentual = $total > 0 ? min(100, (int) round(($preenchidas / $total) * 100)) : 0;

        return [
            'preenchidas' => $preenchidas,
            'total' => $total,
            'percentual' => $percentual,
            'concluida' => $total > 0 && $preenchidas === $total,
        ];
    }

    private function chaveExpansao(int $turmaId, int $itemId): string
    {
        return $turmaId.':'.$itemId;
    }

    private function chaveSerieEscola(int $escolaId, int $serieId): string
    {
        return $escolaId.':'.$serieId;
    }

    private function normalizarQueryId(mixed $valor): ?int
    {
        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }

    private function limparCachesDoEscopo(): void
    {
        $this->avaliacoesDisponiveisCache = null;
        $this->avaliacaoAtualCache = null;
        $this->avaliacaoAtualCacheCarregado = false;
        $this->turmasDisponiveisCache = null;
        $this->pautasBaseDisponiveisCache = null;
        $this->pautasDisponiveisCache = null;
        $this->alunosPorTurmaCache = null;
        $this->alunosDaSerieCache = null;
        $this->documentosAvaliacaoCache = null;
        $this->pautasPorTurmaCache = [];
        $this->turmasDaSeriePorIdCache = [];
        $this->alunosPorIdCache = [];
        $this->turmaIdPorAlunoIdCache = [];
        $this->alternativasPorPautaPorIdCache = [];
        $this->professoresPrecarregados = false;
        $this->componentesDisponiveisNoWorkspaceCache = null;
        $this->escolasPermitidasIdsCacheCarregado = false;
        $this->escolasPermitidasIdsCache = null;
        $this->professoresPorTurmaComponente = [];
        $this->nomesProfessoresPorId = [];
        $this->limparCachesDeProgresso();
    }

    private function limparCachesDeProgresso(): void
    {
        $this->progressoConsolidadoCache = null;
    }

    private function limitarTextoCampo(mixed $valor): string
    {
        return mb_substr(trim((string) $valor), 0, self::LIMITE_CARACTERES_TEXTO);
    }

    private function emitirAtualizacaoDoWorkspaceAcompanhamento(): void
    {
        if (! $this->modoAcompanhamento()) {
            return;
        }

        $this->dispatch('workspace-acompanhamento-alterado');
    }

    private function aplicarEscopoEscolasPermitidas($query): void
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

    private function escolasPermitidasIds(): ?array
    {
        if ($this->escolasPermitidasIdsCacheCarregado) {
            return $this->escolasPermitidasIdsCache;
        }

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            $this->escolasPermitidasIdsCacheCarregado = true;

            return $this->escolasPermitidasIdsCache = [];
        }

        $scope = app(PessoaScopeService::class);

        if ($scope->hasGlobalAccess($user)) {
            $this->escolasPermitidasIdsCacheCarregado = true;

            return $this->escolasPermitidasIdsCache = null;
        }

        $escolasIds = $scope->escolaIdsDosVinculos($user);

        $this->escolasPermitidasIdsCacheCarregado = true;

        return $this->escolasPermitidasIdsCache = $escolasIds === [] ? [] : $escolasIds;
    }
}
