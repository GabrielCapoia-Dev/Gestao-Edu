<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\AvaliacaoAlunoDocumento;
use App\Models\AvaliacaoInformacaoOperacional;
use App\Models\AvaliacaoRespostaOperacional;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\AvaliacaoTurmaTokenEscrita;
use App\Models\Turma;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Inicializa o estado relacional de uma turma somente quando ele é necessário.
 *
 * O JSON legado nunca é atualizado por este serviço. Ele é apenas lido como
 * fonte inicial, convertido de forma transacional e preservado como fallback
 * histórico/auditoria. Depois de inicializado, o ciclo passa a operar somente
 * nas tabelas relacionais.
 */
class AvaliacaoMigracaoLazyService
{
    /** @var array<string, true> */
    private array $garantidos = [];

    /** @var array<string, AvaliacaoTurmaCiclo> */
    private array $ciclosConhecidos = [];

    public function __construct(
        private readonly AvaliacaoTurmaCicloService $ciclos,
        private readonly TurmaAvaliacaoAlunoScopeService $escopos,
    ) {
    }

    /**
     * Garante a inicialização das turmas avaliativas correspondentes aos alunos
     * que estão sendo carregados no workspace.
     *
     * @param list<int> $alunoIds
     */
    public function garantirParaAlunos(int $avaliacaoId, array $alunoIds, ?array $turmaAvaliativaIds = null): void
    {
        $alunoIds = collect($alunoIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($avaliacaoId <= 0 || $alunoIds->isEmpty()) {
            return;
        }

        $turmasOrigemIds = Aluno::query()
            ->whereIn('id', $alunoIds->all())
            ->pluck('id_turma')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($turmasOrigemIds->isEmpty()) {
            return;
        }

        $turmasAvaliativas = $this->turmasDaAvaliacao($avaliacaoId, $turmaAvaliativaIds);
        if ($turmasAvaliativas->isEmpty()) {
            return;
        }

        $escopos = $this->escopos->escoposPorTurma($turmasAvaliativas);

        collect($escopos)
            ->filter(fn (array $escopo): bool => $turmasOrigemIds->contains((int) $escopo['turma_origem_id']))
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->each(fn (int $turmaAvaliativaId) => $this->garantirTurma($avaliacaoId, $turmaAvaliativaId));
    }

    /**
     * Usado por consultas globais do acompanhamento. Apenas avaliações ainda
     * ativas são inicializadas automaticamente; histórico legado permanece JSON
     * até que uma turma seja explicitamente aberta para trabalho/conclusão.
     *
     * @param list<int> $avaliacaoIds
     */
    public function garantirAvaliacoesAtivas(array $avaliacaoIds): void
    {
        $ids = collect($avaliacaoIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        Avaliacao::query()
            ->whereIn('id', $ids->all())
            ->where('status', Avaliacao::STATUS_ATIVA)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->each(function (int $avaliacaoId): void {
                $this->turmasDaAvaliacao($avaliacaoId)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->sort()
                    ->each(fn (int $turmaId) => $this->garantirTurma($avaliacaoId, $turmaId));
            });
    }

    public function garantirTurma(int $avaliacaoId, int $turmaAvaliativaId): AvaliacaoTurmaCiclo
    {
        if ($ciclo = $this->cicloConhecido($avaliacaoId, $turmaAvaliativaId)) {
            return $ciclo;
        }

        $pronto = AvaliacaoTurmaCiclo::query()
            ->where('avaliacao_id', $avaliacaoId)
            ->where('turma_avaliativa_id', $turmaAvaliativaId)
            ->where(function ($query): void {
                $query
                    ->where('status', AvaliacaoTurmaCiclo::STATUS_CONCLUIDA)
                    ->orWhereNotNull('operacional_inicializado_em');
            })
            ->first();

        if ($pronto) {
            return $pronto;
        }

        return Cache::lock(
            'avaliacao-lazy-inicializacao:'.$avaliacaoId.':'.$turmaAvaliativaId,
            (int) config('exports.lock_expiration', 1200),
        )->block(30, fn (): AvaliacaoTurmaCiclo => $this->garantirTurmaSemLock($avaliacaoId, $turmaAvaliativaId));
    }

    public function adotarCicloPronto(AvaliacaoTurmaCiclo $ciclo): void
    {
        if (
            $ciclo->status !== AvaliacaoTurmaCiclo::STATUS_CONCLUIDA
            && $ciclo->operacional_inicializado_em === null
        ) {
            return;
        }

        $chave = (int) $ciclo->avaliacao_id.':'.(int) $ciclo->turma_avaliativa_id;
        $this->ciclosConhecidos[$chave] = $ciclo;
        $this->garantidos[$chave] = true;
    }

    protected function cicloConhecido(int $avaliacaoId, int $turmaAvaliativaId): ?AvaliacaoTurmaCiclo
    {
        return $this->ciclosConhecidos[$avaliacaoId.':'.$turmaAvaliativaId] ?? null;
    }

    private function garantirTurmaSemLock(int $avaliacaoId, int $turmaAvaliativaId): AvaliacaoTurmaCiclo
    {
        $chave = $avaliacaoId.':'.$turmaAvaliativaId;

        if (isset($this->garantidos[$chave])) {
            $ciclo = $this->ciclos->obter($avaliacaoId, $turmaAvaliativaId);
            if ($ciclo) {
                return $ciclo;
            }
            unset($this->garantidos[$chave]);
        }

        $turma = Turma::query()->findOrFail($turmaAvaliativaId);
        $pertence = DB::table('avaliacao_turma')
            ->where('avaliacao_id', $avaliacaoId)
            ->where('turma_id', $turmaAvaliativaId)
            ->exists();

        if (! $pertence) {
            throw new RuntimeException('A turma informada não pertence à avaliação.');
        }

        $escopo = $this->escopos->escoposPorTurma(collect([$turma]))[$turmaAvaliativaId] ?? null;
        if (! is_array($escopo)) {
            throw new RuntimeException('Não foi possível resolver a turma de origem da avaliação.');
        }

        $turmaOrigemId = (int) $escopo['turma_origem_id'];
        $existente = $this->ciclos->obter($avaliacaoId, $turmaAvaliativaId);

        if ($existente?->status === AvaliacaoTurmaCiclo::STATUS_CONCLUIDA) {
            $this->garantidos[$chave] = true;

            return $existente;
        }

        $ciclo = $this->ciclos->obterOuCriar($avaliacaoId, $turmaAvaliativaId, $turmaOrigemId);

        if ($ciclo->operacional_inicializado_em !== null) {
            $this->garantidos[$chave] = true;

            return $ciclo;
        }

        try {
            $ciclo = DB::transaction(function () use ($avaliacaoId, $turmaAvaliativaId, $turmaOrigemId, $ciclo): AvaliacaoTurmaCiclo {
                $ciclo = AvaliacaoTurmaCiclo::query()->lockForUpdate()->findOrFail((int) $ciclo->id);

                if ($ciclo->operacional_inicializado_em !== null || $ciclo->status === AvaliacaoTurmaCiclo::STATUS_CONCLUIDA) {
                    return $ciclo;
                }

                if (! $ciclo->aceitaEscrita()) {
                    throw new RuntimeException('O ciclo da turma não aceita inicialização operacional.');
                }

                $token = AvaliacaoTurmaTokenEscrita::query()
                    ->where('ciclo_id', (int) $ciclo->id)
                    ->lockForUpdate()
                    ->first();

                if (! $token) {
                    throw new RuntimeException('O ciclo da turma não possui token de escrita.');
                }

                // Ciclos reabertos já foram reidratados a partir do snapshot final.
                // Nunca voltamos a aplicar o JSON legado sobre esse estado.
                if ($ciclo->status === AvaliacaoTurmaCiclo::STATUS_REABERTA || $ciclo->snapshot_evento_atual_id) {
                    $ciclo->forceFill([
                        'operacional_inicializado_em' => now(),
                        'legado_documentos_migrados' => 0,
                        'legado_migracao_hash' => null,
                    ])->save();

                    return $ciclo;
                }

                $documentos = AvaliacaoAlunoDocumento::query()
                    ->where('avaliacao_id', $avaliacaoId)
                    ->where('turma_id', $turmaOrigemId)
                    ->orderBy('id')
                    ->get();

                $this->validarDocumentos($avaliacaoId, $documentos);

                [$respostas, $informacoes] = $this->montarLinhas(
                    $documentos,
                    $ciclo,
                    (int) $token->id,
                    $avaliacaoId,
                    $turmaAvaliativaId,
                    $turmaOrigemId,
                );

                $this->validarConflitosExistentes($ciclo, $respostas, $informacoes);

                if ($respostas !== []) {
                    DB::table('avaliacao_respostas_operacionais')->insertOrIgnore($respostas);
                }
                if ($informacoes !== []) {
                    DB::table('avaliacao_informacoes_operacionais')->insertOrIgnore($informacoes);
                }

                $hash = $this->hashDocumentos($documentos);
                $ciclo->forceFill([
                    'operacional_inicializado_em' => now(),
                    'legado_documentos_migrados' => $documentos->count(),
                    'legado_migracao_hash' => $hash,
                ])->save();

                return $ciclo->refresh();
            }, 3);
        } catch (Throwable $exception) {
            $this->registrarFalha($avaliacaoId, $turmaAvaliativaId, $turmaOrigemId, $exception);
            throw $exception;
        }

        $this->garantidos[$chave] = true;

        return $ciclo;
    }

    /** @return Collection<int, Turma> */
    private function turmasDaAvaliacao(int $avaliacaoId, ?array $turmaAvaliativaIds = null): Collection
    {
        return Turma::query()
            ->whereIn('id', DB::table('avaliacao_turma')
                ->where('avaliacao_id', $avaliacaoId)
                ->when($turmaAvaliativaIds !== null, fn ($query) => $query->whereIn('turma_id', $turmaAvaliativaIds))
                ->select('turma_id'))
            ->get();
    }

    /** @param Collection<int, AvaliacaoAlunoDocumento> $documentos */
    private function validarDocumentos(int $avaliacaoId, Collection $documentos): void
    {
        if ($documentos->isEmpty()) {
            return;
        }

        $pautaIds = $documentos
            ->flatMap(fn (AvaliacaoAlunoDocumento $documento): array => array_keys($documento->pautasPayload()))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($pautaIds->isNotEmpty()) {
            $validas = DB::table('avaliacao_pauta')
                ->where('avaliacao_id', $avaliacaoId)
                ->whereIn('pauta_id', $pautaIds->all())
                ->pluck('pauta_id')
                ->map(fn ($id): int => (int) $id)
                ->unique();

            $invalidas = $pautaIds->diff($validas);
            if ($invalidas->isNotEmpty()) {
                throw new RuntimeException('O JSON legado contém pautas que não pertencem mais à avaliação: '.$invalidas->implode(', ').'.');
            }
        }

        $alternativaIds = $documentos
            ->flatMap(fn (AvaliacaoAlunoDocumento $documento): array => collect($documento->pautasPayload())
                ->pluck('alternativa_id')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->all())
            ->unique()
            ->values();

        if ($alternativaIds->isNotEmpty()) {
            $existentes = DB::table('alternativas')
                ->whereIn('id', $alternativaIds->all())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->unique();

            $invalidas = $alternativaIds->diff($existentes);
            if ($invalidas->isNotEmpty()) {
                throw new RuntimeException('O JSON legado contém alternativas inexistentes: '.$invalidas->implode(', ').'.');
            }
        }
    }

    /**
     * @param Collection<int, AvaliacaoAlunoDocumento> $documentos
     * @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>}
     */
    private function montarLinhas(
        Collection $documentos,
        AvaliacaoTurmaCiclo $ciclo,
        int $tokenId,
        int $avaliacaoId,
        int $turmaAvaliativaId,
        int $turmaOrigemId,
    ): array {
        $agora = now();
        $respostas = [];
        $informacoes = [];

        $professoresExistentes = DB::table('professores')->pluck('id')->map(fn ($id): int => (int) $id)->flip();
        $componentesExistentes = DB::table('componentes_curriculares')->pluck('id')->map(fn ($id): int => (int) $id)->flip();

        foreach ($documentos as $documento) {
            foreach ($documento->pautasPayload() as $pautaId => $item) {
                if (! is_array($item) || empty($item['alternativa_id'])) {
                    continue;
                }

                $professorId = ! empty($item['professor_id']) ? (int) $item['professor_id'] : null;
                $componenteId = ! empty($item['componente_curricular_id']) ? (int) $item['componente_curricular_id'] : null;

                $respostas[] = [
                    'token_escrita_id' => $tokenId,
                    'ciclo_id' => (int) $ciclo->id,
                    'avaliacao_id' => $avaliacaoId,
                    'turma_avaliativa_id' => $turmaAvaliativaId,
                    'turma_origem_id' => $turmaOrigemId,
                    'aluno_id' => (int) $documento->aluno_id,
                    'pauta_id' => (int) $pautaId,
                    'componente_curricular_id' => $componenteId && $componentesExistentes->has($componenteId) ? $componenteId : null,
                    'professor_id' => $professorId && $professoresExistentes->has($professorId) ? $professorId : null,
                    'alternativa_id' => (int) $item['alternativa_id'],
                    'observacao' => $this->texto($item['observacao'] ?? null),
                    'respondido_em' => $item['respondido_em'] ?? $documento->ultima_resposta_em ?? $agora,
                    'version' => max(1, (int) ($item['version'] ?? 1)),
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
            }

            foreach ($documento->informacoesComplementaresPayload() as $componenteId => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $texto = $this->texto($item['texto'] ?? null);
                if ($texto === null) {
                    continue;
                }

                $componenteChave = (int) $componenteId;
                $professorId = ! empty($item['professor_id']) ? (int) $item['professor_id'] : null;

                $informacoes[] = [
                    'token_escrita_id' => $tokenId,
                    'ciclo_id' => (int) $ciclo->id,
                    'avaliacao_id' => $avaliacaoId,
                    'turma_avaliativa_id' => $turmaAvaliativaId,
                    'turma_origem_id' => $turmaOrigemId,
                    'aluno_id' => (int) $documento->aluno_id,
                    'componente_curricular_id' => $componenteChave > 0 && $componentesExistentes->has($componenteChave) ? $componenteChave : null,
                    'componente_chave' => max(0, $componenteChave),
                    'professor_id' => $professorId && $professoresExistentes->has($professorId) ? $professorId : null,
                    'texto' => $texto,
                    'version' => max(1, (int) ($item['version'] ?? 1)),
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
            }
        }

        return [$respostas, $informacoes];
    }

    /**
     * Não sobrescreve silenciosamente uma linha relacional que já exista com
     * conteúdo diferente. Isso protege contra tentativas anteriores de migração
     * parcial ou gravações feitas antes da inicialização ser concluída.
     *
     * @param list<array<string,mixed>> $respostas
     * @param list<array<string,mixed>> $informacoes
     */
    private function validarConflitosExistentes(AvaliacaoTurmaCiclo $ciclo, array $respostas, array $informacoes): void
    {
        $existentes = AvaliacaoRespostaOperacional::query()
            ->where('ciclo_id', (int) $ciclo->id)
            ->get()
            ->keyBy(fn (AvaliacaoRespostaOperacional $item): string => $item->aluno_id.':'.$item->pauta_id);

        foreach ($respostas as $linha) {
            $atual = $existentes->get($linha['aluno_id'].':'.$linha['pauta_id']);
            if (! $atual) {
                continue;
            }

            if (
                (int) $atual->alternativa_id !== (int) $linha['alternativa_id']
                || $this->texto($atual->observacao) !== $this->texto($linha['observacao'])
                || (int) ($atual->professor_id ?? 0) !== (int) ($linha['professor_id'] ?? 0)
                || (int) ($atual->componente_curricular_id ?? 0) !== (int) ($linha['componente_curricular_id'] ?? 0)
            ) {
                throw new RuntimeException("Divergência entre JSON legado e relacional no aluno {$linha['aluno_id']}, pauta {$linha['pauta_id']}.");
            }
        }

        $infosExistentes = AvaliacaoInformacaoOperacional::query()
            ->where('ciclo_id', (int) $ciclo->id)
            ->get()
            ->keyBy(fn (AvaliacaoInformacaoOperacional $item): string => $item->aluno_id.':'.$item->componente_chave);

        foreach ($informacoes as $linha) {
            $atual = $infosExistentes->get($linha['aluno_id'].':'.$linha['componente_chave']);
            if (! $atual) {
                continue;
            }

            if (
                $this->texto($atual->texto) !== $this->texto($linha['texto'])
                || (int) ($atual->professor_id ?? 0) !== (int) ($linha['professor_id'] ?? 0)
            ) {
                throw new RuntimeException("Divergência entre JSON legado e relacional nas informações do aluno {$linha['aluno_id']}.");
            }
        }
    }

    /** @param Collection<int, AvaliacaoAlunoDocumento> $documentos */
    private function hashDocumentos(Collection $documentos): ?string
    {
        if ($documentos->isEmpty()) {
            return null;
        }

        $conteudo = $documentos
            ->sortBy('id')
            ->map(fn (AvaliacaoAlunoDocumento $documento): array => [
                'id' => (int) $documento->id,
                'aluno_id' => (int) $documento->aluno_id,
                'payload' => $documento->payload,
            ])
            ->values()
            ->all();

        return hash('sha256', json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function registrarFalha(
        int $avaliacaoId,
        int $turmaAvaliativaId,
        int $turmaOrigemId,
        Throwable $exception,
    ): void {
        if (! Schema::hasTable('avaliacao_migracao_inconsistencias')) {
            return;
        }

        $documentos = AvaliacaoAlunoDocumento::query()
            ->where('avaliacao_id', $avaliacaoId)
            ->where('turma_id', $turmaOrigemId)
            ->get(['id', 'aluno_id']);

        if ($documentos->isEmpty()) {
            return;
        }

        foreach ($documentos as $documento) {
            DB::table('avaliacao_migracao_inconsistencias')->updateOrInsert(
                ['documento_id' => (int) $documento->id, 'codigo' => 'migracao_lazy_falhou'],
                [
                    'avaliacao_id' => $avaliacaoId,
                    'aluno_id' => (int) $documento->aluno_id,
                    'mensagem' => mb_substr($exception->getMessage(), 0, 65000),
                    'contexto' => json_encode([
                        'turma_avaliativa_id' => $turmaAvaliativaId,
                        'turma_origem_id' => $turmaOrigemId,
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function texto(mixed $valor): ?string
    {
        $valor = trim((string) ($valor ?? ''));

        return $valor !== '' ? $valor : null;
    }
}
