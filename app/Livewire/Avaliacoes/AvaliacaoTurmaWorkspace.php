<?php

namespace App\Livewire\Avaliacoes;

use App\Models\Alternativa;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\AvaliacaoInformacaoComplementar;
use App\Models\AvaliacaoResposta;
use App\Models\Pauta;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public array $informacoesComplementares = [];

    public array $informacoesComplementaresBloqueadas = [];

    public array $alternativasPorPauta = [];

    public ?int $avaliacaoEmMassaGlobal = null;

    public ?int $turmaEmMassaGlobal = null;

    public array $pautasExpandidas = [];

    public array $alunosExpandidos = [];

    public array $turmasExpandidas = [];

    public string $visualizacao = 'pautas';

    public array $professorIds = [];

    public array $componentesPorTurma = [];

    public array $professoresPorTurmaComponente = [];

    public string $componenteWorkspaceId = '';

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

        $this->sincronizarVinculosProfessor();

        if ($this->modoAcompanhamento()) {
            $this->avaliacao = $avaliacaoId;
            $this->turma = $turmaId;
            $this->escola = $escolaId;
            $this->serie = $serieId;
            $this->turmaEmMassaGlobal = $turmaId;
            $this->serieEscola = $this->escola && $this->serie
                ? $this->chaveSerieEscola($this->escola, $this->serie)
                : null;
            $this->componenteWorkspaceId = $initialComponenteId !== null ? (string) $initialComponenteId : '0';
            $this->turmasExpandidas = $turmaId ? [$turmaId] : [];
        } else {
            $avaliacaoQuery = $this->normalizarQueryId(request()->query('avaliacao'));
            $serieQuery = $this->normalizarQueryId(request()->query('serie'));
            $escolaQuery = $this->normalizarQueryId(request()->query('escola'));
            $turmaQuery = $this->normalizarQueryId(request()->query('turma'));

            $this->avaliacao = $avaliacaoId ?: $avaliacaoQuery;

            if ($serieId) {
                $this->serie = $serieId;
                $this->escola = $escolaId ?: $this->escolaDaSerie($serieId);
                $this->sincronizarSerieEscola();
            } elseif ($serieQuery) {
                $this->serie = $serieQuery;
                $this->escola = $escolaQuery ?: $this->escolaDaSerie($serieQuery);
                $this->sincronizarSerieEscola();
            } elseif ($turmaId) {
                $this->turma = $turmaId;
                $this->serie = $this->serieDaTurma($turmaId);
                $this->escola = $escolaId ?: $this->escolaDaTurma($turmaId);
                $this->sincronizarSerieEscola();
            } elseif ($turmaQuery) {
                $this->turma = $turmaQuery;
                $this->serie = $this->serieDaTurma($turmaQuery);
                $this->escola = $this->escolaDaTurma($turmaQuery);
                $this->sincronizarSerieEscola();
            }
        }

        if ($this->avaliacao && $this->serie) {
            $this->carregarDadosDoEscopo();
        }
    }

    public function render()
    {
        return view('livewire.avaliacoes.avaliacao-turma-workspace');
    }

    public function modoAcompanhamento(): bool
    {
        return $this->modo === 'acompanhamento';
    }

    public function podeResponder(): bool
    {
        return $this->canEdit;
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

    public function updatedComponenteWorkspaceId(): void
    {
        if (! $this->modoAcompanhamento()) {
            return;
        }

        $this->limparDadosDoEscopo();
        $this->turmasExpandidas = $this->turma ? [$this->turma] : [];
        $this->carregarDadosDoEscopo();
    }

    public function definirVisualizacao(string $visualizacao): void
    {
        if (! in_array($visualizacao, ['pautas', 'alunos'], true)) {
            return;
        }

        $this->visualizacao = $visualizacao;
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

    public function alternarPauta(int $turmaId, int $pautaId): void
    {
        $chave = $this->chaveExpansao($turmaId, $pautaId);
        $indice = array_search($chave, $this->pautasExpandidas, true);

        if ($indice !== false) {
            unset($this->pautasExpandidas[$indice]);
            $this->pautasExpandidas = array_values($this->pautasExpandidas);

            return;
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

        $this->alunosExpandidos[] = $chave;
    }

    public function alunoEstaExpandido(int $turmaId, int $alunoId): bool
    {
        return in_array($this->chaveExpansao($turmaId, $alunoId), $this->alunosExpandidos, true);
    }

    public function updated(string $name): void
    {
        if (str_starts_with($name, 'informacoesComplementares.')) {
            [$prefixo, $componenteId, $alunoId] = array_pad(explode('.', $name), 3, null);

            if ($prefixo === 'informacoesComplementares' && is_numeric($componenteId) && is_numeric($alunoId)) {
                if (
                    $this->alunoEstaBloqueadoParaAvaliacao((int) $alunoId)
                    || $this->informacaoComplementarEstaBloqueada((int) $componenteId, (int) $alunoId)
                ) {
                    return;
                }

                $this->autoSalvarInformacaoComplementar((int) $componenteId, (int) $alunoId);
            }

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

        if (! is_numeric($pautaId) || ! is_numeric($alunoId)) {
            return;
        }

        if (! in_array($campo, ['alternativa_id', 'observacao'], true)) {
            return;
        }

        if (
            $this->alunoEstaBloqueadoParaAvaliacao((int) $alunoId)
            || $this->respostaEstaBloqueada((int) $pautaId, (int) $alunoId)
        ) {
            return;
        }

        $this->autoSalvarResposta((int) $pautaId, (int) $alunoId);
    }

    public function aplicarEmMassaNaSerie(): void
    {
        $this->abortSeNaoPuderResponder();

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

        foreach ($turmasAlvo as $turma) {
            $turmaId = (int) $turma->id;

            foreach ($this->pautasDaTurma($turmaId) as $pauta) {
                $alternativa = $this->alternativaDaPauta((int) $pauta->id, $alternativaId);

                if (! $alternativa) {
                    $pautasIgnoradas++;

                    continue;
                }

                foreach ($this->alunosDaTurma($turmaId) as $aluno) {
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
                        $pendencias[$turmaId . ':' . $pauta->id][] = $alunoId;

                        continue;
                    }

                    $payload[] = [
                        'avaliacao_id' => (int) $this->avaliacao,
                        'pauta_id' => (int) $pauta->id,
                        'turma_id' => $turmaId,
                        'aluno_id' => $alunoId,
                        'professor_id' => $this->professorIdParaRegistro($turmaId, $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null),
                        'alternativa_id' => $alternativaId,
                        'observacao' => $temObservacao ? $observacaoInformada : null,
                        'respondido_em' => $agora,
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ];
                }
            }
        }

        if ($payload === [] && $pendencias === []) {
            $mensagem = $alunosPendentesTransferenciaIgnorados !== []
                ? 'Alunos pendentes de transferência foram mantidos bloqueados.'
                : 'Respostas bloqueadas ou já preenchidas foram mantidas.';

            Notification::make()
                ->title('Nenhuma resposta alterada.')
                ->body($mensagem)
                ->warning()
                ->send();

            return;
        }

        DB::transaction(function () use ($payload, $pendencias): void {
            if ($payload !== []) {
                AvaliacaoResposta::query()->upsert(
                    $payload,
                    ['avaliacao_id', 'pauta_id', 'turma_id', 'aluno_id'],
                    ['professor_id', 'alternativa_id', 'observacao', 'respondido_em', 'updated_at']
                );
            }

            foreach ($pendencias as $chave => $alunosIds) {
                [$turmaId, $pautaId] = array_map('intval', explode(':', $chave));

                AvaliacaoResposta::query()
                    ->where('avaliacao_id', (int) $this->avaliacao)
                    ->where('turma_id', $turmaId)
                    ->where('pauta_id', $pautaId)
                    ->whereIn('aluno_id', $alunosIds)
                    ->delete();
            }
        });

        $mensagens = [];

        if ($pautasIgnoradas > 0) {
            $mensagens[] = $pautasIgnoradas . ' pauta(s) não possuem esta alternativa.';
        }

        if ($alunosPendentesTransferenciaIgnorados !== []) {
            $mensagens[] = count($alunosPendentesTransferenciaIgnorados) . ' aluno(s) pendente(s) de transferência foram ignorados.';
        }

        if ($totalIgnoradoPorPreenchimento > 0) {
            $mensagens[] = $totalIgnoradoPorPreenchimento . ' resposta(s) já preenchida(s) ou bloqueada(s) foram mantidas.';
        }

        Notification::make()
            ->title('Avaliação em massa aplicada.')
            ->body(implode(' ', $mensagens))
            ->success()
            ->send();

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
        $payload = [];
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

                    $payload[] = [
                        'avaliacao_id' => (int) $this->avaliacao,
                        'pauta_id' => (int) $pauta->id,
                        'turma_id' => $turmaId,
                        'aluno_id' => (int) $aluno->id,
                        'professor_id' => $this->professorIdParaRegistro($turmaId, $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null),
                        'alternativa_id' => $alternativaId,
                        'observacao' => $temObservacao ? $observacaoInformada : null,
                        'respondido_em' => $agora,
                        'created_at' => $agora,
                        'updated_at' => $agora,
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

        if ($payload !== []) {
            AvaliacaoResposta::query()->upsert(
                $payload,
                ['avaliacao_id', 'pauta_id', 'turma_id', 'aluno_id'],
                ['professor_id', 'alternativa_id', 'observacao', 'respondido_em', 'updated_at']
            );
        }

        Notification::make()
            ->title('Avaliação salva com sucesso.')
            ->success()
            ->send();

        $this->emitirAtualizacaoDoWorkspaceAcompanhamento();
    }

    public function alternativaRequerObservacao(int $pautaId, ?int $alternativaId): bool
    {
        $alternativa = $this->alternativaDaPauta($pautaId, $alternativaId);

        return (bool) ($alternativa['tem_observacao'] ?? false);
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
        $query = Avaliacao::query()
            ->pendentesParaData(now());

        if ($this->modoAcompanhamento()) {
            $query->whereHas('turmas', function ($turmas): void {
                $this->aplicarEscopoEscolasPermitidas($turmas);
            });
        }

        if ($this->deveFiltrarPorProfessor()) {
            $query->whereHas('turmas.componentes', function ($componentes): void {
                $componentes->whereIn('turma_componente_professor.professor_id', $this->professorIds);
            });
        }

        $avaliacoes = $query
            ->with([
                'tipo' => fn ($tipo) => $tipo->with([
                    'alternativas' => fn ($alternativas) => $alternativas->where('status', true),
                ]),
                'pautas' => fn ($pautas) => $pautas
                    ->where('status', true)
                    ->with([
                        'componente:id,nome',
                        'tipo' => fn ($tipo) => $tipo->with([
                            'alternativas' => fn ($alternativas) => $alternativas->where('status', true),
                        ]),
                        'alternativas' => fn ($alternativas) => $alternativas->where('status', true),
                    ]),
                'turmas' => fn ($turmas) => $turmas->with(['escola:id,nome', 'serie:id,nome']),
            ])
            ->orderBy('data_inicio')
            ->get();

        return $avaliacoes
            ->filter(fn (Avaliacao $avaliacao): bool => $this->filtrarTurmasDaAvaliacao($avaliacao)->isNotEmpty())
            ->values();
    }

    public function getAvaliacaoAtualProperty(): ?Avaliacao
    {
        return $this->avaliacoesDisponiveis->firstWhere('id', (int) $this->avaliacao);
    }

    public function getTurmasDisponiveisProperty(): Collection
    {
        if (! $this->avaliacaoAtual) {
            return collect();
        }

        $turmas = $this->filtrarTurmasDaAvaliacao($this->avaliacaoAtual);

        if ($this->modoAcompanhamento() && $this->turma) {
            return $turmas
                ->filter(fn (Turma $turma): bool => (int) $turma->id === (int) $this->turma)
                ->values();
        }

        return $turmas;
    }

    public function getPautasBaseDisponiveisProperty(): Collection
    {
        if (! $this->avaliacaoAtual || ! $this->serie) {
            return collect();
        }

        $pautas = collect();

        foreach ($this->turmasDaSerieDisponiveis as $turma) {
            $pautas = $pautas->merge($this->filtrarPautasDaTurma($this->avaliacaoAtual->pautas, $turma, false));
        }

        return $pautas->unique('id')->values();
    }

    public function getPautasDisponiveisProperty(): Collection
    {
        $pautas = $this->pautasBaseDisponiveis;

        if ($pautas->isEmpty()) {
            return collect();
        }

        $pautas = $pautas->filter(function (Pauta $pauta): bool {
            if (! $this->modoAcompanhamento()) {
                return true;
            }

            if ($this->componenteWorkspaceId === '') {
                return true;
            }

            if ($this->componenteWorkspaceId === '0') {
                return $pauta->componente_curricular_id === null;
            }

            return (int) ($pauta->componente_curricular_id ?? 0) === (int) $this->componenteWorkspaceId;
        })->values();

        $this->carregarAlternativasPorPauta($pautas);

        return $pautas
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
            ->sortBy(fn (array $escopo): string => mb_strtolower($escopo['escola_nome'] . '|' . $escopo['serie_nome']))
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
        $turmasIds = $this->turmasDaSerieDisponiveis
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($turmasIds === []) {
            return collect();
        }

        return Aluno::query()
            ->whereIn('id_turma', $turmasIds)
            ->whereIn('status', [Aluno::STATUS_MATRICULADO, Aluno::STATUS_PENDENTE])
            ->orderBy('nome')
            ->get(['id', 'nome', 'cgm', 'id_turma', 'status', 'pendencia_origem_aluno_id'])
            ->groupBy('id_turma')
            ->map(fn (Collection $alunos): Collection => $alunos->values());
    }

    public function getAlunosDaSerieProperty(): Collection
    {
        return $this->alunosPorTurma
            ->flatMap(fn (Collection $alunos): Collection => $alunos)
            ->values();
    }

    public function alunosDaTurma(int $turmaId): Collection
    {
        return $this->alunosPorTurma->get($turmaId, collect());
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
        $turma = $this->turmasDaSerieDisponiveis->firstWhere('id', $turmaId);

        if (! $turma || ! $this->avaliacaoAtual) {
            return collect();
        }

        return $this->pautasDisponiveis
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
        $progresso = [];

        foreach ($this->turmasDaSerieDisponiveis as $turma) {
            $turmaId = (int) $turma->id;
            $pautas = $this->pautasDaTurma($turmaId);
            $alunos = $this->alunosRespondiveisDaTurma($turmaId);
            $total = $pautas->count() * $alunos->count();
            $preenchidas = 0;

            foreach ($pautas as $pauta) {
                foreach ($alunos as $aluno) {
                    if ($this->respostaEstaCompleta($pauta, (int) $aluno->id)) {
                        $preenchidas++;
                    }
                }
            }

            $progresso[$turmaId] = $this->montarResumoProgresso($preenchidas, $total);
        }

        return $progresso;
    }

    public function getProgressoPorPautaProperty(): array
    {
        $progresso = [];

        foreach ($this->turmasDaSerieDisponiveis as $turma) {
            $turmaId = (int) $turma->id;
            $alunos = $this->alunosRespondiveisDaTurma($turmaId);

            foreach ($this->pautasDaTurma($turmaId) as $pauta) {
                $total = $alunos->count();
                $preenchidas = 0;

                foreach ($alunos as $aluno) {
                    if ($this->respostaEstaCompleta($pauta, (int) $aluno->id)) {
                        $preenchidas++;
                    }
                }

                $progresso[$turmaId][$pauta->id] = $this->montarResumoProgresso($preenchidas, $total);
            }
        }

        return $progresso;
    }

    public function getProgressoPorAlunoProperty(): array
    {
        $progresso = [];

        foreach ($this->turmasDaSerieDisponiveis as $turma) {
            $turmaId = (int) $turma->id;
            $pautas = $this->pautasDaTurma($turmaId);

            foreach ($this->alunosDaTurma($turmaId) as $aluno) {
                if ($this->alunoEstaBloqueadoParaAvaliacao($aluno)) {
                    $progresso[$aluno->id] = $this->montarResumoProgresso(0, 0);

                    continue;
                }

                $total = $pautas->count();
                $preenchidas = 0;

                foreach ($pautas as $pauta) {
                    if ($this->respostaEstaCompleta($pauta, (int) $aluno->id)) {
                        $preenchidas++;
                    }
                }

                $progresso[$aluno->id] = $this->montarResumoProgresso($preenchidas, $total);
            }
        }

        return $progresso;
    }

    public function pautasAgrupadasPorComponenteDaTurma(int $turmaId): Collection
    {
        return $this->pautasDaTurma($turmaId)
            ->groupBy(fn (Pauta $pauta): string => $pauta->componente?->nome ?? 'Geral (sem componente especifico)');
    }

    public function getComponentesDisponiveisNoWorkspaceProperty(): array
    {
        $opcoes = [];

        foreach ($this->pautasBaseDisponiveis as $pauta) {
            if ($pauta->componente_curricular_id === null) {
                $opcoes['0'] = 'Geral (sem componente específico)';

                continue;
            }

            $opcoes[(string) $pauta->componente_curricular_id] = (string) ($pauta->componente?->nome ?? 'Componente');
        }

        asort($opcoes);

        return $opcoes;
    }

    public function respostaEstaBloqueada(int $pautaId, int $alunoId): bool
    {
        return (bool) ($this->respostas[$pautaId][$alunoId]['bloqueada'] ?? false);
    }

    public function informacaoComplementarEstaBloqueada(int $componenteId, int $alunoId): bool
    {
        return (bool) ($this->informacoesComplementaresBloqueadas[$componenteId][$alunoId] ?? false);
    }

    private function carregarDadosDoEscopo(): void
    {
        if (! $this->avaliacao || ! $this->serie) {
            return;
        }

        $this->carregarRespostas();
        $this->carregarInformacoesComplementares();
    }

    private function limparDadosDoEscopo(bool $limparTurmasExpandidas = false): void
    {
        $this->respostas = [];
        $this->informacoesComplementares = [];
        $this->informacoesComplementaresBloqueadas = [];
        $this->alternativasPorPauta = [];
        $this->avaliacaoEmMassaGlobal = null;
        $this->pautasExpandidas = [];
        $this->alunosExpandidos = [];

        if (! $this->modoAcompanhamento()) {
            $this->turmaEmMassaGlobal = null;
        }

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

    private function abortSeNaoPuderResponder(): void
    {
        abort_unless($this->podeResponder(), 403);
    }

    private function sincronizarVinculosProfessor(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $this->professorIds = $user->professores()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($this->professorIds === []) {
            return;
        }

        $vinculos = TurmaComponenteProfessor::query()
            ->whereIn('professor_id', $this->professorIds)
            ->get(['turma_id', 'componente_curricular_id']);

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
        if ($this->modoAcompanhamento()) {
            $escolasIds = $this->escolasPermitidasIds();

            if ($escolasIds !== null && ! in_array((int) $turma->id_escola, $escolasIds, true)) {
                return false;
            }
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

                if (! $aplicarFiltroComponenteLocal || ! $this->modoAcompanhamento()) {
                    return true;
                }

                if ($this->componenteWorkspaceId === '') {
                    return true;
                }

                if ($this->componenteWorkspaceId === '0') {
                    return $pauta->componente_curricular_id === null;
                }

                return (int) ($pauta->componente_curricular_id ?? 0) === (int) $this->componenteWorkspaceId;
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
                ])
                ->values()
                ->all();
        }
    }

    private function carregarRespostas(): void
    {
        $pautas = $this->pautasDisponiveis;
        $alunos = $this->alunosDaSerie;
        $turmasIds = $this->turmasDaSerieDisponiveis
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($pautas->isEmpty() || $alunos->isEmpty() || $turmasIds === []) {
            $this->respostas = [];
            $this->alternativasPorPauta = [];

            return;
        }

        $respostasExistentes = AvaliacaoResposta::query()
            ->where('avaliacao_id', (int) $this->avaliacao)
            ->whereIn('turma_id', $turmasIds)
            ->whereIn('pauta_id', $pautas->pluck('id')->all())
            ->whereIn('aluno_id', $alunos->pluck('id')->all())
            ->get()
            ->keyBy(fn (AvaliacaoResposta $resposta): string => $resposta->pauta_id . '-' . $resposta->aluno_id);

        $pendenciasPorAluno = $alunos
            ->where('status', Aluno::STATUS_PENDENTE)
            ->pluck('pendencia_origem_aluno_id', 'id')
            ->filter();

        $referenciasOrigem = collect();

        if ($pendenciasPorAluno->isNotEmpty()) {
            $referenciasOrigem = AvaliacaoResposta::query()
                ->where('avaliacao_id', (int) $this->avaliacao)
                ->whereIn('pauta_id', $pautas->pluck('id')->all())
                ->whereIn('aluno_id', $pendenciasPorAluno->values()->all())
                ->with('alternativa:id,nome')
                ->get()
                ->keyBy(fn (AvaliacaoResposta $resposta): string => $resposta->pauta_id . '-' . $resposta->aluno_id);
        }

        $respostas = [];

        foreach ($this->turmasDaSerieDisponiveis as $turma) {
            $turmaId = (int) $turma->id;

            foreach ($this->pautasDaTurma($turmaId) as $pauta) {
                foreach ($this->alunosDaTurma($turmaId) as $aluno) {
                    $chave = $pauta->id . '-' . $aluno->id;
                    $resposta = $respostasExistentes->get($chave);
                    $origemId = (int) ($pendenciasPorAluno->get((int) $aluno->id) ?? 0);
                    $referenciaOrigem = $origemId > 0
                        ? $referenciasOrigem->get($pauta->id . '-' . $origemId)
                        : null;

                    $respostas[$pauta->id][$aluno->id] = [
                        'alternativa_id' => $resposta?->alternativa_id,
                        'observacao' => $resposta?->observacao,
                        'bloqueada' => (bool) ($resposta?->bloqueada ?? false),
                        'origem_referencia' => $referenciaOrigem ? [
                            'alternativa' => (string) ($referenciaOrigem->alternativa?->nome ?? ''),
                            'observacao' => (string) ($referenciaOrigem->observacao ?? ''),
                        ] : null,
                    ];
                }
            }
        }

        $this->respostas = $respostas;
    }

    private function carregarInformacoesComplementares(): void
    {
        $alunosIds = $this->alunosDaSerie
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $turmasIds = $this->turmasDaSerieDisponiveis
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($alunosIds === [] || $turmasIds === []) {
            $this->informacoesComplementares = [];
            $this->informacoesComplementaresBloqueadas = [];

            return;
        }

        $registros = AvaliacaoInformacaoComplementar::query()
            ->where('avaliacao_id', (int) $this->avaliacao)
            ->whereIn('turma_id', $turmasIds)
            ->whereIn('aluno_id', $alunosIds)
            ->get(['aluno_id', 'componente_curricular_id', 'informacoes_complementares', 'bloqueada'])
            ->keyBy(fn ($registro) => ((int) ($registro->componente_curricular_id ?? 0)) . '-' . ((int) $registro->aluno_id));

        $informacoes = [];
        $bloqueadas = [];

        foreach ($this->pautasDisponiveis as $pauta) {
            $componenteId = (int) ($pauta->componente_curricular_id ?? 0);

            foreach ($alunosIds as $alunoId) {
                $registro = $registros->get($componenteId . '-' . $alunoId);
                $informacoes[$componenteId][$alunoId] = (string) ($registro?->informacoes_complementares ?? '');
                $bloqueadas[$componenteId][$alunoId] = (bool) ($registro?->bloqueada ?? false);
            }
        }

        $this->informacoesComplementares = $informacoes;
        $this->informacoesComplementaresBloqueadas = $bloqueadas;
    }

    private function autoSalvarResposta(int $pautaId, int $alunoId): void
    {
        $this->abortSeNaoPuderResponder();

        if (! $this->avaliacao || ! $this->serie || $this->respostaEstaBloqueada($pautaId, $alunoId)) {
            return;
        }

        $aluno = $this->alunoDaSerieSelecionada($alunoId);

        if (! $aluno || $this->alunoEstaBloqueadoParaAvaliacao($aluno)) {
            return;
        }

        $turmaId = (int) $aluno->id_turma;
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

        $agora = now();

        AvaliacaoResposta::query()->upsert(
            [[
                'avaliacao_id' => (int) $this->avaliacao,
                'pauta_id' => $pautaId,
                'turma_id' => $turmaId,
                'aluno_id' => $alunoId,
                'professor_id' => $this->professorIdParaRegistro($turmaId, $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null),
                'alternativa_id' => $alternativaId,
                'observacao' => $temObservacao ? $observacaoInformada : null,
                'respondido_em' => $agora,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]],
            ['avaliacao_id', 'pauta_id', 'turma_id', 'aluno_id'],
            ['professor_id', 'alternativa_id', 'observacao', 'respondido_em', 'updated_at']
        );

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

        $turmaId = (int) $aluno->id_turma;
        $informacoes = $this->limitarTextoCampo($this->informacoesComplementares[$componenteId][$alunoId] ?? '');

        if ($informacoes === '') {
            AvaliacaoInformacaoComplementar::query()
                ->where('avaliacao_id', (int) $this->avaliacao)
                ->where('turma_id', $turmaId)
                ->where('aluno_id', $alunoId)
                ->where('componente_curricular_id', $componenteId > 0 ? $componenteId : null)
                ->delete();

            return;
        }

        AvaliacaoInformacaoComplementar::query()->upsert(
            [[
                'avaliacao_id' => (int) $this->avaliacao,
                'turma_id' => $turmaId,
                'aluno_id' => $alunoId,
                'componente_curricular_id' => $componenteId > 0 ? $componenteId : null,
                'professor_id' => $this->professorIdParaRegistro($turmaId, $componenteId > 0 ? $componenteId : null),
                'informacoes_complementares' => $informacoes,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['avaliacao_id', 'turma_id', 'aluno_id', 'componente_curricular_id'],
            ['professor_id', 'informacoes_complementares', 'updated_at']
        );

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

        AvaliacaoResposta::query()
            ->where('avaliacao_id', (int) $this->avaliacao)
            ->where('pauta_id', $pautaId)
            ->where('turma_id', (int) $aluno->id_turma)
            ->where('aluno_id', $alunoId)
            ->delete();

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

        $cacheKey = $turmaId . ':' . $componenteId;

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

    private function alternativaDaPauta(int $pautaId, ?int $alternativaId): ?array
    {
        if (! $alternativaId) {
            return null;
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
        return $this->alunosDaSerie->firstWhere('id', $alunoId);
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
        return $turmaId . ':' . $itemId;
    }

    private function chaveSerieEscola(int $escolaId, int $serieId): string
    {
        return $escolaId . ':' . $serieId;
    }

    private function normalizarQueryId(mixed $valor): ?int
    {
        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }

    private function limitarTextoCampo(mixed $valor): string
    {
        return mb_substr(trim((string) $valor), 0, self::LIMITE_CARACTERES_TEXTO);
    }

    private function emitirAtualizacaoDoWorkspaceAcompanhamento(): void
    {
        // O dashboard pai agora recarrega apenas ao fechar o modal.
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
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        if ($user->hasRole('Admin')) {
            return null;
        }

        $escolasIds = $user->idsEscolasVinculadas();

        return $escolasIds === [] ? [] : $escolasIds;
    }
}
