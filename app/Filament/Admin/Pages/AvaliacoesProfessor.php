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

    protected static ?string $navigationLabel = 'Minhas Avaliações';

    protected static ?string $slug = 'avaliacoes-professor';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public ?int $avaliacao = null;

    public ?int $turma = null;

    public array $respostas = [];

    public array $avaliacaoEmMassa = [];

    public array $pautasExpandidas = [];

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
    }

    public function updatedAvaliacao(): void
    {
        $this->turma = null;
        $this->respostas = [];
        $this->avaliacaoEmMassa = [];
        $this->pautasExpandidas = [];
    }

    public function updatedTurma(): void
    {
        $this->respostas = [];
        $this->avaliacaoEmMassa = [];
        $this->pautasExpandidas = [];

        if ($this->avaliacao && $this->turma) {
            $this->carregarRespostas();
        }
    }

    public function alternarPauta(int $pautaId): void
    {
        $indice = array_search($pautaId, $this->pautasExpandidas, true);

        if ($indice !== false) {
            unset($this->pautasExpandidas[$indice]);
            $this->pautasExpandidas = array_values($this->pautasExpandidas);

            return;
        }

        $this->pautasExpandidas[] = $pautaId;
    }

    public function pautaEstaExpandida(int $pautaId): bool
    {
        return in_array($pautaId, $this->pautasExpandidas, true);
    }

    public function updated(string $name): void
    {
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

        $this->autoSalvarResposta((int) $pautaId, (int) $alunoId);
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
        $alternativa = $pauta->alternativas->firstWhere('id', $alternativaId);

        if (! $alternativa) {
            Notification::make()
                ->title('Selecione uma alternativa válida para aplicar em massa.')
                ->warning()
                ->send();

            return;
        }

        foreach ($this->alunosDaTurma as $aluno) {
            $this->respostas[$pautaId][$aluno->id]['alternativa_id'] = $alternativaId;
        }

        $payload = [];
        $alunosComPendencia = [];
        $professorId = $this->professorIdDaTurma((int) $this->turma);
        $agora = now();

        foreach ($this->alunosDaTurma as $aluno) {
            $observacaoInformada = trim((string) ($this->respostas[$pautaId][$aluno->id]['observacao'] ?? ''));

            if (! $alternativa->tem_observacao) {
                $this->respostas[$pautaId][$aluno->id]['observacao'] = null;
                $observacaoInformada = '';
            }

            if ($alternativa->tem_observacao && $observacaoInformada === '') {
                $alunosComPendencia[] = (int) $aluno->id;
                continue;
            }

            $payload[] = [
                'avaliacao_id' => (int) $this->avaliacao,
                'pauta_id' => (int) $pauta->id,
                'turma_id' => (int) $this->turma,
                'aluno_id' => (int) $aluno->id,
                'professor_id' => $professorId,
                'alternativa_id' => (int) $alternativaId,
                'observacao' => $alternativa->tem_observacao ? $observacaoInformada : null,
                'respondido_em' => $agora,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        DB::transaction(function () use ($payload, $alunosComPendencia, $pautaId): void {
            if ($payload !== []) {
                AvaliacaoResposta::query()->upsert(
                    $payload,
                    ['avaliacao_id', 'pauta_id', 'turma_id', 'aluno_id'],
                    ['professor_id', 'alternativa_id', 'observacao', 'respondido_em', 'updated_at']
                );
            }

            if ($alunosComPendencia !== []) {
                AvaliacaoResposta::query()
                    ->where('avaliacao_id', (int) $this->avaliacao)
                    ->where('pauta_id', (int) $pautaId)
                    ->where('turma_id', (int) $this->turma)
                    ->whereIn('aluno_id', $alunosComPendencia)
                    ->delete();
            }
        });

        $mensagemPendencia = count($alunosComPendencia) > 0
            ? count($alunosComPendencia) . ' aluno(s) ainda precisam preencher observação para concluir o salvamento.'
            : null;

        Notification::make()
            ->title('Alternativa aplicada para toda a turma nesta pauta.')
            ->body($mensagemPendencia ?? ($alternativa->tem_observacao ? 'Essa alternativa exige observação por aluno.' : 'Respostas salvas automaticamente.'))
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
        $faltandoObservacao = 0;
        $payload = [];
        $professorId = $this->professorIdDaTurma((int) $this->turma);
        $agora = now();

        foreach ($pautas as $pauta) {
            foreach ($alunos as $aluno) {
                $alternativaId = (int) ($this->respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? 0);
                $alternativa = $pauta->alternativas->firstWhere('id', $alternativaId);

                if (! $alternativa) {
                    $faltandoResposta++;
                    continue;
                }

                $observacaoInformada = trim((string) ($this->respostas[$pauta->id][$aluno->id]['observacao'] ?? ''));

                if ($alternativa->tem_observacao && $observacaoInformada === '') {
                    $faltandoObservacao++;
                    continue;
                }

                $observacao = $alternativa->tem_observacao && $observacaoInformada !== ''
                    ? $observacaoInformada
                    : null;

                $payload[] = [
                    'avaliacao_id' => (int) $this->avaliacao,
                    'pauta_id' => (int) $pauta->id,
                    'turma_id' => (int) $this->turma,
                    'aluno_id' => (int) $aluno->id,
                    'professor_id' => $professorId,
                    'alternativa_id' => $alternativaId,
                    'observacao' => $observacao,
                    'respondido_em' => $agora,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
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

    public function alternativaRequerObservacao(int $pautaId, ?int $alternativaId): bool
    {
        if (! $alternativaId) {
            return false;
        }

        $pauta = $this->pautasDisponiveis->firstWhere('id', $pautaId);

        if (! $pauta) {
            return false;
        }

        $alternativa = $pauta->alternativas->firstWhere('id', (int) $alternativaId);

        return (bool) ($alternativa?->tem_observacao ?? false);
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
                if ($this->respostaEstaCompleta($pauta, (int) $aluno->id)) {
                    $preenchidas++;
                }
            }
        }

        return [
            'preenchidas' => $preenchidas,
            'total' => $total,
        ];
    }

    public function getProgressoPorPautaProperty(): array
    {
        $pautas = $this->pautasDisponiveis;
        $alunos = $this->alunosDaTurma;

        if ($pautas->isEmpty() || $alunos->isEmpty()) {
            return [];
        }

        $progresso = [];

        foreach ($pautas as $pauta) {
            $total = $alunos->count();
            $preenchidas = 0;

            foreach ($alunos as $aluno) {
                if ($this->respostaEstaCompleta($pauta, (int) $aluno->id)) {
                    $preenchidas++;
                }
            }

            $percentual = $total > 0
                ? min(100, (int) round(($preenchidas / $total) * 100))
                : 0;

            $progresso[$pauta->id] = [
                'preenchidas' => $preenchidas,
                'total' => $total,
                'percentual' => $percentual,
                'concluida' => $total > 0 && $preenchidas === $total,
            ];
        }

        return $progresso;
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

    private function autoSalvarResposta(int $pautaId, int $alunoId): void
    {
        if (! $this->avaliacao || ! $this->turma) {
            return;
        }

        $pauta = $this->pautasDisponiveis->firstWhere('id', $pautaId);

        if (! $pauta) {
            return;
        }

        $alunoDaTurma = $this->alunosDaTurma->firstWhere('id', $alunoId);

        if (! $alunoDaTurma) {
            return;
        }

        $alternativaId = (int) ($this->respostas[$pautaId][$alunoId]['alternativa_id'] ?? 0);
        $alternativa = $pauta->alternativas->firstWhere('id', $alternativaId);

        if (! $alternativa) {
            $this->removerRespostaPersistida($pautaId, $alunoId);

            return;
        }

        $observacaoInformada = trim((string) ($this->respostas[$pautaId][$alunoId]['observacao'] ?? ''));

        if (! $alternativa->tem_observacao) {
            if (($this->respostas[$pautaId][$alunoId]['observacao'] ?? null) !== null) {
                $this->respostas[$pautaId][$alunoId]['observacao'] = null;
            }

            $observacaoInformada = '';
        }

        if ($alternativa->tem_observacao && $observacaoInformada === '') {
            $this->removerRespostaPersistida($pautaId, $alunoId);

            return;
        }

        $agora = now();
        $professorId = $this->professorIdDaTurma((int) $this->turma);

        AvaliacaoResposta::query()->upsert(
            [[
                'avaliacao_id' => (int) $this->avaliacao,
                'pauta_id' => $pautaId,
                'turma_id' => (int) $this->turma,
                'aluno_id' => $alunoId,
                'professor_id' => $professorId,
                'alternativa_id' => $alternativaId,
                'observacao' => $alternativa->tem_observacao ? $observacaoInformada : null,
                'respondido_em' => $agora,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]],
            ['avaliacao_id', 'pauta_id', 'turma_id', 'aluno_id'],
            ['professor_id', 'alternativa_id', 'observacao', 'respondido_em', 'updated_at']
        );
    }

    private function removerRespostaPersistida(int $pautaId, int $alunoId): void
    {
        if (! $this->avaliacao || ! $this->turma) {
            return;
        }

        AvaliacaoResposta::query()
            ->where('avaliacao_id', (int) $this->avaliacao)
            ->where('pauta_id', $pautaId)
            ->where('turma_id', (int) $this->turma)
            ->where('aluno_id', $alunoId)
            ->delete();
    }

    private function respostaEstaCompleta(Pauta $pauta, int $alunoId): bool
    {
        $alternativaId = (int) ($this->respostas[$pauta->id][$alunoId]['alternativa_id'] ?? 0);

        if ($alternativaId <= 0) {
            return false;
        }

        $alternativa = $pauta->alternativas->firstWhere('id', $alternativaId);

        if (! $alternativa) {
            return false;
        }

        if (! $alternativa->tem_observacao) {
            return true;
        }

        $observacao = trim((string) ($this->respostas[$pauta->id][$alunoId]['observacao'] ?? ''));

        return $observacao !== '';
    }
}
