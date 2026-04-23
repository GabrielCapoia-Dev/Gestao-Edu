<?php

namespace App\Filament\Admin\Pages;

use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoInformacaoComplementar;
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
    private const PERMISSAO_LISTAR_AVALIACOES = 'Listar Avaliações';
    private const PERMISSAO_RESPONDER_AVALIACOES = 'Responder Avaliações';
    private const PERMISSAO_EXPORTAR_AVALIACOES = 'Exportar Avaliações';

    protected string $view = 'filament.pages.avaliacoes-professor';

    protected static ?string $title = 'Avaliações';

    protected static ?string $navigationLabel = 'Minhas Avaliações';

    protected static ?string $slug = 'avaliacoes-professor';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public ?int $avaliacao = null;

    public ?int $turma = null;

    public array $respostas = [];

    public array $informacoesComplementares = [];

    public array $alternativasPorPauta = [];

    public array $avaliacaoEmMassa = [];

    public array $pautasExpandidas = [];

    public array $professorIds = [];

    public array $componentesPorTurma = [];

    public array $professorPorTurma = [];

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->hasAnyPermission([
            self::PERMISSAO_LISTAR_AVALIACOES,
            self::PERMISSAO_RESPONDER_AVALIACOES,
            self::PERMISSAO_EXPORTAR_AVALIACOES,
        ]);
    }

    public function podeResponder(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user?->hasPermissionTo(self::PERMISSAO_RESPONDER_AVALIACOES) ?? false;
    }

    public function podeExportar(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user?->hasPermissionTo(self::PERMISSAO_EXPORTAR_AVALIACOES) ?? false;
    }

    private function deveFiltrarPorProfessor(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($this->professorIds === []) {
            return false;
        }

        if ($user->hasPermissionTo(self::PERMISSAO_LISTAR_AVALIACOES)) {
            return false;
        }

        return true;
    }

    private function abortSeNaoPuderResponder(): void
    {
        abort_unless($this->podeResponder(), 403);
    }

    private function abortSeNaoPuderExportar(): void
    {
        abort_unless($this->podeExportar(), 403);
    }

    public function mount(): void
    {
        $this->sincronizarVinculosProfessor();

        $avaliacaoQuery = $this->normalizarQueryId(request()->query('avaliacao'));
        $turmaQuery = $this->normalizarQueryId(request()->query('turma'));

        if ($avaliacaoQuery) {
            $this->avaliacao = $avaliacaoQuery;
        }

        if ($this->avaliacao && ! $this->avaliacoesDisponiveis->contains('id', (int) $this->avaliacao)) {
            $this->avaliacao = null;
            $this->turma = null;

            return;
        }

        if ($turmaQuery) {
            $this->turma = $turmaQuery;
        }

        if ($this->turma && ! $this->turmasDisponiveis->contains('id', (int) $this->turma)) {
            $this->turma = null;
        }

        if ($this->avaliacao && $this->turma) {
            $this->carregarRespostas();
            $this->carregarInformacoesComplementares();
        }
    }

    public function updatedAvaliacao(): void
    {
        $this->turma = null;
        $this->respostas = [];
        $this->informacoesComplementares = [];
        $this->alternativasPorPauta = [];
        $this->avaliacaoEmMassa = [];
        $this->pautasExpandidas = [];
    }

    public function updatedTurma(): void
    {
        $this->respostas = [];
        $this->informacoesComplementares = [];
        $this->alternativasPorPauta = [];
        $this->avaliacaoEmMassa = [];
        $this->pautasExpandidas = [];

        if ($this->avaliacao && $this->turma) {
            $this->carregarRespostas();
            $this->carregarInformacoesComplementares();
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
        if (str_starts_with($name, 'informacoesComplementares.')) {
            $partes = explode('.', $name);
            $alunoId = $partes[1] ?? null;

            if (is_numeric($alunoId)) {
                $this->autoSalvarInformacaoComplementar((int) $alunoId);
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

        $this->autoSalvarResposta((int) $pautaId, (int) $alunoId);
    }

    public function aplicarEmMassa(int $pautaId): void
    {
        $this->abortSeNaoPuderResponder();

        $pauta = $this->pautasDisponiveis->firstWhere('id', $pautaId);

        if (! $pauta) {
            Notification::make()
                ->title('Pauta não encontrada para esta turma.')
                ->warning()
                ->send();

            return;
        }

        $alternativaId = (int) ($this->avaliacaoEmMassa[$pautaId] ?? 0);
        $alternativa = $this->alternativaDaPauta((int) $pauta->id, $alternativaId);

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
            $temObservacao = (bool) ($alternativa['tem_observacao'] ?? false);

            if (! $temObservacao) {
                $this->respostas[$pautaId][$aluno->id]['observacao'] = null;
                $observacaoInformada = '';
            }

            if ($temObservacao && $observacaoInformada === '') {
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
                'observacao' => $temObservacao ? $observacaoInformada : null,
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
            ->body(
                $mensagemPendencia
                ?? ((bool) ($alternativa['tem_observacao'] ?? false)
                    ? 'Essa alternativa exige observação por aluno.'
                    : 'Respostas salvas automaticamente.')
            )
            ->success()
            ->send();
    }

    public function salvarRespostas(): void
    {
        $this->abortSeNaoPuderResponder();

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
                $alternativa = $this->alternativaDaPauta((int) $pauta->id, $alternativaId);

                if (! $alternativa) {
                    $faltandoResposta++;
                    continue;
                }

                $observacaoInformada = trim((string) ($this->respostas[$pauta->id][$aluno->id]['observacao'] ?? ''));
                $temObservacao = (bool) ($alternativa['tem_observacao'] ?? false);

                if ($temObservacao && $observacaoInformada === '') {
                    $faltandoObservacao++;
                    continue;
                }

                $observacao = $temObservacao && $observacaoInformada !== ''
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

    public function exportarRespostas()
    {
        $this->abortSeNaoPuderExportar();

        if (! $this->avaliacaoAtual || ! $this->turma) {
            Notification::make()
                ->title('Selecione uma avaliação e uma turma para exportar.')
                ->warning()
                ->send();

            return null;
        }

        $turmaAtual = $this->turmasDisponiveis->firstWhere('id', (int) $this->turma);
        $pautas = $this->pautasDisponiveis;
        $alunos = $this->alunosDaTurma;

        if (! $turmaAtual || $pautas->isEmpty() || $alunos->isEmpty()) {
            Notification::make()
                ->title('Não há dados suficientes para exportação (turma/pautas/alunos).')
                ->warning()
                ->send();

            return null;
        }

        $pautasIds = $pautas->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $alunosIds = $alunos->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $respostas = AvaliacaoResposta::query()
            ->where('avaliacao_id', (int) $this->avaliacaoAtual->id)
            ->where('turma_id', (int) $turmaAtual->id)
            ->whereIn('pauta_id', $pautasIds)
            ->whereIn('aluno_id', $alunosIds)
            ->with(['alternativa:id,nome'])
            ->get()
            ->keyBy(fn (AvaliacaoResposta $resposta): string => $resposta->pauta_id . '-' . $resposta->aluno_id);

        $informacoesComplementares = AvaliacaoInformacaoComplementar::query()
            ->where('avaliacao_id', (int) $this->avaliacaoAtual->id)
            ->where('turma_id', (int) $turmaAtual->id)
            ->whereIn('aluno_id', $alunosIds)
            ->get(['aluno_id', 'informacoes_complementares'])
            ->keyBy('aluno_id');

        $avaliacaoId = (int) $this->avaliacaoAtual->id;
        $turmaId = (int) $turmaAtual->id;
        $avaliacaoNome = (string) $this->avaliacaoAtual->nome;
        $turmaNome = (string) ($turmaAtual->nome ?? '');

        $nomeArquivo = sprintf(
            'avaliacao_%d_turma_%d_%s.csv',
            $avaliacaoId,
            $turmaId,
            now()->format('Ymd_His')
        );

        return response()->streamDownload(function () use ($avaliacaoId, $avaliacaoNome, $turmaAtual, $turmaId, $turmaNome, $pautas, $alunos, $respostas, $informacoesComplementares): void {
            echo "\xEF\xBB\xBF";

            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            $delimiter = ';';

            fputcsv($out, [
                'Avaliacao ID',
                'Avaliacao Nome',
                'Turma ID',
                'Turma Nome',
                'Aluno ID',
                'Aluno Nome',
                'Aluno CGM',
                'Pauta ID',
                'Pauta Texto',
                'Componente',
                'Alternativa ID',
                'Alternativa',
                'Observacao',
                'Respondido Em',
                'Professor ID',
                'Informacoes Complementares (Aluno)',
            ], $delimiter);

            foreach ($alunos as $aluno) {
                foreach ($pautas as $pauta) {
                    $chave = $pauta->id . '-' . $aluno->id;
                    $resposta = $respostas->get($chave);
                    $alternativa = $resposta?->alternativa;
                    $info = $informacoesComplementares->get((int) $aluno->id);

                    fputcsv($out, [
                        $avaliacaoId,
                        $avaliacaoNome,
                        $turmaId,
                        $turmaNome,
                        (int) $aluno->id,
                        (string) $aluno->nome,
                        (string) $aluno->cgm,
                        (int) $pauta->id,
                        (string) $pauta->texto,
                        (string) ($pauta->componente?->nome ?? ''),
                        $resposta?->alternativa_id ? (int) $resposta->alternativa_id : '',
                        (string) ($alternativa?->nome ?? ''),
                        (string) ($resposta?->observacao ?? ''),
                        $resposta?->respondido_em?->toDateTimeString() ?? '',
                        $resposta?->professor_id ? (int) $resposta->professor_id : '',
                        (string) ($info?->informacoes_complementares ?? ''),
                    ], $delimiter);
                }
            }

            fclose($out);
        }, $nomeArquivo, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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

    public function getAvaliacoesDisponiveisProperty(): Collection
    {
        $avaliacoesQuery = Avaliacao::query()
            ->pendentesParaData(now());

        if ($this->deveFiltrarPorProfessor()) {
            $avaliacoesQuery->whereHas('turmas.componentes', function ($query) {
                $query->whereIn('turma_componente_professor.professor_id', $this->professorIds);
            });
        }

        $avaliacoes = $avaliacoesQuery
            ->with([
                'tipo' => fn ($query) => $query
                    ->with([
                        'alternativas' => fn ($alternativas) => $alternativas->where('status', true),
                    ]),
                'pautas' => fn ($query) => $query
                    ->where('status', true)
                    ->with([
                        'componente:id,nome',
                        'tipo' => fn ($tipoQuery) => $tipoQuery
                            ->with([
                                'alternativas' => fn ($alternativas) => $alternativas->where('status', true),
                            ]),
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

        $pautas = $this->avaliacaoAtual->pautas;

        if ($this->deveFiltrarPorProfessor()) {
            $componentesProfessor = $this->componentesPorTurma[(int) $this->turma] ?? [];

            $pautas = $pautas
                ->filter(fn (Pauta $pauta): bool => $this->pautaEhRelevanteParaComponentes($pauta, $componentesProfessor))
                ->values();
        } else {
            $pautas = $pautas->values();
        }

        $this->carregarAlternativasPorPauta($pautas);

        return $pautas
            ->filter(fn (Pauta $pauta): bool => $this->alternativasDaPauta((int) $pauta->id) !== [])
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
        if (! $this->deveFiltrarPorProfessor()) {
            return $avaliacao->turmas->values();
        }

        return $avaliacao->turmas
            ->filter(function ($turma) use ($avaliacao): bool {
                $componentesProfessor = $this->componentesPorTurma[(int) $turma->id] ?? [];

                if ($componentesProfessor === []) {
                    return false;
                }

                return $avaliacao->pautas->contains(
                    fn (Pauta $pauta): bool => $this->pautaEhRelevanteParaComponentes($pauta, $componentesProfessor)
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
            $this->alternativasPorPauta = [];

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

    private function carregarAlternativasPorPauta(Collection $pautas): void
    {
        $this->alternativasPorPauta = [];

        if (! $this->avaliacaoAtual || $pautas->isEmpty()) {
            return;
        }

        $pautasIds = $pautas->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $overrides = DB::table('avaliacao_pauta_alternativa')
            ->where('avaliacao_id', (int) $this->avaliacaoAtual->id)
            ->whereIn('pauta_id', $pautasIds)
            ->get(['pauta_id', 'alternativa_id'])
            ->groupBy('pauta_id')
            ->map(fn (Collection $rows): array => $rows->pluck('alternativa_id')->map(fn ($id) => (int) $id)->unique()->values()->all());

        $overridesAlternativasIds = $overrides
            ->flatten(1)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $alternativasOverride = Alternativa::query()
            ->whereIn('id', $overridesAlternativasIds)
            ->where('status', true)
            ->orderBy('nome')
            ->get()
            ->keyBy('id');

        $alternativasTipoAvaliacao = $this->avaliacaoAtual->tipo?->alternativas
            ? $this->avaliacaoAtual->tipo->alternativas->where('status', true)->values()
            : collect();

        foreach ($pautas as $pauta) {
            $pautaId = (int) $pauta->id;
            $alternativas = collect();

            $overrideIds = $overrides->get($pautaId, []);

            if ($overrideIds !== []) {
                $alternativas = collect($overrideIds)
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
                $alternativas = $alternativasTipoAvaliacao;
            }

            if ($alternativas->isEmpty()) {
                $alternativas = $pauta->alternativas->where('status', true)->values();
            }

            $this->alternativasPorPauta[$pautaId] = $alternativas
                ->map(fn (Alternativa $alternativa): array => [
                    'id' => (int) $alternativa->id,
                    'nome' => (string) $alternativa->nome,
                    'tem_observacao' => (bool) $alternativa->tem_observacao,
                ])
                ->values()
                ->all();
        }
    }

    private function carregarInformacoesComplementares(): void
    {
        if (! $this->avaliacao || ! $this->turma) {
            $this->informacoesComplementares = [];

            return;
        }

        $alunosIds = $this->alunosDaTurma
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($alunosIds === []) {
            $this->informacoesComplementares = [];

            return;
        }

        $registros = AvaliacaoInformacaoComplementar::query()
            ->where('avaliacao_id', (int) $this->avaliacao)
            ->where('turma_id', (int) $this->turma)
            ->whereIn('aluno_id', $alunosIds)
            ->get(['aluno_id', 'informacoes_complementares'])
            ->keyBy('aluno_id');

        $informacoes = [];

        foreach ($alunosIds as $alunoId) {
            $informacoes[$alunoId] = (string) ($registros->get($alunoId)?->informacoes_complementares ?? '');
        }

        $this->informacoesComplementares = $informacoes;
    }

    private function professorIdDaTurma(int $turmaId): ?int
    {
        $professorId = $this->professorPorTurma[$turmaId] ?? null;

        return $professorId ? (int) $professorId : null;
    }

    private function alternativaDaPauta(int $pautaId, ?int $alternativaId): ?array
    {
        if (! $alternativaId) {
            return null;
        }

        return collect($this->alternativasDaPauta($pautaId))
            ->first(fn (array $alternativa): bool => (int) ($alternativa['id'] ?? 0) === (int) $alternativaId);
    }

    private function autoSalvarResposta(int $pautaId, int $alunoId): void
    {
        $this->abortSeNaoPuderResponder();

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
        $alternativa = $this->alternativaDaPauta($pautaId, $alternativaId);

        if (! $alternativa) {
            $this->removerRespostaPersistida($pautaId, $alunoId);

            return;
        }

        $observacaoInformada = trim((string) ($this->respostas[$pautaId][$alunoId]['observacao'] ?? ''));
        $temObservacao = (bool) ($alternativa['tem_observacao'] ?? false);

        if (! $temObservacao) {
            if (($this->respostas[$pautaId][$alunoId]['observacao'] ?? null) !== null) {
                $this->respostas[$pautaId][$alunoId]['observacao'] = null;
            }

            $observacaoInformada = '';
        }

        if ($temObservacao && $observacaoInformada === '') {
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
                'observacao' => $temObservacao ? $observacaoInformada : null,
                'respondido_em' => $agora,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]],
            ['avaliacao_id', 'pauta_id', 'turma_id', 'aluno_id'],
            ['professor_id', 'alternativa_id', 'observacao', 'respondido_em', 'updated_at']
        );
    }

    private function autoSalvarInformacaoComplementar(int $alunoId): void
    {
        $this->abortSeNaoPuderResponder();

        if (! $this->avaliacao || ! $this->turma) {
            return;
        }

        $alunoDaTurma = $this->alunosDaTurma->firstWhere('id', $alunoId);

        if (! $alunoDaTurma) {
            return;
        }

        $informacoes = trim((string) ($this->informacoesComplementares[$alunoId] ?? ''));

        if ($informacoes === '') {
            AvaliacaoInformacaoComplementar::query()
                ->where('avaliacao_id', (int) $this->avaliacao)
                ->where('turma_id', (int) $this->turma)
                ->where('aluno_id', $alunoId)
                ->delete();

            return;
        }

        AvaliacaoInformacaoComplementar::query()->upsert(
            [[
                'avaliacao_id' => (int) $this->avaliacao,
                'turma_id' => (int) $this->turma,
                'aluno_id' => $alunoId,
                'professor_id' => $this->professorIdDaTurma((int) $this->turma),
                'informacoes_complementares' => $informacoes,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['avaliacao_id', 'turma_id', 'aluno_id'],
            ['professor_id', 'informacoes_complementares', 'updated_at']
        );
    }

    private function removerRespostaPersistida(int $pautaId, int $alunoId): void
    {
        $this->abortSeNaoPuderResponder();

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

        $alternativa = $this->alternativaDaPauta((int) $pauta->id, $alternativaId);

        if (! $alternativa) {
            return false;
        }

        if (! ((bool) ($alternativa['tem_observacao'] ?? false))) {
            return true;
        }

        $observacao = trim((string) ($this->respostas[$pauta->id][$alunoId]['observacao'] ?? ''));

        return $observacao !== '';
    }

    private function normalizarQueryId(mixed $valor): ?int
    {
        $id = (int) $valor;

        return $id > 0 ? $id : null;
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
