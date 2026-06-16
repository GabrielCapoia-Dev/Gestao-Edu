<?php

namespace App\Livewire;

use App\Models\Alternativa;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\AvaliacaoInformacaoComplementar;
use App\Models\AvaliacaoResposta;
use App\Models\Pauta;
use App\Models\User;
use App\Services\AlunoTransferenciaParecerService;
use App\Services\AlunoTransferenciaPendenteService;
use App\Services\UserService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AlunoParecerTransferenciaModal extends Component
{
    public int $alunoId;

    public ?int $alunoSelecionadoId = null;

    public array $respostasParecer = [];

    public array $observacoesParecer = [];

    public array $informacoesComplementaresParecer = [];

    public array $informacoesComplementaresBloqueadas = [];

    public array $avaliacoesExpandidas = [];

    public function mount(int $alunoId): void
    {
        $this->alunoId = $alunoId;
        $this->selecionarAluno($alunoId);
    }

    public function render(): View
    {
        return view('livewire.aluno-parecer-transferencia-modal');
    }

    public function updated($name, $value = null): void
    {
        if (! is_string($name) || $name === '') {
            return;
        }

        if (str_starts_with($name, 'respostasParecer.') || str_starts_with($name, 'observacoesParecer.')) {
            $partes = explode('.', $name);
            $avaliacaoId = (int) ($partes[1] ?? 0);
            $pautaId = (int) ($partes[2] ?? 0);

            if ($avaliacaoId > 0 && $pautaId > 0) {
                $this->autoSalvarRespostaParecer($avaliacaoId, $pautaId);
            }

            return;
        }

        if (str_starts_with($name, 'informacoesComplementaresParecer.')) {
            $partes = explode('.', $name);
            $avaliacaoId = (int) ($partes[1] ?? 0);
            $componenteId = (int) ($partes[2] ?? 0);

            if ($avaliacaoId > 0) {
                $this->autoSalvarInformacaoComplementarParecer($avaliacaoId, $componenteId);
            }
        }
    }

    public function salvarRespostaParecerCampo(int $avaliacaoId, int $pautaId, mixed $alternativaId): void
    {
        if ($avaliacaoId <= 0 || $pautaId <= 0) {
            return;
        }

        $this->respostasParecer[$avaliacaoId][$pautaId] = (string) ($alternativaId ?? '');

        $this->autoSalvarRespostaParecer($avaliacaoId, $pautaId);
    }

    public function salvarObservacaoParecerCampo(int $avaliacaoId, int $pautaId, mixed $observacao): void
    {
        if ($avaliacaoId <= 0 || $pautaId <= 0) {
            return;
        }

        $this->observacoesParecer[$avaliacaoId][$pautaId] = $this->limitarTextoCampo($observacao);

        $this->autoSalvarRespostaParecer($avaliacaoId, $pautaId);
    }

    public function salvarInformacaoComplementarParecerCampo(int $avaliacaoId, int $componenteId, mixed $informacoes): void
    {
        if ($avaliacaoId <= 0) {
            return;
        }

        $this->informacoesComplementaresParecer[$avaliacaoId][$componenteId] = $this->limitarTextoCampo($informacoes);

        $this->autoSalvarInformacaoComplementarParecer($avaliacaoId, $componenteId);
    }

    public function selecionarAluno(int $alunoId): void
    {
        $aluno = $this->buscarAlunoNoEscopo($alunoId);

        if (! $aluno) {
            return;
        }

        $this->alunoSelecionadoId = (int) $aluno->id;
        $this->carregarRespostasParecer($aluno);
        $this->carregarInformacoesComplementaresParecer($aluno);
        $this->carregarAvaliacoesExpandidas($aluno);
    }

    public function alternarAvaliacaoParecer(int $avaliacaoId): void
    {
        $this->avaliacoesExpandidas[$avaliacaoId] = ! $this->avaliacaoEstaExpandida($avaliacaoId);
    }

    public function avaliacaoEstaExpandida(int $avaliacaoId): bool
    {
        return (bool) ($this->avaliacoesExpandidas[$avaliacaoId] ?? false);
    }

    public function gerarParecerTransferencia(AlunoTransferenciaParecerService $service): ?Response
    {
        $aluno = $this->alunoSelecionado;

        if (! $aluno) {
            Notification::make()
                ->title('Selecione um aluno para continuar.')
                ->warning()
                ->send();

            return null;
        }

        if (! $aluno->estaMatriculado()) {
            Notification::make()
                ->title('Parecer somente para consulta.')
                ->body('Alunos históricos permanecem disponíveis para visualização, sem alteração de respostas ou transferência.')
                ->warning()
                ->send();

            return null;
        }

        if (! $this->podeGerarParecer) {
            Notification::make()
                ->title('Sem permissão para gerar o parecer.')
                ->warning()
                ->send();

            return null;
        }

        try {
            /** @var User $usuario */
            $usuario = Auth::user();
            $this->salvarRespostasParecer($aluno);
            $this->salvarInformacoesComplementaresParecer($aluno);
            $response = $service->exportarETransferir($aluno, $usuario);

            Notification::make()
                ->title('Parecer de Transferência gerado.')
                ->body('O aluno foi marcado como Transferido.')
                ->success()
                ->send();

            $this->selecionarAluno((int) $aluno->id);

            return $response;
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Não foi possível gerar o parecer.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }

    public function getAlunoSelecionadoProperty(): ?Aluno
    {
        return $this->alunoSelecionadoId
            ? $this->buscarAlunoNoEscopo((int) $this->alunoSelecionadoId)
            : null;
    }

    public function getParecerSomenteLeituraProperty(): bool
    {
        $aluno = $this->alunoSelecionado;

        return ! $aluno?->estaMatriculado();
    }

    public function getPodeGerarParecerProperty(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return ($this->alunoSelecionado?->estaMatriculado() ?? false)
            && ($user?->hasPermissionLike('gerar parecer de transferencia') ?? false);
    }

    public function getAvaliacoesDoAlunoProperty(): Collection
    {
        $aluno = $this->alunoSelecionado;

        if (! $aluno?->turma) {
            return collect();
        }

        $turma = $aluno->turma;

        return Avaliacao::query()
            ->whereHas('turmas', fn (Builder $turmas): Builder => $turmas->whereKey((int) $turma->id))
            ->with([
                'tipo:id,nome',
                'periodo:id,nome',
                'pautas' => fn ($query) => $query
                    ->where('status', true)
                    ->with(['componente:id,nome', 'alternativas:id,nome,status,tem_observacao']),
            ])
            ->orderBy('data_inicio')
            ->orderBy('id')
            ->get()
            ->map(fn (Avaliacao $avaliacao): array => $this->formatarAvaliacao($avaliacao, $aluno))
            ->filter(fn (array $avaliacao): bool => (int) ($avaliacao['total'] ?? 0) > 0)
            ->values();
    }

    private function buscarAlunoNoEscopo(int $alunoId): ?Aluno
    {
        $query = Aluno::query()
            ->with(['turma.escola', 'turma.serie'])
            ->whereKey($alunoId)
            ->whereHas('turma.avaliacoes');

        app(UserService::class)->aplicarFiltroAlunosDoUsuario($query, Auth::user());

        return $query->first();
    }

    private function formatarAvaliacao(Avaliacao $avaliacao, Aluno $aluno): array
    {
        $turma = $aluno->turma;
        $pautas = $avaliacao->pautas
            ->filter(fn (Pauta $pauta): bool => is_null($pauta->serie_id) || (int) $pauta->serie_id === (int) $turma?->id_serie)
            ->values();
        $componentesVisiveis = $this->componentesVisiveisParecer($aluno);

        if (is_array($componentesVisiveis)) {
            $pautas = $pautas
                ->filter(fn (Pauta $pauta): bool => $pauta->componente_curricular_id !== null
                    && in_array((int) $pauta->componente_curricular_id, $componentesVisiveis, true))
                ->values();
        }

        $respostas = AvaliacaoResposta::query()
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->where('turma_id', (int) $aluno->id_turma)
            ->where('aluno_id', (int) $aluno->id)
            ->with('alternativa:id,nome')
            ->get()
            ->keyBy('pauta_id');

        $alternativasPorPauta = $this->alternativasPorPauta($avaliacao, $pautas);
        $preenchidas = $pautas
            ->filter(function (Pauta $pauta) use ($avaliacao, $respostas, $alternativasPorPauta): bool {
                $alternativaId = $this->alternativaSelecionadaId(
                    (int) $avaliacao->id,
                    (int) $pauta->id,
                    $respostas->get((int) $pauta->id)
                );

                if (! $alternativaId) {
                    return false;
                }

                $alternativa = ($alternativasPorPauta[(int) $pauta->id] ?? collect())->firstWhere('id', $alternativaId);

                if (! (bool) ($alternativa?->tem_observacao ?? false)) {
                    return true;
                }

                return $this->observacaoParecer(
                    (int) $avaliacao->id,
                    (int) $pauta->id,
                    $respostas->get((int) $pauta->id)
                ) !== '';
            })
            ->count();
        $total = $pautas->count();
        $percentual = $total > 0 ? min(100, (int) round(($preenchidas / $total) * 100)) : 0;

        return [
            'id' => (int) $avaliacao->id,
            'nome' => (string) $avaliacao->nome,
            'tipo' => (string) ($avaliacao->tipo?->nome ?? ''),
            'periodo' => (string) ($avaliacao->periodo?->nome ?? ''),
            'periodo_datas' => trim(collect([
                $avaliacao->data_inicio?->format('d/m/Y'),
                $avaliacao->data_fim?->format('d/m/Y'),
            ])->filter()->join(' ate ')),
            'preenchidas' => $preenchidas,
            'total' => $total,
            'percentual' => $percentual,
            'componentes' => $pautas
                ->groupBy(fn (Pauta $pauta): int => (int) ($pauta->componente_curricular_id ?? 0))
                ->map(function (Collection $pautasDoComponente) use ($avaliacao, $aluno, $respostas, $alternativasPorPauta): array {
                    /** @var Pauta|null $primeiraPauta */
                    $primeiraPauta = $pautasDoComponente->first();
                    $componenteId = (int) ($primeiraPauta?->componente_curricular_id ?? 0);
                    $componenteEditavel = $this->podeEditarComponenteParecer($aluno, $componenteId > 0 ? $componenteId : null);

                    return [
                        'id' => $componenteId,
                        'nome' => (string) ($primeiraPauta?->componente?->nome ?? 'Geral'),
                        'editavel' => $componenteEditavel,
                        'informacoes_complementares' => (string) ($this->informacoesComplementaresParecer[(int) $avaliacao->id][$componenteId] ?? ''),
                        'informacao_bloqueada' => (bool) ($this->informacoesComplementaresBloqueadas[(int) $avaliacao->id][$componenteId] ?? false),
                        'pautas' => $pautasDoComponente
                            ->map(function (Pauta $pauta) use ($avaliacao, $componenteEditavel, $respostas, $alternativasPorPauta): array {
                                $resposta = $respostas->get((int) $pauta->id);
                                $alternativaId = $this->alternativaSelecionadaId(
                                    (int) $avaliacao->id,
                                    (int) $pauta->id,
                                    $resposta
                                );
                                $alternativas = $alternativasPorPauta[(int) $pauta->id] ?? collect();
                                $alternativaSelecionada = $alternativaId
                                    ? $alternativas->firstWhere('id', $alternativaId)
                                    : null;
                                $requerObservacao = (bool) ($alternativaSelecionada?->tem_observacao ?? false);

                                return [
                                    'id' => (int) $pauta->id,
                                    'texto' => (string) $pauta->texto,
                                    'alternativas' => $alternativas
                                        ->map(fn (Alternativa $alternativa): array => [
                                            'id' => (int) $alternativa->id,
                                            'nome' => (string) $alternativa->nome,
                                            'tem_observacao' => (bool) $alternativa->tem_observacao,
                                        ])
                                        ->values()
                                        ->all(),
                                    'alternativa_id' => $alternativaId ? (string) $alternativaId : '',
                                    'resposta' => (string) ($alternativaSelecionada?->nome ?? $resposta?->alternativa?->nome ?? ''),
                                    'observacao' => $this->observacaoParecer(
                                        (int) $avaliacao->id,
                                        (int) $pauta->id,
                                        $resposta
                                    ),
                                    'requer_observacao' => $requerObservacao,
                                    'bloqueada' => (bool) ($resposta?->bloqueada ?? false),
                                    'editavel' => $componenteEditavel,
                                ];
                            })
                            ->values()
                            ->all(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    private function carregarRespostasParecer(Aluno $aluno): void
    {
        if (! $aluno->turma) {
            $this->respostasParecer = [];
            $this->observacoesParecer = [];

            return;
        }

        $avaliacoesIds = Avaliacao::query()
            ->whereHas('turmas', fn (Builder $turmas): Builder => $turmas->whereKey((int) $aluno->id_turma))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($avaliacoesIds === []) {
            $this->respostasParecer = [];
            $this->observacoesParecer = [];

            return;
        }

        $respostas = [];
        $observacoes = [];

        AvaliacaoResposta::query()
            ->whereIn('avaliacao_id', $avaliacoesIds)
            ->where('turma_id', (int) $aluno->id_turma)
            ->where('aluno_id', (int) $aluno->id)
            ->get(['avaliacao_id', 'pauta_id', 'alternativa_id', 'observacao'])
            ->each(function (AvaliacaoResposta $resposta) use (&$respostas, &$observacoes): void {
                $avaliacaoId = (int) $resposta->avaliacao_id;
                $pautaId = (int) $resposta->pauta_id;

                if ($resposta->alternativa_id) {
                    $respostas[$avaliacaoId][$pautaId] = (string) $resposta->alternativa_id;
                }

                $observacoes[$avaliacaoId][$pautaId] = (string) ($resposta->observacao ?? '');
            });

        $this->respostasParecer = $respostas;
        $this->observacoesParecer = $observacoes;
    }

    private function salvarRespostasParecer(Aluno $aluno): void
    {
        if (! $aluno->estaMatriculado() || ! $aluno->turma || $this->respostasParecer === []) {
            return;
        }

        foreach ($this->respostasParecer as $avaliacaoId => $respostasPorPauta) {
            if (! is_array($respostasPorPauta)) {
                continue;
            }

            foreach ($respostasPorPauta as $pautaId => $alternativaId) {
                $this->persistirRespostaParecer($aluno, (int) $avaliacaoId, (int) $pautaId, true);
            }
        }
    }

    private function autoSalvarRespostaParecer(int $avaliacaoId, int $pautaId): void
    {
        $aluno = $this->alunoSelecionado;

        if (! $aluno?->estaMatriculado() || ! $aluno->turma) {
            return;
        }

        try {
            $this->persistirRespostaParecer($aluno, $avaliacaoId, $pautaId, false);
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Não foi possível salvar a resposta.')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    private function persistirRespostaParecer(Aluno $aluno, int $avaliacaoId, int $pautaId, bool $validarObservacaoObrigatoria): void
    {
        if (! $aluno->estaMatriculado() || ! $aluno->turma) {
            return;
        }

        /** @var Avaliacao|null $avaliacao */
        $avaliacao = Avaliacao::query()
            ->whereKey($avaliacaoId)
            ->whereHas('turmas', fn (Builder $turmas): Builder => $turmas->whereKey((int) $aluno->id_turma))
            ->with([
                'pautas' => fn ($query) => $query
                    ->whereKey($pautaId)
                    ->where('status', true)
                    ->with(['alternativas:id,nome,status,tem_observacao', 'componente:id,nome']),
            ])
            ->first();

        if (! $avaliacao) {
            return;
        }

        /** @var Pauta|null $pauta */
        $pauta = $avaliacao->pautas
            ->filter(fn (Pauta $pauta): bool => is_null($pauta->serie_id) || (int) $pauta->serie_id === (int) $aluno->turma?->id_serie)
            ->firstWhere('id', $pautaId);

        if (! $pauta) {
            return;
        }

        if (! $this->podeEditarComponenteParecer($aluno, $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null)) {
            return;
        }

        $resposta = AvaliacaoResposta::query()
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->where('pauta_id', (int) $pauta->id)
            ->where('turma_id', (int) $aluno->id_turma)
            ->where('aluno_id', (int) $aluno->id)
            ->first();

        if ($resposta?->bloqueada) {
            return;
        }

        $alternativaId = (int) ($this->respostasParecer[(int) $avaliacao->id][(int) $pauta->id] ?? 0);

        if ($alternativaId <= 0 && ! $resposta) {
            return;
        }

        $alternativaSelecionada = null;

        if ($alternativaId > 0) {
            $alternativaSelecionada = ($this->alternativasPorPauta($avaliacao, collect([$pauta]))[(int) $pauta->id] ?? collect())
                ->firstWhere('id', $alternativaId);

            if (! $alternativaSelecionada) {
                throw new RuntimeException('A alternativa selecionada não pertence a pauta informada.');
            }
        }

        $temObservacao = (bool) ($alternativaSelecionada?->tem_observacao ?? false);
        $observacao = $temObservacao
            ? $this->limitarTextoCampo($this->observacoesParecer[(int) $avaliacao->id][(int) $pauta->id] ?? $resposta?->observacao ?? '')
            : null;

        if ($temObservacao && $validarObservacaoObrigatoria && $observacao === '') {
            throw new RuntimeException('Preencha a observação obrigatória das alternativas que exigem observação.');
        }

        AvaliacaoResposta::query()->updateOrCreate(
            [
                'avaliacao_id' => (int) $avaliacao->id,
                'pauta_id' => (int) $pauta->id,
                'turma_id' => (int) $aluno->id_turma,
                'aluno_id' => (int) $aluno->id,
            ],
            [
                'professor_id' => $this->professorIdParaComponenteParecer($aluno, $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null)
                    ?? $resposta?->professor_id,
                'alternativa_id' => $alternativaId > 0 ? $alternativaId : null,
                'observacao' => $observacao !== '' ? $observacao : null,
                'respondido_em' => $alternativaId > 0 ? now() : null,
            ]
        );
    }

    private function carregarInformacoesComplementaresParecer(Aluno $aluno): void
    {
        if (! $aluno->turma) {
            $this->informacoesComplementaresParecer = [];
            $this->informacoesComplementaresBloqueadas = [];

            return;
        }

        $avaliacoesIds = Avaliacao::query()
            ->whereHas('turmas', fn (Builder $turmas): Builder => $turmas->whereKey((int) $aluno->id_turma))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($avaliacoesIds === []) {
            $this->informacoesComplementaresParecer = [];
            $this->informacoesComplementaresBloqueadas = [];

            return;
        }

        $informacoes = [];
        $bloqueadas = [];

        AvaliacaoInformacaoComplementar::query()
            ->whereIn('avaliacao_id', $avaliacoesIds)
            ->where('turma_id', (int) $aluno->id_turma)
            ->where('aluno_id', (int) $aluno->id)
            ->get(['avaliacao_id', 'componente_curricular_id', 'informacoes_complementares', 'bloqueada'])
            ->each(function (AvaliacaoInformacaoComplementar $registro) use (&$informacoes, &$bloqueadas): void {
                $avaliacaoId = (int) $registro->avaliacao_id;
                $componenteId = (int) ($registro->componente_curricular_id ?? 0);

                $informacoes[$avaliacaoId][$componenteId] = (string) ($registro->informacoes_complementares ?? '');
                $bloqueadas[$avaliacaoId][$componenteId] = (bool) ($registro->bloqueada ?? false);
            });

        $this->informacoesComplementaresParecer = $informacoes;
        $this->informacoesComplementaresBloqueadas = $bloqueadas;
    }

    private function carregarAvaliacoesExpandidas(Aluno $aluno): void
    {
        if (! $aluno->turma) {
            $this->avaliacoesExpandidas = [];

            return;
        }

        $this->avaliacoesExpandidas = Avaliacao::query()
            ->whereHas('turmas', fn (Builder $turmas): Builder => $turmas->whereKey((int) $aluno->id_turma))
            ->pluck('id')
            ->mapWithKeys(fn ($id): array => [(int) $id => false])
            ->all();
    }

    private function salvarInformacoesComplementaresParecer(Aluno $aluno): void
    {
        if (! $aluno->estaMatriculado() || ! $aluno->turma || $this->informacoesComplementaresParecer === []) {
            return;
        }

        foreach ($this->informacoesComplementaresParecer as $avaliacaoId => $informacoesPorComponente) {
            if (! is_array($informacoesPorComponente)) {
                continue;
            }

            foreach ($informacoesPorComponente as $componenteId => $informacoes) {
                $this->persistirInformacaoComplementarParecer((int) $avaliacaoId, (int) $componenteId, $informacoes);
            }
        }
    }

    private function autoSalvarInformacaoComplementarParecer(int $avaliacaoId, int $componenteId): void
    {
        $aluno = $this->alunoSelecionado;

        if (! $aluno?->estaMatriculado() || ! $aluno->turma) {
            return;
        }

        $this->persistirInformacaoComplementarParecer(
            $avaliacaoId,
            $componenteId,
            $this->informacoesComplementaresParecer[(int) $avaliacaoId][$componenteId] ?? ''
        );
    }

    private function persistirInformacaoComplementarParecer(int $avaliacaoId, int $componenteId, mixed $valor): void
    {
        $aluno = $this->alunoSelecionado;

        if (! $aluno?->estaMatriculado() || ! $aluno->turma) {
            return;
        }

        $avaliacao = Avaliacao::query()
            ->whereKey($avaliacaoId)
            ->whereHas('turmas', fn (Builder $turmas): Builder => $turmas->whereKey((int) $aluno->id_turma))
            ->with([
                'pautas' => fn ($query) => $query
                    ->where('status', true)
                    ->with('componente:id,nome'),
            ])
            ->first();

        if (! $avaliacao) {
            return;
        }

        $componentesIds = $avaliacao->pautas
            ->filter(fn (Pauta $pauta): bool => is_null($pauta->serie_id) || (int) $pauta->serie_id === (int) $aluno->turma?->id_serie)
            ->pluck('componente_curricular_id')
            ->map(fn ($id) => (int) ($id ?? 0))
            ->unique()
            ->values()
            ->all();

        if (! in_array($componenteId, $componentesIds, true)) {
            return;
        }

        if (! $this->podeEditarComponenteParecer($aluno, $componenteId > 0 ? $componenteId : null)) {
            return;
        }

        $registroQuery = AvaliacaoInformacaoComplementar::query()
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->where('turma_id', (int) $aluno->id_turma)
            ->where('aluno_id', (int) $aluno->id);

        if ($componenteId > 0) {
            $registroQuery->where('componente_curricular_id', $componenteId);
        } else {
            $registroQuery->whereNull('componente_curricular_id');
        }

        $registro = $registroQuery->first();

        if ($registro?->bloqueada) {
            return;
        }

        $informacoes = $this->limitarTextoCampo($valor);

        if ($informacoes === '') {
            if ($registro) {
                $registro->delete();
            }

            return;
        }

        AvaliacaoInformacaoComplementar::query()->updateOrCreate(
            [
                'avaliacao_id' => (int) $avaliacao->id,
                'turma_id' => (int) $aluno->id_turma,
                'aluno_id' => (int) $aluno->id,
                'componente_curricular_id' => $componenteId > 0 ? $componenteId : null,
            ],
            [
                'professor_id' => $this->professorIdParaComponenteParecer($aluno, $componenteId > 0 ? $componenteId : null)
                    ?? $registro?->professor_id,
                'informacoes_complementares' => $informacoes,
            ]
        );
    }

    /**
     * @param  Collection<int, Pauta>  $pautas
     * @return array<int, Collection<int, Alternativa>>
     */
    private function alternativasPorPauta(Avaliacao $avaliacao, Collection $pautas): array
    {
        $pautasIds = $pautas->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($pautasIds === []) {
            return [];
        }

        $overrides = DB::table('avaliacao_pauta_alternativa')
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->whereIn('pauta_id', $pautasIds)
            ->get(['pauta_id', 'alternativa_id'])
            ->groupBy('pauta_id')
            ->map(fn (Collection $rows): array => $rows->pluck('alternativa_id')->map(fn ($id) => (int) $id)->all());

        $overrideAlternativas = Alternativa::query()
            ->whereIn('id', $overrides->flatten()->unique()->values()->all())
            ->where('status', true)
            ->orderBy('nome')
            ->get()
            ->keyBy('id');

        $alternativasTipoAvaliacao = Alternativa::query()
            ->where('tipo_avaliacao_id', (int) $avaliacao->tipo_avaliacao_id)
            ->where('status', true)
            ->orderBy('nome')
            ->get();

        $porPauta = [];

        foreach ($pautas as $pauta) {
            $pautaId = (int) $pauta->id;
            $overrideIds = $overrides->get($pautaId, []);

            if ($overrideIds !== []) {
                $porPauta[$pautaId] = collect($overrideIds)
                    ->map(fn (int $id) => $overrideAlternativas->get($id))
                    ->filter()
                    ->values();

                continue;
            }

            $alternativas = $pauta->alternativas
                ->where('status', true)
                ->sortBy(fn (Alternativa $alternativa): string => mb_strtolower((string) $alternativa->nome))
                ->values();

            if ($alternativas->isEmpty()) {
                $alternativas = $alternativasTipoAvaliacao;
            }

            $porPauta[$pautaId] = $alternativas->values();
        }

        return $porPauta;
    }

    private function alternativaSelecionadaId(int $avaliacaoId, int $pautaId, ?AvaliacaoResposta $resposta): ?int
    {
        if (
            array_key_exists($avaliacaoId, $this->respostasParecer)
            && is_array($this->respostasParecer[$avaliacaoId])
            && array_key_exists($pautaId, $this->respostasParecer[$avaliacaoId])
        ) {
            $alternativaId = (int) $this->respostasParecer[$avaliacaoId][$pautaId];

            return $alternativaId > 0 ? $alternativaId : null;
        }

        $alternativaId = (int) ($resposta?->alternativa_id ?? 0);

        return $alternativaId > 0 ? $alternativaId : null;
    }

    private function observacaoParecer(int $avaliacaoId, int $pautaId, ?AvaliacaoResposta $resposta): string
    {
        if (
            array_key_exists($avaliacaoId, $this->observacoesParecer)
            && is_array($this->observacoesParecer[$avaliacaoId])
            && array_key_exists($pautaId, $this->observacoesParecer[$avaliacaoId])
        ) {
            return $this->limitarTextoCampo($this->observacoesParecer[$avaliacaoId][$pautaId]);
        }

        return $this->limitarTextoCampo($resposta?->observacao ?? '');
    }

    private function limitarTextoCampo(mixed $valor): string
    {
        return mb_substr(trim((string) $valor), 0, 1500);
    }

    private function podeEditarComponenteParecer(Aluno $aluno, ?int $componenteId): bool
    {
        return app(AlunoTransferenciaPendenteService::class)
            ->professorPodeResponderComponente(Auth::user(), $aluno, $componenteId);
    }

    private function componentesVisiveisParecer(Aluno $aluno): ?array
    {
        return app(AlunoTransferenciaPendenteService::class)
            ->componentesVisiveisParaParecer(Auth::user(), $aluno);
    }

    private function professorIdParaComponenteParecer(Aluno $aluno, ?int $componenteId): ?int
    {
        return app(AlunoTransferenciaPendenteService::class)
            ->professorIdParaComponente(Auth::user(), $aluno, $componenteId);
    }
}
