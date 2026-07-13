<?php

namespace App\Filament\Admin\Pages;

use App\Models\Alternativa;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\AvaliacaoAlunoDocumento;
use App\Services\Avaliacoes\AvaliacaoAlunoDocumentoService;
use App\Models\Pauta;
use App\Models\Turma;
use App\Models\User;
use App\Services\AlunoTransferenciaParecerService;
use App\Services\AlunoTransferenciaPendenteService;
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

    protected static ?string $title = 'Parecer de Transferência';

    protected static ?string $navigationLabel = 'Parecer de Transferência';

    protected static ?string $slug = 'parecer-transferencia-aluno';

    protected static ?int $navigationSort = 25;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public string $busca = '';

    public string $escolaFiltro = '';

    public string $serieFiltro = '';

    public string $turmaFiltro = '';

    public string $semProfessorFiltro = '';

    public string $porPagina = '10';

    public ?int $alunoSelecionadoId = null;

    public bool $slideoverAberto = false;

    public array $respostasParecer = [];

    public array $observacoesParecer = [];

    public array $informacoesComplementaresParecer = [];

    public array $informacoesComplementaresBloqueadas = [];

    public array $avaliacoesExpandidas = [];

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => 'Parecer de Transferência de Aluno',
            'description' => 'Gerencie os pareceres de transferência dos alunos, acompanhe as informações necessárias para a transferência e mantenha um histórico detalhado para cada um.',
        ]);
    }

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
        $this->avaliacoesExpandidas = [];
        $this->resetPage();
    }

    public function updatedEscolaFiltro(): void
    {
        $this->turmaFiltro = '';
        $this->alunoSelecionadoId = null;
        $this->slideoverAberto = false;
        $this->respostasParecer = [];
        $this->observacoesParecer = [];
        $this->informacoesComplementaresParecer = [];
        $this->informacoesComplementaresBloqueadas = [];
        $this->avaliacoesExpandidas = [];
        $this->resetPage();
    }

    public function updatedSerieFiltro(): void
    {
        $this->turmaFiltro = '';
        $this->alunoSelecionadoId = null;
        $this->slideoverAberto = false;
        $this->respostasParecer = [];
        $this->observacoesParecer = [];
        $this->informacoesComplementaresParecer = [];
        $this->informacoesComplementaresBloqueadas = [];
        $this->avaliacoesExpandidas = [];
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
        $this->avaliacoesExpandidas = [];
        $this->resetPage();
    }

    public function updatedSemProfessorFiltro(): void
    {
        $this->alunoSelecionadoId = null;
        $this->slideoverAberto = false;
        $this->respostasParecer = [];
        $this->observacoesParecer = [];
        $this->informacoesComplementaresParecer = [];
        $this->informacoesComplementaresBloqueadas = [];
        $this->avaliacoesExpandidas = [];
        $this->resetPage();
    }

    public function updatedPorPagina(): void
    {
        $this->porPagina = (string) $this->quantidadePorPagina();
        $this->resetPage();
    }

    public function updated(string $name): void
    {
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
        $this->avaliacoesExpandidas = [];
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
                ->title('Selecione um aluno matriculado para continuar.')
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

            $this->fecharSlideover();
            $this->resetPage();

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

    public function getAlunosProperty(): LengthAwarePaginator
    {
        $query = $this->queryAlunosParecer();

        $busca = trim($this->busca);
        $escolaFiltro = (int) $this->escolaFiltro;
        $serieFiltro = (int) $this->serieFiltro;
        $turmaFiltro = (int) $this->turmaFiltro;
        $semProfessorFiltro = (string) $this->semProfessorFiltro;

        if ($escolaFiltro > 0) {
            $query->whereHas('turma', fn(Builder $turmas): Builder => $turmas->where('id_escola', $escolaFiltro));
        }

        if ($serieFiltro > 0) {
            $query->whereHas('turma', fn(Builder $turmas): Builder => $turmas->where('id_serie', $serieFiltro));
        }

        if ($turmaFiltro > 0) {
            $query->where('id_turma', $turmaFiltro);
        }

        if ($semProfessorFiltro === '1') {
            $this->aplicarFiltroPendenciaSemProfessor($query);
        }

        if ($busca !== '') {
            $query->where(function (Builder $alunos) use ($busca): void {
                $alunos
                    ->where('nome', 'like', '%' . $busca . '%')
                    ->orWhere('cgm', 'like', '%' . $busca . '%')
                    ->orWhereHas('turma.escola', fn(Builder $escolas): Builder => $escolas->where('nome', 'like', '%' . $busca . '%'))
                    ->orWhereHas('turma.serie', fn(Builder $series): Builder => $series->where('nome', 'like', '%' . $busca . '%'));
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

    public function opcoesEscolas(): array
    {
        return $this->queryAlunosParecer()
            ->get()
            ->pluck('turma.escola')
            ->filter()
            ->unique(fn($escola): int => (int) $escola->id)
            ->sortBy(fn($escola): string => mb_strtolower((string) $escola->nome))
            ->mapWithKeys(fn($escola): array => [(int) $escola->id => (string) $escola->nome])
            ->all();
    }

    public function opcoesSeries(): array
    {
        $query = $this->queryAlunosParecer();
        $escolaFiltro = (int) $this->escolaFiltro;

        if ($escolaFiltro > 0) {
            $query->whereHas('turma', fn(Builder $turmas): Builder => $turmas->where('id_escola', $escolaFiltro));
        }

        return $query
            ->get()
            ->pluck('turma.serie')
            ->filter()
            ->unique(fn($serie): int => (int) $serie->id)
            ->sortBy(fn($serie): string => mb_strtolower((string) $serie->nome))
            ->mapWithKeys(fn($serie): array => [(int) $serie->id => (string) $serie->nome])
            ->all();
    }

    public function opcoesTurmas(): array
    {
        $query = $this->queryAlunosParecer();
        $escolaFiltro = (int) $this->escolaFiltro;
        $serieFiltro = (int) $this->serieFiltro;

        if ($escolaFiltro > 0) {
            $query->whereHas('turma', fn(Builder $turmas): Builder => $turmas->where('id_escola', $escolaFiltro));
        }

        if ($serieFiltro > 0) {
            $query->whereHas('turma', fn(Builder $turmas): Builder => $turmas->where('id_serie', $serieFiltro));
        }

        return $query
            ->get()
            ->pluck('turma')
            ->filter(fn(?Turma $turma): bool => $turma !== null)
            ->unique(fn(Turma $turma): int => (int) $turma->id)
            ->sortBy(fn(Turma $turma): string => mb_strtolower(trim(implode(' ', [
                (string) ($turma->escola?->nome ?? ''),
                (string) ($turma->serie?->nome ?? ''),
                (string) $turma->nome,
            ]))))
            ->mapWithKeys(fn(Turma $turma): array => [
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
            ->whereHas('turmas', fn(Builder $turmas): Builder => $turmas->whereKey((int) $turma->id))
            ->with([
                'tipo:id,nome',
                'periodo:id,nome',
                'pautas' => fn($query) => $query
                    ->where('status', true)
                    ->with(['componente:id,nome', 'alternativas:id,nome,status,tem_observacao']),
            ])
            ->orderBy('data_inicio')
            ->orderBy('id')
            ->get()
            ->map(fn(Avaliacao $avaliacao): array => $this->formatarAvaliacao($avaliacao, $aluno))
            ->filter(fn(array $avaliacao): bool => (int) ($avaliacao['total'] ?? 0) > 0)
            ->values();
    }

    private function queryAlunosParecer(): Builder
    {
        $query = Aluno::query()
            ->with(['turma.escola', 'turma.serie'])
            ->whereHas('turma.avaliacoes')
            ->where('status', Aluno::STATUS_MATRICULADO);

        app(UserService::class)->aplicarFiltroAlunosDoUsuario($query, Auth::user());

        return $query;
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

    private function aplicarFiltroPendenciaSemProfessor(Builder $query): void
    {
        $query->whereExists(function ($pendencias): void {
            $pendencias
                ->selectRaw('1')
                ->from('avaliacao_turma as at')
                ->join('avaliacoes as av', 'av.id', '=', 'at.avaliacao_id')
                ->join('avaliacao_pauta as ap', 'ap.avaliacao_id', '=', 'at.avaliacao_id')
                ->join('pautas as p', 'p.id', '=', 'ap.pauta_id')
                ->join('turmas as t', 't.id', '=', 'at.turma_id')
                ->whereColumn('at.turma_id', 'alunos.id_turma')
                ->where('av.status', Avaliacao::STATUS_ATIVA)
                ->where('p.status', true)
                ->whereNotNull('p.componente_curricular_id')
                ->where(function ($series): void {
                    $series
                        ->whereNull('p.serie_id')
                        ->orWhereColumn('p.serie_id', 't.id_serie');
                })
                ->whereNotExists(function ($professores): void {
                    $professores
                        ->selectRaw('1')
                        ->from('turma_componente_professor as tcp')
                        ->whereColumn('tcp.turma_id', 'at.turma_id')
                        ->whereColumn('tcp.componente_curricular_id', 'p.componente_curricular_id')
                        ->whereNotNull('tcp.professor_id');
                })
                ->whereNotExists(function ($respostas): void {
                    $respostas
                        ->selectRaw('1')
                        ->from('avaliacao_aluno_documentos as d')
                        ->whereColumn('d.avaliacao_id', 'at.avaliacao_id')
                        ->whereColumn('d.aluno_id', 'alunos.id')
                        ->whereRaw(
                            'JSON_CONTAINS(COALESCE(d.pauta_ids_respondidas, JSON_ARRAY()), CAST(p.id AS JSON), \'$\')'
                        );
                });
        });
    }

    private function formatarAvaliacao(Avaliacao $avaliacao, Aluno $aluno): array
    {
        $turma = $aluno->turma;
        $pautas = $avaliacao->pautas
            ->filter(fn(Pauta $pauta): bool => is_null($pauta->serie_id) || (int) $pauta->serie_id === (int) $turma?->id_serie)
            ->values();
        $componentesVisiveis = $this->componentesVisiveisParecer($aluno);

        if (is_array($componentesVisiveis)) {
            $pautas = $pautas
                ->filter(fn(Pauta $pauta): bool => $pauta->componente_curricular_id !== null
                    && in_array((int) $pauta->componente_curricular_id, $componentesVisiveis, true))
                ->values();
        }

        $documento = app(AvaliacaoAlunoDocumentoService::class)->obter((int) $avaliacao->id, (int) $aluno->id);
        $respostas = collect($documento?->pautasPayload() ?? [])
            ->mapWithKeys(fn (array $item, string|int $pautaId): array => [(int) ($item['pauta_id'] ?? $pautaId) => $item]);

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
                ->groupBy(fn(Pauta $pauta): int => (int) ($pauta->componente_curricular_id ?? 0))
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
                                        ->map(fn(Alternativa $alternativa): array => [
                                            'id' => (int) $alternativa->id,
                                            'nome' => (string) $alternativa->nome,
                                            'tem_observacao' => (bool) $alternativa->tem_observacao,
                                        ])
                                        ->values()
                                        ->all(),
                                    'alternativa_id' => $alternativaId ? (string) $alternativaId : '',
                                    'resposta' => (string) ($alternativaSelecionada?->nome ?? ''),
                                    'observacao' => $this->observacaoParecer(
                                        (int) $avaliacao->id,
                                        (int) $pauta->id,
                                        is_array($resposta) ? $resposta : null
                                    ),
                                    'requer_observacao' => $requerObservacao,
                                    'bloqueada' => false,
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
            ->whereHas('turmas', fn(Builder $turmas): Builder => $turmas->whereKey((int) $aluno->id_turma))
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        if ($avaliacoesIds === []) {
            $this->respostasParecer = [];
            $this->observacoesParecer = [];

            return;
        }

        $respostas = [];
        $observacoes = [];

        AvaliacaoAlunoDocumento::query()
            ->whereIn('avaliacao_id', $avaliacoesIds)
            ->where('aluno_id', (int) $aluno->id)
            ->get()
            ->each(function (AvaliacaoAlunoDocumento $documento) use (&$respostas, &$observacoes): void {
                $avaliacaoId = (int) $documento->avaliacao_id;

                foreach ($documento->pautasPayload() as $pautaId => $dados) {
                    if (! is_array($dados)) {
                        continue;
                    }

                    $pautaId = (int) $pautaId;

                    if (! empty($dados['alternativa_id'])) {
                        $respostas[$avaliacaoId][$pautaId] = (string) $dados['alternativa_id'];
                    }

                    $observacoes[$avaliacaoId][$pautaId] = (string) ($dados['observacao'] ?? '');
                }
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
            ->whereHas('turmas', fn(Builder $turmas): Builder => $turmas->whereKey((int) $aluno->id_turma))
            ->with([
                'pautas' => fn($query) => $query
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
                ->filter(fn(Pauta $pauta): bool => is_null($pauta->serie_id) || (int) $pauta->serie_id === (int) $aluno->turma?->id_serie)
                ->values();
            $pautasPorId = $pautas->keyBy('id');
            $pautasIds = $pautas->pluck('id')->map(fn($id) => (int) $id)->all();
            $alternativasPorPauta = $this->alternativasPorPauta($avaliacao, $pautas);

            foreach ($respostasPorPauta as $pautaId => $alternativaId) {
                $pautaId = (int) $pautaId;

                if (! in_array($pautaId, $pautasIds, true)) {
                    continue;
                }

                /** @var Pauta|null $pauta */
                $pauta = $pautasPorId->get($pautaId);

                if (! $pauta || ! $this->podeEditarComponenteParecer($aluno, $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null)) {
                    continue;
                }

                $service = app(AvaliacaoAlunoDocumentoService::class);
                $documento = $service->obterOuCriar((int) $avaliacao->id, $aluno);
                $respostaAtual = $documento->respostaDaPauta($pautaId);

                $alternativaId = (int) $alternativaId;

                if ($alternativaId <= 0 && ! $respostaAtual) {
                    continue;
                }

                $alternativaSelecionada = null;

                if ($alternativaId > 0) {
                    $alternativaSelecionada = ($alternativasPorPauta[$pautaId] ?? collect())
                        ->firstWhere('id', $alternativaId);

                    if (! $alternativaSelecionada) {
                        throw new RuntimeException('A alternativa selecionada não pertence a pauta informada.');
                    }
                }

                $temObservacao = (bool) ($alternativaSelecionada?->tem_observacao ?? false);
                $observacao = $temObservacao
                    ? $this->limitarTextoCampo($this->observacoesParecer[(int) $avaliacao->id][$pautaId] ?? $respostaAtual['observacao'] ?? '')
                    : null;

                if ($temObservacao && $observacao === '') {
                    throw new RuntimeException('Preencha a observação obrigatória das alternativas que exigem observação.');
                }

                $service->salvarPauta($documento, $pautaId, [
                    'alternativa_id' => $alternativaId > 0 ? $alternativaId : null,
                    'observacao' => $observacao,
                    'professor_id' => $this->professorIdParaComponenteParecer($aluno, $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null)
                        ?? ($respostaAtual['professor_id'] ?? null),
                    'componente_curricular_id' => $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null,
                    'respondido_em' => $alternativaId > 0 ? now() : null,
                ]);
            }
        }
    }

    private function autoSalvarRespostaParecer(int $avaliacaoId, int $pautaId): void
    {
        $aluno = $this->alunoSelecionado;

        if (! $aluno?->turma) {
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
        if (! $aluno->turma) {
            return;
        }

        /** @var Avaliacao|null $avaliacao */
        $avaliacao = Avaliacao::query()
            ->whereKey($avaliacaoId)
            ->whereHas('turmas', fn(Builder $turmas): Builder => $turmas->whereKey((int) $aluno->id_turma))
            ->with([
                'pautas' => fn($query) => $query
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
            ->filter(fn(Pauta $pauta): bool => is_null($pauta->serie_id) || (int) $pauta->serie_id === (int) $aluno->turma?->id_serie)
            ->firstWhere('id', $pautaId);

        if (! $pauta) {
            return;
        }

        if (! $this->podeEditarComponenteParecer($aluno, $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null)) {
            return;
        }

        $service = app(AvaliacaoAlunoDocumentoService::class);
        $documento = $service->obterOuCriar((int) $avaliacao->id, $aluno);
        $resposta = $documento->respostaDaPauta((int) $pauta->id);

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
            ? $this->limitarTextoCampo($this->observacoesParecer[(int) $avaliacao->id][(int) $pauta->id] ?? $resposta['observacao'] ?? '')
            : null;

        if ($temObservacao && $validarObservacaoObrigatoria && $observacao === '') {
            throw new RuntimeException('Preencha a observação obrigatória das alternativas que exigem observação.');
        }

        $service->salvarPauta($documento, (int) $pauta->id, [
            'alternativa_id' => $alternativaId > 0 ? $alternativaId : null,
            'observacao' => $observacao !== '' ? $observacao : null,
            'professor_id' => $this->professorIdParaComponenteParecer($aluno, $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null)
                ?? ($resposta['professor_id'] ?? null),
            'componente_curricular_id' => $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null,
            'respondido_em' => $alternativaId > 0 ? now() : null,
        ]);
    }

    private function carregarInformacoesComplementaresParecer(Aluno $aluno): void
    {
        if (! $aluno->turma) {
            $this->informacoesComplementaresParecer = [];
            $this->informacoesComplementaresBloqueadas = [];

            return;
        }

        $avaliacoesIds = Avaliacao::query()
            ->whereHas('turmas', fn(Builder $turmas): Builder => $turmas->whereKey((int) $aluno->id_turma))
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        if ($avaliacoesIds === []) {
            $this->informacoesComplementaresParecer = [];
            $this->informacoesComplementaresBloqueadas = [];

            return;
        }

        $informacoes = [];
        $bloqueadas = [];

        AvaliacaoAlunoDocumento::query()
            ->whereIn('avaliacao_id', $avaliacoesIds)
            ->where('aluno_id', (int) $aluno->id)
            ->get()
            ->each(function (AvaliacaoAlunoDocumento $documento) use (&$informacoes, &$bloqueadas): void {
                $avaliacaoId = (int) $documento->avaliacao_id;

                foreach ($documento->informacoesComplementaresPayload() as $componenteId => $info) {
                    if (! is_array($info)) {
                        continue;
                    }

                    $componenteId = (int) $componenteId;
                    $informacoes[$avaliacaoId][$componenteId] = (string) ($info['texto'] ?? '');
                    $bloqueadas[$avaliacaoId][$componenteId] = false;
                }
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
            ->whereHas('turmas', fn(Builder $turmas): Builder => $turmas->whereKey((int) $aluno->id_turma))
            ->pluck('id')
            ->mapWithKeys(fn($id): array => [(int) $id => false])
            ->all();
    }

    private function salvarInformacoesComplementaresParecer(Aluno $aluno): void
    {
        if (! $aluno->turma || $this->informacoesComplementaresParecer === []) {
            return;
        }

        $avaliacoes = Avaliacao::query()
            ->whereHas('turmas', fn(Builder $turmas): Builder => $turmas->whereKey((int) $aluno->id_turma))
            ->with([
                'pautas' => fn($query) => $query
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
                ->filter(fn(Pauta $pauta): bool => is_null($pauta->serie_id) || (int) $pauta->serie_id === (int) $aluno->turma?->id_serie)
                ->pluck('componente_curricular_id')
                ->map(fn($id) => (int) ($id ?? 0))
                ->unique()
                ->values()
                ->all();

            foreach ($informacoesPorComponente as $componenteId => $informacoes) {
                $componenteId = (int) $componenteId;

                if (! in_array($componenteId, $componentesIds, true)) {
                    continue;
                }

                if (! $this->podeEditarComponenteParecer($aluno, $componenteId > 0 ? $componenteId : null)) {
                    continue;
                }

                $service = app(AvaliacaoAlunoDocumentoService::class);
                $documento = $service->obterOuCriar((int) $avaliacao->id, $aluno);
                $informacoes = $this->limitarTextoCampo($informacoes);

                $service->salvarInfoComplementar(
                    $documento,
                    $componenteId,
                    $informacoes !== '' ? $informacoes : null,
                    $this->professorIdParaComponenteParecer($aluno, $componenteId > 0 ? $componenteId : null),
                );
            }
        }
    }

    private function autoSalvarInformacaoComplementarParecer(int $avaliacaoId, int $componenteId): void
    {
        $aluno = $this->alunoSelecionado;

        if (! $aluno?->turma) {
            return;
        }

        $avaliacao = Avaliacao::query()
            ->whereKey($avaliacaoId)
            ->whereHas('turmas', fn(Builder $turmas): Builder => $turmas->whereKey((int) $aluno->id_turma))
            ->with([
                'pautas' => fn($query) => $query
                    ->where('status', true)
                    ->with('componente:id,nome'),
            ])
            ->first();

        if (! $avaliacao) {
            return;
        }

        $componentesIds = $avaliacao->pautas
            ->filter(fn(Pauta $pauta): bool => is_null($pauta->serie_id) || (int) $pauta->serie_id === (int) $aluno->turma?->id_serie)
            ->pluck('componente_curricular_id')
            ->map(fn($id) => (int) ($id ?? 0))
            ->unique()
            ->values()
            ->all();

        if (! in_array($componenteId, $componentesIds, true)) {
            return;
        }

        if (! $this->podeEditarComponenteParecer($aluno, $componenteId > 0 ? $componenteId : null)) {
            return;
        }

        $service = app(AvaliacaoAlunoDocumentoService::class);
        $documento = $service->obterOuCriar((int) $avaliacao->id, $aluno);
        $informacoes = $this->limitarTextoCampo($this->informacoesComplementaresParecer[(int) $avaliacao->id][$componenteId] ?? '');

        $service->salvarInfoComplementar(
            $documento,
            $componenteId,
            $informacoes !== '' ? $informacoes : null,
            $this->professorIdParaComponenteParecer($aluno, $componenteId > 0 ? $componenteId : null),
        );
    }

    /**
     * @param  Collection<int, Pauta>  $pautas
     * @return array<int, Collection<int, Alternativa>>
     */
    private function alternativasPorPauta(Avaliacao $avaliacao, Collection $pautas): array
    {
        $pautasIds = $pautas->pluck('id')->map(fn($id) => (int) $id)->all();

        if ($pautasIds === []) {
            return [];
        }

        $overrides = DB::table('avaliacao_pauta_alternativa')
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->whereIn('pauta_id', $pautasIds)
            ->get(['pauta_id', 'alternativa_id'])
            ->groupBy('pauta_id')
            ->map(fn(Collection $rows): array => $rows->pluck('alternativa_id')->map(fn($id) => (int) $id)->all());

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
                    ->map(fn(int $id) => $overrideAlternativas->get($id))
                    ->filter()
                    ->values();

                continue;
            }

            $alternativas = $pauta->alternativas
                ->where('status', true)
                ->sortBy(fn(Alternativa $alternativa): string => mb_strtolower((string) $alternativa->nome))
                ->values();

            if ($alternativas->isEmpty()) {
                $alternativas = $alternativasTipoAvaliacao;
            }

            $porPauta[$pautaId] = $alternativas->values();
        }

        return $porPauta;
    }

    private function alternativaSelecionadaId(int $avaliacaoId, int $pautaId, ?array $resposta): ?int
    {
        if (
            array_key_exists($avaliacaoId, $this->respostasParecer)
            && is_array($this->respostasParecer[$avaliacaoId])
            && array_key_exists($pautaId, $this->respostasParecer[$avaliacaoId])
        ) {
            $alternativaId = (int) $this->respostasParecer[$avaliacaoId][$pautaId];

            return $alternativaId > 0 ? $alternativaId : null;
        }

        $alternativaId = (int) ($resposta['alternativa_id'] ?? 0);

        return $alternativaId > 0 ? $alternativaId : null;
    }

    private function observacaoParecer(int $avaliacaoId, int $pautaId, ?array $resposta): string
    {
        if (
            array_key_exists($avaliacaoId, $this->observacoesParecer)
            && is_array($this->observacoesParecer[$avaliacaoId])
            && array_key_exists($pautaId, $this->observacoesParecer[$avaliacaoId])
        ) {
            return $this->limitarTextoCampo($this->observacoesParecer[$avaliacaoId][$pautaId]);
        }

        return $this->limitarTextoCampo($resposta['observacao'] ?? '');
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
