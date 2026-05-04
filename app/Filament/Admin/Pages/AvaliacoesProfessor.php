<?php

namespace App\Filament\Admin\Pages;

use App\Models\Alternativa;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\AvaliacaoInformacaoComplementar;
use App\Models\AvaliacaoResposta;
use App\Models\Pauta;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
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
    private const LIMITE_CARACTERES_TEXTO = 1500;

    private const PERMISSAO_LISTAR_AVALIACOES = 'Listar Avaliações';

    private const PERMISSAO_RESPONDER_AVALIACOES = 'Responder Avaliações';

    private const PERMISSAO_EXPORTAR_AVALIACOES = 'Exportar Avaliações';

    protected string $view = 'filament.pages.avaliacoes-professor';

    protected static ?string $title = 'Avaliações';

    protected static ?string $navigationLabel = 'Minhas Avaliações';

    protected static ?string $slug = 'avaliacoes-professor';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public ?int $avaliacao = null;

    public ?int $serie = null;

    public ?int $escola = null;

    public ?string $serieEscola = null;

    public ?int $turma = null;

    public array $respostas = [];

    public array $informacoesComplementares = [];

    public array $alternativasPorPauta = [];

    public array $avaliacaoEmMassa = [];

    public ?int $avaliacaoEmMassaGlobal = null;

    public ?int $turmaEmMassaGlobal = null;

    public array $pautasExpandidas = [];

    public array $alunosExpandidos = [];

    public array $turmasExpandidas = [];

    public string $visualizacao = 'pautas';

    public array $professorIds = [];

    public array $componentesPorTurma = [];

    public array $professorPorTurma = [];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
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
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasPermissionTo(self::PERMISSAO_RESPONDER_AVALIACOES) ?? false;
    }

    public function podeExportar(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasPermissionTo(self::PERMISSAO_EXPORTAR_AVALIACOES) ?? false;
    }

    private function deveFiltrarPorProfessor(): bool
    {
        /** @var User|null $user */
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
        $serieQuery = $this->normalizarQueryId(request()->query('serie'));
        $escolaQuery = $this->normalizarQueryId(request()->query('escola'));
        $turmaQuery = $this->normalizarQueryId(request()->query('turma'));

        if ($avaliacaoQuery) {
            $this->avaliacao = $avaliacaoQuery;
        }

        if ($this->avaliacao && ! $this->avaliacoesDisponiveis->contains('id', (int) $this->avaliacao)) {
            $this->avaliacao = null;
            $this->serie = null;
            $this->escola = null;
            $this->serieEscola = null;
            $this->turma = null;

            return;
        }

        if ($serieQuery) {
            $this->serie = $serieQuery;
            $this->escola = $escolaQuery ?: $this->escolaDaSerie($serieQuery);
            $this->sincronizarSerieEscola();
        } elseif ($turmaQuery) {
            $this->turma = $turmaQuery;
            $this->serie = $this->serieDaTurma($turmaQuery);
            $this->escola = $this->escolaDaTurma($turmaQuery);
            $this->sincronizarSerieEscola();
        }

        if ($this->serie && ! $this->escopoSerieSelecionadoEhValido()) {
            $this->serie = null;
            $this->escola = null;
            $this->serieEscola = null;
            $this->turma = null;
        }

        if ($this->avaliacao && $this->serie) {
            $this->carregarRespostas();
            $this->carregarInformacoesComplementares();
        }
    }

    public function updatedAvaliacao(): void
    {
        $this->serie = null;
        $this->escola = null;
        $this->serieEscola = null;
        $this->turma = null;
        $this->limparDadosDoEscopo(true);
    }

    public function updatedSerieEscola(): void
    {
        $this->turma = null;
        $this->aplicarSerieEscolaSelecionada();
        $this->limparDadosDoEscopo(true);

        if (! $this->escopoSerieSelecionadoEhValido()) {
            $this->serie = null;
            $this->escola = null;
            $this->serieEscola = null;

            return;
        }

        if ($this->avaliacao && $this->serie) {
            $this->carregarRespostas();
            $this->carregarInformacoesComplementares();
        }
    }

    public function updatedSerie(): void
    {
        $this->turma = null;
        $this->escola = $this->serie ? $this->escolaDaSerie((int) $this->serie) : null;
        $this->sincronizarSerieEscola();
        $this->limparDadosDoEscopo(true);

        if ($this->serie && ! $this->escopoSerieSelecionadoEhValido()) {
            $this->serie = null;
            $this->escola = null;
            $this->serieEscola = null;

            return;
        }

        if ($this->avaliacao && $this->serie) {
            $this->carregarRespostas();
            $this->carregarInformacoesComplementares();
        }
    }

    public function updatedTurma(): void
    {
        $this->serie = $this->turma ? $this->serieDaTurma((int) $this->turma) : null;
        $this->escola = $this->turma ? $this->escolaDaTurma((int) $this->turma) : null;
        $this->sincronizarSerieEscola();
        $this->limparDadosDoEscopo(true);

        if ($this->avaliacao && $this->serie) {
            $this->carregarRespostas();
            $this->carregarInformacoesComplementares();
        }
    }

    private function limparDadosDoEscopo(bool $limparTurmasExpandidas = false): void
    {
        $this->respostas = [];
        $this->informacoesComplementares = [];
        $this->alternativasPorPauta = [];
        $this->avaliacaoEmMassa = [];
        $this->avaliacaoEmMassaGlobal = null;
        $this->turmaEmMassaGlobal = null;
        $this->pautasExpandidas = [];
        $this->alunosExpandidos = [];

        if ($limparTurmasExpandidas) {
            $this->turmasExpandidas = [];
        }
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
            $partes = explode('.', $name);
            $componenteId = $partes[1] ?? null;
            $alunoId = $partes[2] ?? null;

            if (is_numeric($componenteId) && is_numeric($alunoId)) {
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

        $this->autoSalvarResposta((int) $pautaId, (int) $alunoId);
    }

    public function aplicarEmMassa(int $turmaId, int $pautaId): void
    {
        $this->abortSeNaoPuderResponder();

        $pauta = $this->pautasDaTurma($turmaId)->firstWhere('id', $pautaId);

        if (! $pauta) {
            Notification::make()
                ->title('Pauta não encontrada para esta turma.')
                ->warning()
                ->send();

            return;
        }

        $alternativaId = (int) ($this->avaliacaoEmMassa[$turmaId][$pautaId] ?? 0);
        $alternativa = $this->alternativaDaPauta((int) $pauta->id, $alternativaId);

        if (! $alternativa) {
            Notification::make()
                ->title('Selecione uma alternativa válida para aplicar em massa.')
                ->warning()
                ->send();

            return;
        }

        $alunos = $this->alunosDaTurma($turmaId);

        foreach ($alunos as $aluno) {
            $this->respostas[$pautaId][$aluno->id]['alternativa_id'] = $alternativaId;
        }

        $payload = [];
        $alunosComPendencia = [];
        $professorId = $this->professorIdDaTurma($turmaId);
        $agora = now();

        foreach ($alunos as $aluno) {
            $observacaoInformada = $this->limitarTextoCampo($this->respostas[$pautaId][$aluno->id]['observacao'] ?? '');
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
                'turma_id' => $turmaId,
                'aluno_id' => (int) $aluno->id,
                'professor_id' => $professorId,
                'alternativa_id' => (int) $alternativaId,
                'observacao' => $temObservacao ? $observacaoInformada : null,
                'respondido_em' => $agora,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        DB::transaction(function () use ($payload, $alunosComPendencia, $pautaId, $turmaId): void {
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
                    ->where('turma_id', $turmaId)
                    ->whereIn('aluno_id', $alunosComPendencia)
                    ->delete();
            }
        });

        $mensagemPendencia = count($alunosComPendencia) > 0
            ? count($alunosComPendencia).' aluno(s) ainda precisam preencher observação para concluir o salvamento.'
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

    public function aplicarEmMassaNaSerie(): void
    {
        $this->abortSeNaoPuderResponder();

        if (! $this->avaliacao || ! $this->serie) {
            Notification::make()
                ->title('Selecione uma avaliacao e uma serie para aplicar em massa.')
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
        $totalAplicado = 0;
        $totalIgnoradoPorPreenchimento = 0;
        $pautasIgnoradas = 0;
        $agora = now();

        $turmasAlvo = $this->turmasAlvoAvaliacaoEmMassa();

        if ($turmasAlvo->isEmpty()) {
            Notification::make()
                ->title('Selecione uma turma valida para aplicar em massa.')
                ->warning()
                ->send();

            return;
        }

        foreach ($turmasAlvo as $turma) {
            $turmaId = (int) $turma->id;
            $professorId = $this->professorIdDaTurma($turmaId);

            foreach ($this->pautasDaTurma($turmaId) as $pauta) {
                $alternativa = $this->alternativaDaPauta((int) $pauta->id, $alternativaId);

                if (! $alternativa) {
                    $pautasIgnoradas++;

                    continue;
                }

                foreach ($this->alunosDaTurma($turmaId) as $aluno) {
                    $alunoId = (int) $aluno->id;

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

                    $payload[] = [
                        'avaliacao_id' => (int) $this->avaliacao,
                        'pauta_id' => (int) $pauta->id,
                        'turma_id' => $turmaId,
                        'aluno_id' => $alunoId,
                        'professor_id' => $professorId,
                        'alternativa_id' => $alternativaId,
                        'observacao' => $temObservacao ? $observacaoInformada : null,
                        'respondido_em' => $agora,
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ];
                    $totalAplicado++;
                }
            }
        }

        if ($payload === [] && $pendencias === []) {
            Notification::make()
                ->title('A alternativa selecionada nao esta disponivel nas pautas desta serie.')
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

        $totalPendencias = collect($pendencias)->flatten()->count();
        $mensagens = [$totalAplicado.' resposta(s) salvas automaticamente.'];

        if ($totalPendencias > 0) {
            $mensagens[] = $totalPendencias.' resposta(s) precisam de observacao obrigatoria.';
        }

        if ($pautasIgnoradas > 0) {
            $mensagens[] = $pautasIgnoradas.' pauta(s) nao possuem esta alternativa.';
        }

        if ($totalIgnoradoPorPreenchimento > 0) {
            $mensagens[] = $totalIgnoradoPorPreenchimento.' resposta(s) com observacao foram mantidas.';
        }

        Notification::make()
            ->title('Avaliacao em massa aplicada.')
            ->body(implode(' ', $mensagens))
            ->success()
            ->send();
    }

    public function salvarRespostas(): void
    {
        $this->abortSeNaoPuderResponder();

        if (! $this->avaliacao || ! $this->serie) {
            Notification::make()
                ->title('Selecione uma avaliacao e uma serie para continuar.')
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
            $professorId = $this->professorIdDaTurma($turmaId);

            foreach ($this->pautasDaTurma($turmaId) as $pauta) {
                foreach ($this->alunosDaTurma($turmaId) as $aluno) {
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

                    $observacao = $temObservacao && $observacaoInformada !== ''
                        ? $observacaoInformada
                        : null;

                    $payload[] = [
                        'avaliacao_id' => (int) $this->avaliacao,
                        'pauta_id' => (int) $pauta->id,
                        'turma_id' => $turmaId,
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

        if (! $this->avaliacaoAtual || ! $this->serie) {
            Notification::make()
                ->title('Selecione uma avaliacao e uma serie para exportar.')
                ->warning()
                ->send();

            return null;
        }

        $turmas = $this->turmasDaSerieDisponiveis;
        $pautas = $this->pautasDisponiveis;
        $alunos = $this->alunosDaSerie;

        if ($turmas->isEmpty() || $pautas->isEmpty() || $alunos->isEmpty()) {
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
            ->whereIn('turma_id', $turmas->pluck('id')->map(fn ($id) => (int) $id)->all())
            ->whereIn('pauta_id', $pautasIds)
            ->whereIn('aluno_id', $alunosIds)
            ->with(['alternativa:id,nome'])
            ->get()
            ->keyBy(fn (AvaliacaoResposta $resposta): string => $resposta->turma_id.'-'.$resposta->pauta_id.'-'.$resposta->aluno_id);

        $informacoesComplementares = AvaliacaoInformacaoComplementar::query()
            ->where('avaliacao_id', (int) $this->avaliacaoAtual->id)
            ->whereIn('turma_id', $turmas->pluck('id')->map(fn ($id) => (int) $id)->all())
            ->whereIn('aluno_id', $alunosIds)
            ->get(['aluno_id', 'componente_curricular_id', 'informacoes_complementares'])
            ->keyBy(fn ($registro) => ((int) $registro->componente_curricular_id).'-'.((int) $registro->aluno_id));

        $avaliacaoId = (int) $this->avaliacaoAtual->id;
        $serieId = (int) $this->serie;
        $avaliacaoNome = (string) $this->avaliacaoAtual->nome;

        $nomeArquivo = sprintf(
            'avaliacao_%d_serie_%d_%s.csv',
            $avaliacaoId,
            $serieId,
            now()->format('Ymd_His')
        );

        return response()->streamDownload(function () use ($avaliacaoId, $avaliacaoNome, $turmas, $respostas, $informacoesComplementares): void {
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

            foreach ($turmas as $turma) {
                $turmaId = (int) $turma->id;
                $turmaNome = $this->nomeTurma($turma);

                foreach ($this->alunosDaTurma($turmaId) as $aluno) {
                    foreach ($this->pautasDaTurma($turmaId) as $pauta) {
                        $chave = $turmaId.'-'.$pauta->id.'-'.$aluno->id;
                        $resposta = $respostas->get($chave);
                        $alternativa = $resposta?->alternativa;
                        $info = $informacoesComplementares->get(((int) ($pauta->componente_curricular_id ?? 0)).'-'.((int) $aluno->id));

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

    public function getAlternativasEmMassaDisponiveisProperty(): Collection
    {
        $this->pautasDisponiveis;

        return collect($this->alternativasPorPauta)
            ->flatten(1)
            ->filter(fn (array $alternativa): bool => isset($alternativa['id'], $alternativa['nome']))
            ->unique(fn (array $alternativa): int => (int) $alternativa['id'])
            ->sortBy(fn (array $alternativa): string => (string) $alternativa['nome'])
            ->values();
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
        if (! $this->avaliacaoAtual || ! $this->serie) {
            return collect();
        }

        $pautas = collect();

        foreach ($this->turmasDaSerieDisponiveis as $turma) {
            $pautas = $pautas->merge($this->filtrarPautasDaTurma($this->avaliacaoAtual->pautas, $turma));
        }

        $pautas = $pautas->unique('id')->values();

        $this->carregarAlternativasPorPauta($pautas);

        return $pautas
            ->filter(fn (Pauta $pauta): bool => $this->alternativasDaPauta((int) $pauta->id) !== [])
            ->values();
    }

    public function getSeriesDisponiveisProperty(): Collection
    {
        return $this->turmasDisponiveis
            ->pluck('serie')
            ->filter()
            ->unique('id')
            ->sortBy(fn ($serie): string => (string) $serie->nome)
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
                    'serie_nome' => (string) ($turma->serie?->nome ?? 'Serie sem nome'),
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
        $turmasIds = $this->turmasDaSerieDisponiveis
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($turmasIds === []) {
            return collect();
        }

        return Aluno::query()
            ->whereIn('id_turma', $turmasIds)
            ->orderBy('nome')
            ->get(['id', 'nome', 'cgm', 'id_turma'])
            ->groupBy('id_turma')
            ->map(fn (Collection $alunos): Collection => $alunos->values());
    }

    public function getAlunosDaTurmaProperty(): Collection
    {
        return $this->alunosDaSerie;
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
            $alunos = $this->alunosDaTurma($turmaId);
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
            $alunos = $this->alunosDaTurma($turmaId);

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

    public function getPautasAgrupadasPorComponenteProperty(): Collection
    {
        return $this->pautasDisponiveis
            ->groupBy(fn (Pauta $pauta): string => $pauta->componente?->nome ?? 'Geral (sem componente especifico)');
    }

    public function pautasAgrupadasPorComponenteDaTurma(int $turmaId): Collection
    {
        return $this->pautasDaTurma($turmaId)
            ->groupBy(fn (Pauta $pauta): string => $pauta->componente?->nome ?? 'Geral (sem componente especifico)');
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
            ->filter(fn (Turma $turma): bool => $this->filtrarPautasDaTurma($avaliacao->pautas, $turma)->isNotEmpty())
            ->sortBy(fn (Turma $turma): string => mb_strtolower(implode('|', [
                (string) ($turma->serie?->nome ?? ''),
                (string) ($turma->escola?->nome ?? ''),
                (string) ($turma->nome ?? ''),
            ])))
            ->values();
    }

    private function filtrarPautasDaTurma(Collection $pautas, Turma $turma): Collection
    {
        return $pautas
            ->filter(fn (Pauta $pauta): bool => $this->pautaEhDaSerieDaTurma($pauta, $turma)
                && $this->pautaEhRelevanteParaTurma($pauta, $turma))
            ->values();
    }

    private function pautaEhDaSerieDaTurma(Pauta $pauta, Turma $turma): bool
    {
        return is_null($pauta->serie_id) || (int) $pauta->serie_id === (int) $turma->id_serie;
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

        return $this->pautaEhRelevanteParaComponentes($pauta, $componentesProfessor);
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
        $alunos = $this->alunosDaSerie;
        $turmasIds = $this->turmasDaSerieDisponiveis
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($pautas->isEmpty() || $alunos->isEmpty() || $turmasIds === []) {
            $this->respostas = [];
            $this->avaliacaoEmMassa = [];
            $this->alternativasPorPauta = [];

            return;
        }

        $respostasExistentes = AvaliacaoResposta::query()
            ->where('avaliacao_id', (int) $this->avaliacao)
            ->whereIn('turma_id', $turmasIds)
            ->whereIn('pauta_id', $pautas->pluck('id')->all())
            ->whereIn('aluno_id', $alunos->pluck('id')->all())
            ->get()
            ->keyBy(fn (AvaliacaoResposta $resposta): string => $resposta->pauta_id.'-'.$resposta->aluno_id);

        $respostas = [];
        $avaliacaoEmMassa = [];

        foreach ($this->turmasDaSerieDisponiveis as $turma) {
            $turmaId = (int) $turma->id;

            foreach ($this->pautasDaTurma($turmaId) as $pauta) {
                $avaliacaoEmMassa[$turmaId][$pauta->id] = null;

                foreach ($this->alunosDaTurma($turmaId) as $aluno) {
                    $chave = $pauta->id.'-'.$aluno->id;
                    $resposta = $respostasExistentes->get($chave);

                    $respostas[$pauta->id][$aluno->id] = [
                        'alternativa_id' => $resposta?->alternativa_id,
                        'observacao' => $resposta?->observacao,
                    ];
                }
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
        if (! $this->avaliacao || ! $this->serie) {
            $this->informacoesComplementares = [];

            return;
        }

        $alunosIds = $this->alunosDaSerie
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $turmasIds = $this->turmasDaSerieDisponiveis
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($alunosIds === [] || $turmasIds === []) {
            $this->informacoesComplementares = [];

            return;
        }

        $registros = AvaliacaoInformacaoComplementar::query()
            ->where('avaliacao_id', (int) $this->avaliacao)
            ->whereIn('turma_id', $turmasIds)
            ->whereIn('aluno_id', $alunosIds)
            ->get(['aluno_id', 'componente_curricular_id', 'informacoes_complementares'])
            ->keyBy(fn ($registro) => ((int) $registro->componente_curricular_id).'-'.((int) $registro->aluno_id));

        $informacoes = [];

        foreach ($this->pautasDisponiveis as $pauta) {
            $componenteId = (int) ($pauta->componente_curricular_id ?? 0);

            foreach ($alunosIds as $alunoId) {
                $chave = $componenteId.'-'.$alunoId;
                $informacoes[$componenteId][$alunoId] = (string) ($registros->get($chave)?->informacoes_complementares ?? '');
            }
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

        if (! $this->avaliacao || ! $this->serie) {
            return;
        }

        $alunoDaTurma = $this->alunoDaSerieSelecionada($alunoId);

        if (! $alunoDaTurma) {
            return;
        }

        $turmaId = (int) $alunoDaTurma->id_turma;
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
        $professorId = $this->professorIdDaTurma($turmaId);

        AvaliacaoResposta::query()->upsert(
            [[
                'avaliacao_id' => (int) $this->avaliacao,
                'pauta_id' => $pautaId,
                'turma_id' => $turmaId,
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

    private function autoSalvarInformacaoComplementar(int $componenteId, int $alunoId): void
    {
        $this->abortSeNaoPuderResponder();

        if (! $this->avaliacao || ! $this->serie) {
            return;
        }

        $alunoDaTurma = $this->alunoDaSerieSelecionada($alunoId);

        if (! $alunoDaTurma) {
            return;
        }

        $turmaId = (int) $alunoDaTurma->id_turma;
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
                'professor_id' => $this->professorIdDaTurma($turmaId),
                'informacoes_complementares' => $informacoes,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['avaliacao_id', 'turma_id', 'aluno_id', 'componente_curricular_id'],
            ['professor_id', 'informacoes_complementares', 'updated_at']
        );
    }

    private function removerRespostaPersistida(int $pautaId, int $alunoId): void
    {
        $this->abortSeNaoPuderResponder();

        if (! $this->avaliacao || ! $this->serie) {
            return;
        }

        $alunoDaTurma = $this->alunoDaSerieSelecionada($alunoId);

        if (! $alunoDaTurma) {
            return;
        }

        AvaliacaoResposta::query()
            ->where('avaliacao_id', (int) $this->avaliacao)
            ->where('pauta_id', $pautaId)
            ->where('turma_id', (int) $alunoDaTurma->id_turma)
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

    private function respostaTemObservacao(int $pautaId, int $alunoId): bool
    {
        $resposta = $this->respostas[$pautaId][$alunoId] ?? [];
        $observacao = trim((string) ($resposta['observacao'] ?? ''));

        return $observacao !== '';
    }

    private function limitarTextoCampo(mixed $valor): string
    {
        return mb_substr(trim((string) $valor), 0, self::LIMITE_CARACTERES_TEXTO);
    }

    private function turmasAlvoAvaliacaoEmMassa(): Collection
    {
        $turmaId = (int) ($this->turmaEmMassaGlobal ?? 0);

        if ($turmaId <= 0) {
            return $this->turmasDaSerieDisponiveis;
        }

        return $this->turmasDaSerieDisponiveis
            ->filter(fn (Turma $turma): bool => (int) $turma->id === $turmaId)
            ->values();
    }

    public function nomeTurma(Turma $turma): string
    {
        return implode(' - ', array_filter([
            $turma->escola?->nome,
            $turma->serie?->nome,
            $this->rotuloTurma($turma),
        ]));
    }

    public function rotuloTurma(Turma $turma): string
    {
        $nome = trim((string) $turma->nome);

        if ($nome === '') {
            return 'Turma';
        }

        return preg_match('/^turma\b/i', $nome) === 1
            ? $nome
            : 'Turma '.$nome;
    }

    private function serieDaTurma(int $turmaId): ?int
    {
        $turma = $this->turmasDisponiveis->firstWhere('id', $turmaId);
        $serieId = $turma?->id_serie;

        return $serieId ? (int) $serieId : null;
    }

    private function escolaDaTurma(int $turmaId): ?int
    {
        $turma = $this->turmasDisponiveis->firstWhere('id', $turmaId);
        $escolaId = $turma?->id_escola;

        return $escolaId ? (int) $escolaId : null;
    }

    private function escolaDaSerie(int $serieId): ?int
    {
        $escolasIds = $this->turmasDisponiveis
            ->filter(fn (Turma $turma): bool => (int) $turma->id_serie === $serieId)
            ->pluck('id_escola')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return $escolasIds->isNotEmpty() ? (int) $escolasIds->first() : null;
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

        if (! $this->escola) {
            return $this->seriesDisponiveis->contains('id', (int) $this->serie);
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
        $percentual = $total > 0
            ? min(100, (int) round(($preenchidas / $total) * 100))
            : 0;

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

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }
}
