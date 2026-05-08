<?php

namespace App\Filament\Admin\Pages;

use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoInformacaoComplementar;
use App\Models\AvaliacaoResposta;
use App\Models\Pauta;
use App\Models\Turma;
use App\Models\User;
use App\Services\AlunoTransferenciaParecerService;
use App\Services\UserService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use UnitEnum;

class ParecerTransferenciaAluno extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.parecer-transferencia-aluno';

    protected static ?string $title = 'Parecer de Transferencia';

    protected static ?string $navigationLabel = 'Parecer de Transferencia';

    protected static ?string $slug = 'parecer-transferencia-aluno';

    protected static ?int $navigationSort = 25;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public string $busca = '';

    public string $turmaFiltro = '';

    public string $porPagina = '10';

    public ?int $alunoSelecionadoId = null;

    public bool $slideoverAberto = false;

    public array $respostasParecer = [];

    public array $observacoesParecer = [];

    public array $informacoesComplementaresParecer = [];

    public array $informacoesComplementaresBloqueadas = [];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return ($user?->hasPermissionLike('realizar transferencia de aluno') ?? false)
            || ($user?->hasPermissionLike('realizar tranferencia de aluno') ?? false)
            || ($user?->hasPermissionLike('gerar parecer de transferencia') ?? false);
    }

    public function mount(): void
    {
        $alunoId = (int) request()->query('aluno');

        if ($alunoId > 0) {
            $this->selecionarAluno($alunoId);
        }
    }

    public function updatedBusca(): void
    {
        $this->alunoSelecionadoId = null;
        $this->slideoverAberto = false;
        $this->respostasParecer = [];
        $this->observacoesParecer = [];
        $this->informacoesComplementaresParecer = [];
        $this->informacoesComplementaresBloqueadas = [];
        $this->resetPage();
    }

    public function updatedTurmaFiltro(): void
    {
        $this->alunoSelecionadoId = null;
        $this->slideoverAberto = false;
        $this->respostasParecer = [];
        $this->observacoesParecer = [];
        $this->informacoesComplementaresParecer = [];
        $this->informacoesComplementaresBloqueadas = [];
        $this->resetPage();
    }

    public function updatedPorPagina(): void
    {
        $this->porPagina = (string) $this->quantidadePorPagina();
        $this->resetPage();
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
        $this->slideoverAberto = true;
    }

    public function fecharSlideover(): void
    {
        $this->slideoverAberto = false;
        $this->alunoSelecionadoId = null;
        $this->respostasParecer = [];
        $this->observacoesParecer = [];
        $this->informacoesComplementaresParecer = [];
        $this->informacoesComplementaresBloqueadas = [];
    }

    public function gerarParecerTransferencia(AlunoTransferenciaParecerService $service): ?Response
    {
        $aluno = $this->alunoSelecionado;

        if (! $aluno) {
            Notification::make()
                ->title('Selecione um aluno matriculado para continuar.')
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
                ->title('Parecer de Transferencia gerado.')
                ->body('O aluno foi marcado como Transferido.')
                ->success()
                ->send();

            $this->fecharSlideover();
            $this->resetPage();

            return $response;
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Nao foi possivel gerar o parecer.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }

    public function getAlunosProperty(): LengthAwarePaginator
    {
        $query = Aluno::query()
            ->with(['turma.escola', 'turma.serie'])
            ->whereHas('turma.avaliacoes')
            ->where('status', Aluno::STATUS_MATRICULADO);

        app(UserService::class)->aplicarFiltroAlunosDoUsuario($query, Auth::user());

        $busca = trim($this->busca);
        $turmaFiltro = (int) $this->turmaFiltro;

        if ($turmaFiltro > 0) {
            $query->where('id_turma', $turmaFiltro);
        }

        if ($busca !== '') {
            $query->where(function (Builder $alunos) use ($busca): void {
                $alunos
                    ->where('nome', 'like', '%'.$busca.'%')
                    ->orWhere('cgm', 'like', '%'.$busca.'%')
                    ->orWhereHas('turma.escola', fn (Builder $escolas): Builder => $escolas->where('nome', 'like', '%'.$busca.'%'))
                    ->orWhereHas('turma.serie', fn (Builder $series): Builder => $series->where('nome', 'like', '%'.$busca.'%'));
            });
        }

        return $query
            ->orderBy('nome')
            ->paginate($this->quantidadePorPagina());
    }

    public function opcoesPorPagina(): array
    {
        return [
            5 => '5',
            10 => '10',
            25 => '25',
            50 => '50',
            100 => '100',
        ];
    }

    public function opcoesTurmas(): array
    {
        $query = Aluno::query()
            ->with(['turma.escola', 'turma.serie'])
            ->whereHas('turma.avaliacoes')
            ->where('status', Aluno::STATUS_MATRICULADO);

        app(UserService::class)->aplicarFiltroAlunosDoUsuario($query, Auth::user());

        return $query
            ->get()
            ->pluck('turma')
            ->filter(fn (?Turma $turma): bool => $turma !== null)
            ->unique(fn (Turma $turma): int => (int) $turma->id)
            ->sortBy(fn (Turma $turma): string => mb_strtolower(trim(implode(' ', [
                (string) ($turma->escola?->nome ?? ''),
                (string) ($turma->serie?->nome ?? ''),
                (string) $turma->nome,
            ]))))
            ->mapWithKeys(fn (Turma $turma): array => [
                (int) $turma->id => trim(implode(' | ', array_filter([
                    $turma->escola?->nome,
                    trim(implode(' - ', array_filter([$turma->serie?->nome, $turma->nome]))),
                ]))),
            ])
            ->all();
    }

    public function getAlunoSelecionadoProperty(): ?Aluno
    {
        return $this->alunoSelecionadoId
            ? $this->buscarAlunoNoEscopo((int) $this->alunoSelecionadoId)
            : null;
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
            ->map(fn (Avaliacao $avaliacao): array => $this->formatarAvaliacao($avaliacao, $aluno));
    }

    private function buscarAlunoNoEscopo(int $alunoId): ?Aluno
    {
        $query = Aluno::query()
            ->with(['turma.escola', 'turma.serie'])
            ->whereKey($alunoId)
            ->whereHas('turma.avaliacoes')
            ->where('status', Aluno::STATUS_MATRICULADO);

        app(UserService::class)->aplicarFiltroAlunosDoUsuario($query, Auth::user());

        return $query->first();
    }

    private function formatarAvaliacao(Avaliacao $avaliacao, Aluno $aluno): array
    {
        $turma = $aluno->turma;
        $pautas = $avaliacao->pautas
            ->filter(fn (Pauta $pauta): bool => is_null($pauta->serie_id) || (int) $pauta->serie_id === (int) $turma?->id_serie)
            ->values();

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
                ->map(function (Collection $pautasDoComponente) use ($avaliacao, $respostas, $alternativasPorPauta): array {
                    /** @var Pauta|null $primeiraPauta */
                    $primeiraPauta = $pautasDoComponente->first();
                    $componenteId = (int) ($primeiraPauta?->componente_curricular_id ?? 0);

                    return [
                        'id' => $componenteId,
                        'nome' => (string) ($primeiraPauta?->componente?->nome ?? 'Geral'),
                        'informacoes_complementares' => (string) ($this->informacoesComplementaresParecer[(int) $avaliacao->id][$componenteId] ?? ''),
                        'informacao_bloqueada' => (bool) ($this->informacoesComplementaresBloqueadas[(int) $avaliacao->id][$componenteId] ?? false),
                        'pautas' => $pautasDoComponente
                            ->map(function (Pauta $pauta) use ($avaliacao, $respostas, $alternativasPorPauta): array {
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
        if (! $aluno->turma || $this->respostasParecer === []) {
            return;
        }

        $avaliacoes = Avaliacao::query()
            ->whereHas('turmas', fn (Builder $turmas): Builder => $turmas->whereKey((int) $aluno->id_turma))
            ->with([
                'pautas' => fn ($query) => $query
                    ->where('status', true)
                    ->with(['alternativas:id,nome,status,tem_observacao', 'componente:id,nome']),
            ])
            ->get()
            ->keyBy('id');

        foreach ($this->respostasParecer as $avaliacaoId => $respostasPorPauta) {
            /** @var Avaliacao|null $avaliacao */
            $avaliacao = $avaliacoes->get((int) $avaliacaoId);

            if (! $avaliacao || ! is_array($respostasPorPauta)) {
                continue;
            }

            $pautas = $avaliacao->pautas
                ->filter(fn (Pauta $pauta): bool => is_null($pauta->serie_id) || (int) $pauta->serie_id === (int) $aluno->turma?->id_serie)
                ->values();
            $pautasIds = $pautas->pluck('id')->map(fn ($id) => (int) $id)->all();
            $alternativasPorPauta = $this->alternativasPorPauta($avaliacao, $pautas);

            foreach ($respostasPorPauta as $pautaId => $alternativaId) {
                $pautaId = (int) $pautaId;

                if (! in_array($pautaId, $pautasIds, true)) {
                    continue;
                }

                $resposta = AvaliacaoResposta::query()
                    ->where('avaliacao_id', (int) $avaliacao->id)
                    ->where('pauta_id', $pautaId)
                    ->where('turma_id', (int) $aluno->id_turma)
                    ->where('aluno_id', (int) $aluno->id)
                    ->first();

                if ($resposta?->bloqueada) {
                    continue;
                }

                $alternativaId = (int) $alternativaId;

                if ($alternativaId <= 0 && ! $resposta) {
                    continue;
                }

                $alternativaSelecionada = null;

                if ($alternativaId > 0) {
                    $alternativaSelecionada = ($alternativasPorPauta[$pautaId] ?? collect())
                        ->firstWhere('id', $alternativaId);

                    if (! $alternativaSelecionada) {
                        throw new RuntimeException('A alternativa selecionada nao pertence a pauta informada.');
                    }
                }

                $temObservacao = (bool) ($alternativaSelecionada?->tem_observacao ?? false);
                $observacao = $temObservacao
                    ? $this->limitarTextoCampo($this->observacoesParecer[(int) $avaliacao->id][$pautaId] ?? $resposta?->observacao ?? '')
                    : null;

                if ($temObservacao && $observacao === '') {
                    throw new RuntimeException('Preencha a observacao obrigatoria das alternativas que exigem observacao.');
                }

                AvaliacaoResposta::query()->updateOrCreate(
                    [
                        'avaliacao_id' => (int) $avaliacao->id,
                        'pauta_id' => $pautaId,
                        'turma_id' => (int) $aluno->id_turma,
                        'aluno_id' => (int) $aluno->id,
                    ],
                    [
                        'professor_id' => $resposta?->professor_id,
                        'alternativa_id' => $alternativaId > 0 ? $alternativaId : null,
                        'observacao' => $observacao,
                        'respondido_em' => $alternativaId > 0 ? now() : null,
                    ]
                );
            }
        }
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

    private function salvarInformacoesComplementaresParecer(Aluno $aluno): void
    {
        if (! $aluno->turma || $this->informacoesComplementaresParecer === []) {
            return;
        }

        $avaliacoes = Avaliacao::query()
            ->whereHas('turmas', fn (Builder $turmas): Builder => $turmas->whereKey((int) $aluno->id_turma))
            ->with([
                'pautas' => fn ($query) => $query
                    ->where('status', true)
                    ->with('componente:id,nome'),
            ])
            ->get()
            ->keyBy('id');

        foreach ($this->informacoesComplementaresParecer as $avaliacaoId => $informacoesPorComponente) {
            /** @var Avaliacao|null $avaliacao */
            $avaliacao = $avaliacoes->get((int) $avaliacaoId);

            if (! $avaliacao || ! is_array($informacoesPorComponente)) {
                continue;
            }

            $componentesIds = $avaliacao->pautas
                ->filter(fn (Pauta $pauta): bool => is_null($pauta->serie_id) || (int) $pauta->serie_id === (int) $aluno->turma?->id_serie)
                ->pluck('componente_curricular_id')
                ->map(fn ($id) => (int) ($id ?? 0))
                ->unique()
                ->values()
                ->all();

            foreach ($informacoesPorComponente as $componenteId => $informacoes) {
                $componenteId = (int) $componenteId;

                if (! in_array($componenteId, $componentesIds, true)) {
                    continue;
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
                    continue;
                }

                $informacoes = $this->limitarTextoCampo($informacoes);

                if ($informacoes === '') {
                    if ($registro) {
                        $registro->delete();
                    }

                    continue;
                }

                AvaliacaoInformacaoComplementar::query()->updateOrCreate(
                    [
                        'avaliacao_id' => (int) $avaliacao->id,
                        'turma_id' => (int) $aluno->id_turma,
                        'aluno_id' => (int) $aluno->id,
                        'componente_curricular_id' => $componenteId > 0 ? $componenteId : null,
                    ],
                    [
                        'professor_id' => $registro?->professor_id,
                        'informacoes_complementares' => $informacoes,
                    ]
                );
            }
        }
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

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }

    private function quantidadePorPagina(): int
    {
        $quantidade = (int) $this->porPagina;

        return in_array($quantidade, [5, 10, 25, 50, 100], true) ? $quantidade : 10;
    }
}
