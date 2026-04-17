<?php

namespace App\Filament\Admin\Pages;

use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\AvaliacaoResposta;
use App\Models\Pauta;
use App\Models\TurmaComponenteProfessor;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class AvaliacoesProfessor extends Page
{
    protected string $view = 'filament.pages.avaliacoes-professor';

    protected static ?string $title = 'Avaliações';

    protected static ?string $navigationLabel = 'Avaliações';

    protected static ?string $slug = 'avaliacoes-professor';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public ?int $avaliacao = null;

    public ?int $turma = null;

    public array $respostas = [];

    public array $avaliacaoEmMassa = [];

    public array $professorIds = [];

    public array $componentesPorTurma = [];

    public array $professorPorTurma = [];

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return ($user?->hasPermissionTo('Responder Avaliações') ?? false)
            && ($user?->ehProfessor() ?? false);
    }

    public function mount(): void
    {
        $this->sincronizarVinculosProfessor();

        $primeiraAvaliacao = $this->avaliacoesDisponiveis->first();

        if (! $primeiraAvaliacao) {
            return;
        }

        $this->avaliacao = (int) $primeiraAvaliacao->id;

        $primeiraTurma = $this->turmasDisponiveis->first();

        if (! $primeiraTurma) {
            return;
        }

        $this->turma = (int) $primeiraTurma->id;
        $this->carregarRespostas();
    }

    public function updatedAvaliacao(): void
    {
        $this->turma = null;
        $this->respostas = [];
        $this->avaliacaoEmMassa = [];

        if (! $this->avaliacao) {
            return;
        }

        $primeiraTurma = $this->turmasDisponiveis->first();

        if ($primeiraTurma) {
            $this->turma = (int) $primeiraTurma->id;
        }
    }

    public function updatedTurma(): void
    {
        $this->respostas = [];
        $this->avaliacaoEmMassa = [];

        if ($this->avaliacao && $this->turma) {
            $this->carregarRespostas();
        }
    }

    public function aplicarEmMassa(int $pautaId): void
    {
        $pauta = $this->pautasDisponiveis->firstWhere('id', $pautaId);

        if (! $pauta) {
            Notification::make()
                ->title('Pauta não encontrada para esta turma.')
                ->warning()
                ->send();

            return;
        }

        $alternativaId = (int) ($this->avaliacaoEmMassa[$pautaId] ?? 0);
        $alternativasValidas = $pauta->alternativas->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (! in_array($alternativaId, $alternativasValidas, true)) {
            Notification::make()
                ->title('Selecione uma alternativa válida para aplicar em massa.')
                ->warning()
                ->send();

            return;
        }

        foreach ($this->alunosDaTurma as $aluno) {
            $this->respostas[$pautaId][$aluno->id]['alternativa_id'] = $alternativaId;
        }

        Notification::make()
            ->title('Alternativa aplicada para toda a turma nesta pauta.')
            ->success()
            ->send();
    }

    public function salvarRespostas(): void
    {
        if (! $this->avaliacao || ! $this->turma) {
            Notification::make()
                ->title('Selecione uma avaliação e uma turma para continuar.')
                ->warning()
                ->send();

            return;
        }

        $pautas = $this->pautasDisponiveis;
        $alunos = $this->alunosDaTurma;

        if ($pautas->isEmpty() || $alunos->isEmpty()) {
            Notification::make()
                ->title('Não há pautas ou alunos disponíveis para avaliação.')
                ->warning()
                ->send();

            return;
        }

        $faltandoResposta = 0;
        $payload = [];
        $professorId = $this->professorIdDaTurma((int) $this->turma);
        $agora = now();

        foreach ($pautas as $pauta) {
            $alternativasValidas = $pauta->alternativas->pluck('id')->map(fn ($id) => (int) $id)->all();

            foreach ($alunos as $aluno) {
                $alternativaId = (int) ($this->respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? 0);

                if (! in_array($alternativaId, $alternativasValidas, true)) {
                    $faltandoResposta++;
                    continue;
                }

                $observacao = trim((string) ($this->respostas[$pauta->id][$aluno->id]['observacao'] ?? ''));

                $payload[] = [
                    'avaliacao_id' => (int) $this->avaliacao,
                    'pauta_id' => (int) $pauta->id,
                    'turma_id' => (int) $this->turma,
                    'aluno_id' => (int) $aluno->id,
                    'professor_id' => $professorId,
                    'alternativa_id' => $alternativaId,
                    'observacao' => $observacao !== '' ? $observacao : null,
                    'respondido_em' => $agora,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
            }
        }

        if ($faltandoResposta > 0) {
            Notification::make()
                ->title('Ainda existem respostas pendentes.')
                ->body('Preencha todas as combinações de aluno e pauta antes de salvar.')
                ->warning()
                ->send();

            return;
        }

        DB::transaction(function () use ($payload): void {
            AvaliacaoResposta::query()->upsert(
                $payload,
                ['avaliacao_id', 'pauta_id', 'turma_id', 'aluno_id'],
                ['professor_id', 'alternativa_id', 'observacao', 'respondido_em', 'updated_at']
            );
        });

        Notification::make()
            ->title('Avaliação salva com sucesso.')
            ->success()
            ->send();
    }

    public function getAvaliacoesDisponiveisProperty(): Collection
    {
        if ($this->professorIds === []) {
            return collect();
        }

        $avaliacoes = Avaliacao::query()
            ->pendentesParaData(now())
            ->whereHas('turmas.componentes', function ($query) {
                $query->whereIn('turma_componente_professor.professor_id', $this->professorIds);
            })
            ->with([
                'pautas' => fn ($query) => $query
                    ->where('status', true)
                    ->with([
                        'componente:id,nome',
                        'alternativas' => fn ($alternativas) => $alternativas->where('status', true),
                    ]),
                'turmas' => fn ($query) => $query->with(['escola:id,nome', 'serie:id,nome']),
            ])
            ->orderBy('data_inicio')
            ->get();

        return $avaliacoes
            ->filter(fn (Avaliacao $avaliacao) => $this->filtrarTurmasDaAvaliacao($avaliacao)->isNotEmpty())
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

        return $this->filtrarTurmasDaAvaliacao($this->avaliacaoAtual);
    }

    public function getPautasDisponiveisProperty(): Collection
    {
        if (! $this->avaliacaoAtual || ! $this->turma) {
            return collect();
        }

        $componentesProfessor = $this->componentesPorTurma[(int) $this->turma] ?? [];

        return $this->avaliacaoAtual->pautas
            ->filter(function (Pauta $pauta) use ($componentesProfessor): bool {
                if ($pauta->alternativas->isEmpty()) {
                    return false;
                }

                return $this->pautaEhRelevanteParaComponentes($pauta, $componentesProfessor);
            })
            ->values();
    }

    public function getAlunosDaTurmaProperty(): Collection
    {
        if (! $this->turma) {
            return collect();
        }

        return Aluno::query()
            ->where('id_turma', (int) $this->turma)
            ->orderBy('nome')
            ->get(['id', 'nome', 'cgm']);
    }

    public function getProgressoProperty(): array
    {
        $pautas = $this->pautasDisponiveis;
        $alunos = $this->alunosDaTurma;

        $total = $pautas->count() * $alunos->count();
        $preenchidas = 0;

        foreach ($pautas as $pauta) {
            foreach ($alunos as $aluno) {
                $alternativaId = (int) ($this->respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? 0);

                if ($alternativaId > 0) {
                    $preenchidas++;
                }
            }
        }

        return [
            'preenchidas' => $preenchidas,
            'total' => $total,
        ];
    }

    private function sincronizarVinculosProfessor(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $this->professorIds = $user->professores()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($this->professorIds === []) {
            return;
        }

        $vinculos = TurmaComponenteProfessor::query()
            ->whereIn('professor_id', $this->professorIds)
            ->get(['turma_id', 'componente_curricular_id', 'professor_id']);

        $this->componentesPorTurma = $vinculos
            ->groupBy('turma_id')
            ->map(function (Collection $items): array {
                return $items->pluck('componente_curricular_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
            })
            ->toArray();

        $this->professorPorTurma = $vinculos
            ->groupBy('turma_id')
            ->map(fn (Collection $items): int => (int) $items->pluck('professor_id')->first())
            ->toArray();
    }

    private function filtrarTurmasDaAvaliacao(Avaliacao $avaliacao): Collection
    {
        return $avaliacao->turmas
            ->filter(function ($turma) use ($avaliacao): bool {
                $componentesProfessor = $this->componentesPorTurma[(int) $turma->id] ?? [];

                if ($componentesProfessor === []) {
                    return false;
                }

                return $avaliacao->pautas->contains(
                    fn (Pauta $pauta): bool => $this->pautaEhRelevanteParaComponentes($pauta, $componentesProfessor)
                        && $pauta->alternativas->isNotEmpty()
                );
            })
            ->values();
    }

    private function pautaEhRelevanteParaComponentes(Pauta $pauta, array $componentesProfessor): bool
    {
        if (is_null($pauta->componente_curricular_id)) {
            return true;
        }

        return in_array((int) $pauta->componente_curricular_id, $componentesProfessor, true);
    }

    private function carregarRespostas(): void
    {
        $pautas = $this->pautasDisponiveis;
        $alunos = $this->alunosDaTurma;

        if ($pautas->isEmpty() || $alunos->isEmpty()) {
            $this->respostas = [];
            $this->avaliacaoEmMassa = [];

            return;
        }

        $respostasExistentes = AvaliacaoResposta::query()
            ->where('avaliacao_id', (int) $this->avaliacao)
            ->where('turma_id', (int) $this->turma)
            ->whereIn('pauta_id', $pautas->pluck('id')->all())
            ->whereIn('aluno_id', $alunos->pluck('id')->all())
            ->get()
            ->keyBy(fn (AvaliacaoResposta $resposta): string => $resposta->pauta_id . '-' . $resposta->aluno_id);

        $respostas = [];
        $avaliacaoEmMassa = [];

        foreach ($pautas as $pauta) {
            $avaliacaoEmMassa[$pauta->id] = null;

            foreach ($alunos as $aluno) {
                $chave = $pauta->id . '-' . $aluno->id;
                $resposta = $respostasExistentes->get($chave);

                $respostas[$pauta->id][$aluno->id] = [
                    'alternativa_id' => $resposta?->alternativa_id,
                    'observacao' => $resposta?->observacao,
                ];
            }
        }

        $this->respostas = $respostas;
        $this->avaliacaoEmMassa = $avaliacaoEmMassa;
    }

    private function professorIdDaTurma(int $turmaId): ?int
    {
        $professorId = $this->professorPorTurma[$turmaId] ?? null;

        return $professorId ? (int) $professorId : null;
    }
}
