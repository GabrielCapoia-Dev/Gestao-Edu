<?php

namespace App\Filament\Admin\Pages;

use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoResposta;
use App\Models\Pauta;
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

    public string $porPagina = '10';

    public ?int $alunoSelecionadoId = null;

    public bool $slideoverAberto = false;

    public array $respostasParecer = [];

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
        $this->slideoverAberto = true;
    }

    public function fecharSlideover(): void
    {
        $this->slideoverAberto = false;
        $this->alunoSelecionadoId = null;
        $this->respostasParecer = [];
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
                    ->with(['componente:id,nome', 'alternativas:id,nome,status']),
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
            ->filter(fn (Pauta $pauta): bool => filled($this->alternativaSelecionadaId(
                (int) $avaliacao->id,
                (int) $pauta->id,
                $respostas->get((int) $pauta->id)
            )))
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
                ->groupBy(fn (Pauta $pauta): string => $pauta->componente?->nome ?? 'Geral')
                ->map(function (Collection $pautasDoComponente) use ($avaliacao, $respostas, $alternativasPorPauta): array {
                    return [
                        'nome' => (string) ($pautasDoComponente->first()?->componente?->nome ?? 'Geral'),
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

                                return [
                                    'id' => (int) $pauta->id,
                                    'texto' => (string) $pauta->texto,
                                    'alternativas' => $alternativas
                                        ->map(fn (Alternativa $alternativa): array => [
                                            'id' => (int) $alternativa->id,
                                            'nome' => (string) $alternativa->nome,
                                        ])
                                        ->values()
                                        ->all(),
                                    'alternativa_id' => $alternativaId ? (string) $alternativaId : '',
                                    'resposta' => (string) ($alternativaSelecionada?->nome ?? $resposta?->alternativa?->nome ?? ''),
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

            return;
        }

        $avaliacoesIds = Avaliacao::query()
            ->whereHas('turmas', fn (Builder $turmas): Builder => $turmas->whereKey((int) $aluno->id_turma))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($avaliacoesIds === []) {
            $this->respostasParecer = [];

            return;
        }

        $respostas = [];

        AvaliacaoResposta::query()
            ->whereIn('avaliacao_id', $avaliacoesIds)
            ->where('turma_id', (int) $aluno->id_turma)
            ->where('aluno_id', (int) $aluno->id)
            ->whereNotNull('alternativa_id')
            ->get(['avaliacao_id', 'pauta_id', 'alternativa_id'])
            ->each(function (AvaliacaoResposta $resposta) use (&$respostas): void {
                $respostas[(int) $resposta->avaliacao_id][(int) $resposta->pauta_id] = (string) $resposta->alternativa_id;
            });

        $this->respostasParecer = $respostas;
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
                    ->with(['alternativas:id,nome,status', 'componente:id,nome']),
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

                if ($alternativaId > 0) {
                    $alternativasValidas = ($alternativasPorPauta[$pautaId] ?? collect())
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    if (! in_array($alternativaId, $alternativasValidas, true)) {
                        throw new RuntimeException('A alternativa selecionada nao pertence a pauta informada.');
                    }
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
                        'respondido_em' => $alternativaId > 0 ? now() : null,
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
