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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use UnitEnum;

class ParecerTransferenciaAluno extends Page
{
    protected string $view = 'filament.pages.parecer-transferencia-aluno';

    protected static ?string $title = 'Parecer de Transferencia';

    protected static ?string $navigationLabel = 'Parecer de Transferencia';

    protected static ?string $slug = 'parecer-transferencia-aluno';

    protected static ?int $navigationSort = 25;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public string $busca = '';

    public ?int $alunoSelecionadoId = null;

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
    }

    public function selecionarAluno(int $alunoId): void
    {
        $aluno = $this->buscarAlunoNoEscopo($alunoId);

        if (! $aluno) {
            return;
        }

        $this->alunoSelecionadoId = (int) $aluno->id;
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
            return $service->exportarETransferir($aluno, Auth::user());
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Nao foi possivel gerar o parecer.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }

    public function getAlunosProperty(): Collection
    {
        $query = Aluno::query()
            ->with(['turma.escola', 'turma.serie'])
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
            ->limit(40)
            ->get();
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
                    ->with(['componente:id,nome', 'alternativas:id,nome']),
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

        $preenchidas = $pautas
            ->filter(fn (Pauta $pauta): bool => filled($respostas->get((int) $pauta->id)?->alternativa_id))
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
                ->map(function (Collection $pautasDoComponente) use ($respostas): array {
                    return [
                        'nome' => (string) ($pautasDoComponente->first()?->componente?->nome ?? 'Geral'),
                        'pautas' => $pautasDoComponente
                            ->map(function (Pauta $pauta) use ($respostas): array {
                                $resposta = $respostas->get((int) $pauta->id);

                                return [
                                    'texto' => (string) $pauta->texto,
                                    'alternativas' => $pauta->alternativas
                                        ->map(fn (Alternativa $alternativa): string => (string) $alternativa->nome)
                                        ->values()
                                        ->all(),
                                    'resposta' => (string) ($resposta?->alternativa?->nome ?? ''),
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

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }
}
