<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoAlunoSnapshot;
use App\Models\AvaliacaoInformacaoOperacional;
use App\Models\AvaliacaoRespostaOperacional;
use App\Models\AvaliacaoSnapshotEvento;
use App\Models\AvaliacaoSnapshotResumoComponente;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\AvaliacaoTurmaTokenEscrita;
use App\Models\Pauta;
use App\Models\Professor;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use App\Support\Avaliacoes\AvaliacaoPerformanceContext;

class AvaliacaoSnapshotService
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        private readonly AvaliacaoTurmaCicloService $ciclos,
        private readonly ParecerResponsaveisResolver $responsaveis,
    ) {
    }

    public function concluir(AvaliacaoTurmaCiclo|int $ciclo, User $ator): AvaliacaoSnapshotEvento
    {
        $cicloId = $ciclo instanceof AvaliacaoTurmaCiclo ? (int) $ciclo->id : (int) $ciclo;

        return DB::transaction(function () use ($cicloId, $ator): AvaliacaoSnapshotEvento {
            $ciclo = AvaliacaoTurmaCiclo::query()->lockForUpdate()->findOrFail($cicloId);

            if ($ciclo->status === AvaliacaoTurmaCiclo::STATUS_CONCLUIDA) {
                $evento = $ciclo->snapshotAtual()->first();
                if ($evento) {
                    return $evento;
                }

                throw new RuntimeException('Ciclo concluído sem snapshot final publicado.');
            }

            $token = $this->ciclos->bloquearTokenExclusivo((int) $ciclo->id);
            $avaliacao = Avaliacao::query()->with(['periodo', 'tipo.alternativas'])->findOrFail((int) $ciclo->avaliacao_id);
            $turmaAvaliativa = Turma::query()->with('escola', 'serie')->findOrFail((int) $ciclo->turma_avaliativa_id);
            $turmaOrigem = Turma::query()->with('escola', 'serie')->findOrFail((int) $ciclo->turma_origem_id);
            $alunos = $this->alunosEsperados($ciclo);
            $pautas = $this->pautasEsperadas($avaliacao, $turmaAvaliativa);

            if ($alunos->isEmpty() || $pautas->isEmpty()) {
                throw new RuntimeException('Uma turma sem alunos ou pautas esperadas não pode ser concluída.');
            }

            $respostas = AvaliacaoRespostaOperacional::query()
                ->where('ciclo_id', (int) $ciclo->id)
                ->whereIn('aluno_id', $alunos->pluck('id'))
                ->whereIn('pauta_id', $pautas->pluck('id'))
                ->orderByDesc('version')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->unique(fn (AvaliacaoRespostaOperacional $resposta): string => $resposta->aluno_id.':'.$resposta->pauta_id)
                ->values();
            $informacoes = AvaliacaoInformacaoOperacional::query()
                ->where('ciclo_id', (int) $ciclo->id)
                ->whereIn('aluno_id', $alunos->pluck('id'))
                ->get();

            $this->validarCompletude($avaliacao, $alunos, $pautas, $respostas);

            $alternativas = Alternativa::query()
                ->whereIn('id', $respostas->pluck('alternativa_id')->filter()->unique())
                ->get()
                ->keyBy('id');
            $professores = Professor::query()
                ->whereIn('id', $respostas->pluck('professor_id')->merge($informacoes->pluck('professor_id'))->filter()->unique())
                ->get()
                ->keyBy('id');
            $responsaveis = $this->responsaveis->resolver($turmaAvaliativa, now(), true);
            $versao = (int) $ciclo->versao_conclusao + 1;
            $payloads = $this->montarPayloads(
                $avaliacao,
                $turmaAvaliativa,
                $turmaOrigem,
                $alunos,
                $pautas,
                $respostas,
                $informacoes,
                $alternativas,
                $professores,
                $responsaveis,
                $versao,
            );
            $hashAgregado = hash('sha256', $payloads->pluck('hash')->implode(':'));
            $agora = now();
            $evento = AvaliacaoSnapshotEvento::query()->create([
                'id' => (string) Str::uuid(),
                'idempotency_key' => "conclusao:{$ciclo->id}:{$versao}",
                'tipo' => AvaliacaoSnapshotEvento::TIPO_CONCLUSAO,
                'ciclo_id' => (int) $ciclo->id,
                'avaliacao_id' => (int) $avaliacao->id,
                'turma_avaliativa_id' => (int) $turmaAvaliativa->id,
                'turma_origem_id' => (int) $turmaOrigem->id,
                'versao' => $versao,
                'schema_version' => self::SCHEMA_VERSION,
                'criado_por' => (int) $ator->id,
                'criado_por_snapshot' => $this->snapshotAtor($ator),
                'total_alunos' => $alunos->count(),
                'total_respostas_esperadas' => $alunos->count() * $pautas->count(),
                'total_respostas_geradas' => $respostas->count(),
                'tamanho_bytes' => $payloads->sum('bytes'),
                'payload_hash_agregado' => $hashAgregado,
                'publicado_em' => null,
            ]);

            foreach ($payloads as $item) {
                AvaliacaoAlunoSnapshot::query()->create([
                    'evento_id' => (string) $evento->id,
                    'avaliacao_id' => (int) $avaliacao->id,
                    'ciclo_id' => (int) $ciclo->id,
                    'turma_avaliativa_id' => (int) $turmaAvaliativa->id,
                    'turma_origem_id' => (int) $turmaOrigem->id,
                    'aluno_id' => (int) $item['aluno']->id,
                    'cgm' => (string) $item['aluno']->cgm,
                    'tipo' => AvaliacaoSnapshotEvento::TIPO_CONCLUSAO,
                    'schema_version' => self::SCHEMA_VERSION,
                    'payload' => $item['payload'],
                    'payload_hash' => $item['hash'],
                    'total_pautas_esperadas' => $pautas->count(),
                    'total_respostas' => count($item['payload']['pautas']),
                    'total_informacoes' => count($item['payload']['informacoes_complementares']),
                    'tamanho_bytes' => $item['bytes'],
                ]);
            }

            $this->criarResumos($evento, $ciclo, $alunos, $pautas, $respostas);

            if (AvaliacaoAlunoSnapshot::query()->where('evento_id', $evento->id)->count() !== $alunos->count()) {
                throw new RuntimeException('A quantidade de snapshots gerados diverge do roster esperado.');
            }

            $evento->forceFill(['publicado_em' => $agora])->save();

            AvaliacaoRespostaOperacional::query()->where('ciclo_id', (int) $ciclo->id)->delete();
            AvaliacaoInformacaoOperacional::query()->where('ciclo_id', (int) $ciclo->id)->delete();
            AvaliacaoTurmaTokenEscrita::query()->whereKey((int) $token->id)->delete();

            $ciclo->forceFill([
                'status' => AvaliacaoTurmaCiclo::STATUS_CONCLUIDA,
                'roster_mode' => AvaliacaoTurmaCiclo::ROSTER_CONGELADO,
                'versao_conclusao' => $versao,
                'snapshot_evento_atual_id' => (string) $evento->id,
                'concluida_em' => $agora,
                'concluida_por' => (int) $ator->id,
                'concluida_por_snapshot' => $this->snapshotAtor($ator),
            ])->save();

            $this->sincronizarStatusAvaliacao($avaliacao);
            app(AvaliacaoPerformanceContext::class)->add([
                'acao' => 'concluir_turma',
                'ciclo_id' => (int) $ciclo->id,
                'avaliacao_id' => (int) $avaliacao->id,
                'turma_id' => (int) $turmaAvaliativa->id,
                'snapshot_evento_id' => (string) $evento->id,
                'snapshot_alunos' => $alunos->count(),
                'snapshot_bytes' => (int) $evento->tamanho_bytes,
            ]);

            return $evento->refresh();
        }, 3);
    }

    public function reabrir(AvaliacaoTurmaCiclo|int $ciclo, User $ator, string $motivo): AvaliacaoTurmaCiclo
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new RuntimeException('O motivo da reabertura é obrigatório.');
        }

        $cicloId = $ciclo instanceof AvaliacaoTurmaCiclo ? (int) $ciclo->id : (int) $ciclo;

        return DB::transaction(function () use ($cicloId, $ator, $motivo): AvaliacaoTurmaCiclo {
            $ciclo = AvaliacaoTurmaCiclo::query()->lockForUpdate()->findOrFail($cicloId);

            if ($ciclo->status !== AvaliacaoTurmaCiclo::STATUS_CONCLUIDA) {
                throw new RuntimeException('Somente turmas concluídas podem ser reabertas.');
            }

            $evento = AvaliacaoSnapshotEvento::query()
                ->whereKey($ciclo->snapshot_evento_atual_id)
                ->where('tipo', AvaliacaoSnapshotEvento::TIPO_CONCLUSAO)
                ->whereNotNull('publicado_em')
                ->with('snapshots')
                ->firstOrFail();
            $this->validarEvento($evento);

            $token = AvaliacaoTurmaTokenEscrita::query()->create([
                'ciclo_id' => (int) $ciclo->id,
                'generation_uuid' => (string) Str::uuid(),
            ]);
            $agora = now();
            $respostas = [];
            $informacoes = [];

            foreach ($evento->snapshots->sortBy('aluno_id') as $snapshot) {
                $payload = $snapshot->payload;
                foreach ((array) ($payload['pautas'] ?? []) as $resposta) {
                    $respostas[] = [
                        'token_escrita_id' => (int) $token->id,
                        'ciclo_id' => (int) $ciclo->id,
                        'avaliacao_id' => (int) $ciclo->avaliacao_id,
                        'turma_avaliativa_id' => (int) $ciclo->turma_avaliativa_id,
                        'turma_origem_id' => (int) $ciclo->turma_origem_id,
                        'aluno_id' => (int) $snapshot->aluno_id,
                        'pauta_id' => (int) $resposta['pauta_id'],
                        'componente_curricular_id' => $resposta['componente_curricular_id'] ?? null,
                        'professor_id' => $resposta['professor_id'] ?? null,
                        'alternativa_id' => $resposta['alternativa_id'] ?? null,
                        'observacao' => $resposta['observacao'] ?? null,
                        'respondido_em' => $resposta['respondido_em'] ?? $agora,
                        'version' => 1,
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ];
                }

                foreach ((array) ($payload['informacoes_complementares'] ?? []) as $info) {
                    $informacoes[] = [
                        'token_escrita_id' => (int) $token->id,
                        'ciclo_id' => (int) $ciclo->id,
                        'avaliacao_id' => (int) $ciclo->avaliacao_id,
                        'turma_avaliativa_id' => (int) $ciclo->turma_avaliativa_id,
                        'turma_origem_id' => (int) $ciclo->turma_origem_id,
                        'aluno_id' => (int) $snapshot->aluno_id,
                        'componente_curricular_id' => $info['componente_curricular_id'] ?? null,
                        'componente_chave' => (int) ($info['componente_curricular_id'] ?? 0),
                        'professor_id' => $info['professor_id'] ?? null,
                        'texto' => $info['texto'] ?? null,
                        'version' => 1,
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ];
                }
            }

            DB::table('avaliacao_respostas_operacionais')->insert($respostas);
            if ($informacoes !== []) {
                DB::table('avaliacao_informacoes_operacionais')->insert($informacoes);
            }

            if (count($respostas) !== (int) $evento->total_respostas_geradas) {
                throw new RuntimeException('A reidratação não reproduziu a quantidade esperada de respostas.');
            }

            $ciclo->forceFill([
                'status' => AvaliacaoTurmaCiclo::STATUS_REABERTA,
                'roster_mode' => AvaliacaoTurmaCiclo::ROSTER_CONGELADO,
                'reaberta_em' => $agora,
                'reaberta_por' => (int) $ator->id,
                'reaberta_por_snapshot' => $this->snapshotAtor($ator),
                'motivo_reabertura' => $motivo,
            ])->save();
            Avaliacao::query()->whereKey((int) $ciclo->avaliacao_id)->update(['status' => Avaliacao::STATUS_ATIVA]);
            app(AvaliacaoPerformanceContext::class)->add([
                'acao' => 'reabrir_turma',
                'ciclo_id' => (int) $ciclo->id,
                'avaliacao_id' => (int) $ciclo->avaliacao_id,
                'turma_id' => (int) $ciclo->turma_avaliativa_id,
                'respostas_reidratadas' => count($respostas),
            ]);

            return $ciclo->refresh();
        }, 3);
    }

    public function validarEvento(AvaliacaoSnapshotEvento $evento): void
    {
        $snapshots = $evento->relationLoaded('snapshots') ? $evento->snapshots : $evento->snapshots()->get();
        if ($snapshots->count() !== (int) $evento->total_alunos) {
            throw new RuntimeException('Evento com quantidade inválida de snapshots.');
        }

        foreach ($snapshots as $snapshot) {
            if (! hash_equals((string) $snapshot->payload_hash, $this->hashPayload((array) $snapshot->payload))) {
                throw new RuntimeException("Snapshot inválido para o aluno {$snapshot->aluno_id}.");
            }
        }

        $agregado = hash('sha256', $snapshots->sortBy('aluno_id')->pluck('payload_hash')->implode(':'));
        if (! hash_equals((string) $evento->payload_hash_agregado, $agregado)) {
            throw new RuntimeException('O hash agregado do evento é inválido.');
        }
    }

    private function alunosEsperados(AvaliacaoTurmaCiclo $ciclo): Collection
    {
        if ($ciclo->status === AvaliacaoTurmaCiclo::STATUS_REABERTA && $ciclo->snapshot_evento_atual_id) {
            return Aluno::query()
                ->whereIn('id', AvaliacaoAlunoSnapshot::query()
                    ->where('evento_id', $ciclo->snapshot_evento_atual_id)
                    ->select('aluno_id'))
                ->orderBy('id')
                ->get();
        }

        return Aluno::query()
            ->where('id_turma', (int) $ciclo->turma_origem_id)
            ->whereIn('status', [Aluno::STATUS_MATRICULADO, Aluno::STATUS_PENDENTE])
            ->where(fn ($query) => $query
                ->where('status', '!=', Aluno::STATUS_PENDENTE)
                ->orWhereNull('pendencia_origem_aluno_id'))
            ->orderBy('id')
            ->get();
    }

    private function pautasEsperadas(Avaliacao $avaliacao, Turma $turma): Collection
    {
        return $avaliacao->pautas()
            ->where('pautas.status', true)
            ->where(fn ($query) => $query
                ->whereNull('pautas.serie_id')
                ->orWhere('pautas.serie_id', (int) $turma->id_serie))
            ->with(['componente', 'alternativas', 'tipo.alternativas'])
            ->orderBy('pautas.id')
            ->get();
    }

    private function validarCompletude(Avaliacao $avaliacao, Collection $alunos, Collection $pautas, Collection $respostas): void
    {
        $esperadas = $alunos->count() * $pautas->count();
        if ($esperadas <= 0 || $respostas->count() !== $esperadas) {
            throw new RuntimeException("A turma possui {$respostas->count()} de {$esperadas} respostas obrigatórias.");
        }

        $alternativas = Alternativa::query()
            ->whereIn('id', $respostas->pluck('alternativa_id')->filter()->unique())
            ->get()
            ->keyBy('id');
        $overrides = DB::table('avaliacao_pauta_alternativa')
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->whereIn('pauta_id', $pautas->pluck('id'))
            ->get(['pauta_id', 'alternativa_id'])
            ->groupBy('pauta_id');
        $ativas = Alternativa::query()
            ->whereIn('id', $overrides->flatten(1)->pluck('alternativa_id')->merge(
                $pautas->flatMap(fn (Pauta $pauta): Collection => $pauta->alternativas
                    ->merge($pauta->tipo?->alternativas ?? collect()))
                    ->merge($avaliacao->tipo?->alternativas ?? collect())
                    ->pluck('id')
            )->filter()->unique())
            ->where('status', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->flip();
        $validasPorPauta = $pautas->mapWithKeys(function (Pauta $pauta) use ($avaliacao, $overrides, $ativas): array {
            $ids = $overrides->get((int) $pauta->id, collect())
                ->pluck('alternativa_id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $ativas->has($id));

            if ($ids->isEmpty()) {
                $ids = collect($pauta->tipo?->alternativas ?? [])
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->filter(fn (int $id): bool => $ativas->has($id));
            }
            if ($ids->isEmpty()) {
                $ids = collect($avaliacao->tipo?->alternativas ?? [])
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->filter(fn (int $id): bool => $ativas->has($id));
            }
            if ($ids->isEmpty()) {
                $ids = $pauta->alternativas
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->filter(fn (int $id): bool => $ativas->has($id));
            }

            return [(int) $pauta->id => $ids->unique()->flip()];
        });

        foreach ($respostas as $resposta) {
            $alternativa = $alternativas->get((int) $resposta->alternativa_id);
            if (! $alternativa || ! $validasPorPauta->get((int) $resposta->pauta_id, collect())->has((int) $resposta->alternativa_id)) {
                throw new RuntimeException("A pauta {$resposta->pauta_id} possui alternativa inválida.");
            }
            if ($alternativa->tem_observacao && trim((string) $resposta->observacao) === '') {
                throw new RuntimeException("A pauta {$resposta->pauta_id} exige observação.");
            }
        }
    }

    private function montarPayloads(
        Avaliacao $avaliacao,
        Turma $turmaAvaliativa,
        Turma $turmaOrigem,
        Collection $alunos,
        Collection $pautas,
        Collection $respostas,
        Collection $informacoes,
        Collection $alternativas,
        Collection $professores,
        array $responsaveis,
        int $versao,
    ): Collection {
        $pautasPorId = $pautas->keyBy('id');
        $respostasPorAluno = $respostas->groupBy('aluno_id');
        $infosPorAluno = $informacoes->groupBy('aluno_id');

        return $alunos->sortBy('id')->map(function (Aluno $aluno) use (
            $avaliacao, $turmaAvaliativa, $turmaOrigem, $alunos, $pautas, $pautasPorId,
            $respostasPorAluno, $infosPorAluno, $alternativas, $professores, $responsaveis, $versao,
        ): array {
            $payload = [
                'schema_version' => self::SCHEMA_VERSION,
                'snapshot_type' => AvaliacaoSnapshotEvento::TIPO_CONCLUSAO,
                'versao_conclusao' => $versao,
                'gerado_em' => now()->toIso8601String(),
                'avaliacao' => ['id' => (int) $avaliacao->id, 'nome' => (string) $avaliacao->nome],
                'periodo' => ['id' => (int) ($avaliacao->periodo_avaliacao_id ?? 0), 'nome' => (string) ($avaliacao->periodo?->nome ?? '')],
                'aluno' => ['id' => (int) $aluno->id, 'nome' => (string) $aluno->nome, 'cgm' => (string) $aluno->cgm],
                'matricula' => ['status' => (string) $aluno->status, 'tipo_vinculo' => (string) $aluno->tipo_vinculo],
                'turma_avaliativa' => $this->snapshotTurma($turmaAvaliativa),
                'turma_fisica' => $this->snapshotTurma($turmaOrigem),
                'pautas' => [],
                'informacoes_complementares' => [],
                'responsaveis_snapshot' => $responsaveis,
                'roster' => ['mode' => AvaliacaoTurmaCiclo::ROSTER_CONGELADO, 'total_alunos' => $alunos->count(), 'total_pautas' => $pautas->count()],
                'regras_completude' => ['informacoes_contam' => false, 'observacao_obrigatoria' => true],
            ];

            foreach ($respostasPorAluno->get((int) $aluno->id, collect())->sortBy('pauta_id') as $resposta) {
                $pauta = $pautasPorId->get((int) $resposta->pauta_id);
                $alternativa = $alternativas->get((int) $resposta->alternativa_id);
                $professor = $professores->get((int) $resposta->professor_id);
                $payload['pautas'][(string) $resposta->pauta_id] = [
                    'pauta_id' => (int) $resposta->pauta_id,
                    'pauta_texto' => (string) ($pauta?->texto ?? ''),
                    'componente_curricular_id' => $resposta->componente_curricular_id ? (int) $resposta->componente_curricular_id : null,
                    'componente_nome' => (string) ($pauta?->componente?->nome ?? ''),
                    'alternativa_id' => (int) $resposta->alternativa_id,
                    'alternativa_nome' => (string) ($alternativa?->nome ?? ''),
                    'observacao' => $resposta->observacao,
                    'professor_id' => $resposta->professor_id ? (int) $resposta->professor_id : null,
                    'professor_nome' => (string) ($professor?->nome ?? ''),
                    'respondido_em' => $resposta->respondido_em?->toIso8601String(),
                ];
            }

            foreach ($infosPorAluno->get((int) $aluno->id, collect())->sortBy('componente_curricular_id') as $info) {
                $professor = $professores->get((int) $info->professor_id);
                $payload['informacoes_complementares'][(string) ($info->componente_curricular_id ?? 0)] = [
                    'componente_curricular_id' => $info->componente_curricular_id ? (int) $info->componente_curricular_id : null,
                    'professor_id' => $info->professor_id ? (int) $info->professor_id : null,
                    'professor_nome' => (string) ($professor?->nome ?? ''),
                    'texto' => (string) $info->texto,
                ];
            }

            $json = $this->jsonCanonico($payload);

            return ['aluno' => $aluno, 'payload' => $payload, 'hash' => hash('sha256', $json), 'bytes' => strlen($json)];
        })->values();
    }

    private function criarResumos(AvaliacaoSnapshotEvento $evento, AvaliacaoTurmaCiclo $ciclo, Collection $alunos, Collection $pautas, Collection $respostas): void
    {
        foreach ($pautas->groupBy(fn (Pauta $pauta): int => (int) ($pauta->componente_curricular_id ?? 0)) as $componenteId => $pautasComponente) {
            $esperadas = $alunos->count() * $pautasComponente->count();
            $concluidas = $respostas->whereIn('pauta_id', $pautasComponente->pluck('id'))->count();
            AvaliacaoSnapshotResumoComponente::query()->create([
                'evento_id' => (string) $evento->id,
                'ciclo_id' => (int) $ciclo->id,
                'componente_curricular_id' => (int) $componenteId ?: null,
                'respostas_esperadas' => $esperadas,
                'respostas_concluidas' => $concluidas,
                'respostas_pendentes' => max(0, $esperadas - $concluidas),
                'percentual' => $esperadas > 0 ? round(($concluidas / $esperadas) * 100, 2) : 0,
            ]);
        }
    }

    private function sincronizarStatusAvaliacao(Avaliacao $avaliacao): void
    {
        $totalTurmas = DB::table('avaliacao_turma')->where('avaliacao_id', (int) $avaliacao->id)->count();
        $concluidas = AvaliacaoTurmaCiclo::query()
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->where('status', AvaliacaoTurmaCiclo::STATUS_CONCLUIDA)
            ->count();

        if ($totalTurmas > 0 && $totalTurmas === $concluidas) {
            $avaliacao->forceFill(['status' => Avaliacao::STATUS_ENCERRADA])->save();
        }
    }

    private function snapshotAtor(User $ator): array
    {
        return ['id' => (int) $ator->id, 'nome' => (string) $ator->name, 'email' => (string) $ator->email];
    }

    private function snapshotTurma(Turma $turma): array
    {
        return [
            'id' => (int) $turma->id,
            'nome' => (string) $turma->nome,
            'turno' => (string) $turma->turno,
            'escola_id' => (int) $turma->id_escola,
            'escola_nome' => (string) ($turma->escola?->nome ?? ''),
            'serie_id' => (int) ($turma->id_serie ?? 0),
            'serie_nome' => (string) ($turma->serie?->nome ?? ''),
        ];
    }

    public function hashPayload(array $payload): string
    {
        return hash('sha256', $this->jsonCanonico($payload));
    }

    private function jsonCanonico(array $payload): string
    {
        $normalizar = function (mixed $valor) use (&$normalizar): mixed {
            if (! is_array($valor)) {
                return $valor;
            }
            if (! array_is_list($valor)) {
                ksort($valor, SORT_STRING);
            }

            return array_map($normalizar, $valor);
        };

        $json = json_encode($normalizar($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return $json;
    }
}
