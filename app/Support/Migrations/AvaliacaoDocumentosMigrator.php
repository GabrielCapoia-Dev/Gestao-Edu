<?php

namespace App\Support\Migrations;

use App\Models\Aluno;
use App\Models\AvaliacaoAlunoDocumento;
use App\Models\AvaliacaoAlunoDocumentoHistorico;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Cutover legado (avaliacao_respostas / infos) → avaliacao_aluno_documentos.
 *
 * Otimizado para boot em produção (~100k respostas):
 * - prefetch de alunos/pautas/esperadas (sem N+1)
 * - agregação em memória por aluno×avaliação
 * - upsert em batch com métricas já materializadas
 * - idempotente e com skip quando a conversão já está completa
 */
class AvaliacaoDocumentosMigrator
{
    public const LEGACY_RESPOSTAS = 'avaliacao_respostas';

    public const LEGACY_INFORMACOES = 'avaliacao_informacoes_complementares';

    public const DOCUMENTOS = 'avaliacao_aluno_documentos';

    public const HISTORICO = 'avaliacao_aluno_documentos_historico';

    private const CHUNK_RESPOSTAS = 3000;

    private const CHUNK_INFOS = 1000;

    private const BATCH_UPSERT = 200;

    /** @var array<int, object>|null */
    private ?array $alunosMap = null;

    /** @var array<int, int|null>|null */
    private ?array $pautasComponenteMap = null;

    /** @var array<string, int>|null chave "{avaliacaoId}:{serieId|0}" */
    private ?array $pautasEsperadasMap = null;

    /**
     * @return array{documentos: int, respondidas_payload: int, legado_pares: int, legado_respostas: int, historicos: int, skipped?: bool, duration_ms?: int}
     */
    public function migrateFromLegacy(bool $force = false): array
    {
        if (! Schema::hasTable(self::DOCUMENTOS)) {
            throw new RuntimeException('Tabela '.self::DOCUMENTOS.' nao existe. Rode a migration de criacao antes.');
        }

        $started = microtime(true);

        if (! $this->hasLegacyTables()) {
            $stats = $this->stats();
            $stats['skipped'] = true;
            $stats['duration_ms'] = (int) round((microtime(true) - $started) * 1000);
            Log::info('avaliacao_documentos.migrate_skip_sem_legado', $stats);

            return $stats;
        }

        if (! $force && $this->conversaoJaCompleta()) {
            $stats = $this->stats();
            $stats['skipped'] = true;
            $stats['duration_ms'] = (int) round((microtime(true) - $started) * 1000);
            Log::info('avaliacao_documentos.migrate_skip_ja_completa', $stats);

            return $stats;
        }

        $this->warmCaches();

        $this->migrarRespostasParaDocumentos();
        $this->mesclarInformacoesComplementares();
        $this->consolidarDocumentosDeAlunosInativos();
        // Métricas já gravadas no upsert; sem loop Eloquent de recálculo.

        $this->clearCaches();

        $stats = $this->stats();
        $stats['skipped'] = false;
        $stats['duration_ms'] = (int) round((microtime(true) - $started) * 1000);
        Log::info('avaliacao_documentos.migrate_ok', $stats);

        return $stats;
    }

    /**
     * @throws RuntimeException
     */
    public function assertMigracaoConsistente(): void
    {
        if (! Schema::hasTable(self::DOCUMENTOS)) {
            throw new RuntimeException('Estrutura de documentos avaliativos incompleta.');
        }

        $stats = $this->stats();

        if (! $this->hasLegacyTables()) {
            return;
        }

        $legadoRespostas = $stats['legado_respostas'];
        $legadoPares = $stats['legado_pares'];
        $documentos = $stats['documentos'];
        $respondidas = $stats['respondidas_payload'];

        if ($legadoRespostas === 0 && $legadoPares === 0) {
            return;
        }

        if ($legadoPares > 0 && $documentos === 0) {
            throw new RuntimeException(
                'Migracao de avaliacoes incompleta: ha pares legados, mas zero documentos. Abortando drop.'
            );
        }

        if ($legadoRespostas > 100 && $respondidas < (int) floor($legadoRespostas * 0.90)) {
            throw new RuntimeException(sprintf(
                'Migracao de avaliacoes suspeita: respondidas_no_payload=%d vs respostas_legadas=%d (<90%%). Abortando drop.',
                $respondidas,
                $legadoRespostas
            ));
        }

        Log::info('avaliacao_documentos.migracao_ok', $stats);
    }

    public function dropLegacyTables(): void
    {
        Schema::dropIfExists(self::LEGACY_RESPOSTAS);
        Schema::dropIfExists(self::LEGACY_INFORMACOES);
    }

    public function hasLegacyTables(): bool
    {
        return Schema::hasTable(self::LEGACY_RESPOSTAS)
            || Schema::hasTable(self::LEGACY_INFORMACOES);
    }

    /**
     * @return array{documentos: int, respondidas_payload: int, legado_pares: int, legado_respostas: int, historicos: int}
     */
    public function stats(): array
    {
        $documentos = Schema::hasTable(self::DOCUMENTOS)
            ? (int) DB::table(self::DOCUMENTOS)->count()
            : 0;
        $respondidasPayload = Schema::hasTable(self::DOCUMENTOS)
            ? (int) DB::table(self::DOCUMENTOS)->sum('total_pautas_respondidas')
            : 0;
        $historicos = Schema::hasTable(self::HISTORICO)
            ? (int) DB::table(self::HISTORICO)->count()
            : 0;

        $legadoRespostas = 0;
        $legadoPares = 0;

        if (Schema::hasTable(self::LEGACY_RESPOSTAS)) {
            $legadoRespostas = (int) DB::table(self::LEGACY_RESPOSTAS)
                ->whereNotNull('alternativa_id')
                ->count();

            $legadoPares = (int) DB::table(self::LEGACY_RESPOSTAS)
                ->whereNotNull('alternativa_id')
                ->selectRaw("count(distinct concat(avaliacao_id, ':', aluno_id)) as total")
                ->value('total');
        }

        return [
            'documentos' => $documentos,
            'respondidas_payload' => $respondidasPayload,
            'legado_pares' => $legadoPares,
            'legado_respostas' => $legadoRespostas,
            'historicos' => $historicos,
        ];
    }

    private function conversaoJaCompleta(): bool
    {
        $stats = $this->stats();

        if ($stats['legado_respostas'] === 0 && $stats['legado_pares'] === 0) {
            return true;
        }

        if ($stats['legado_pares'] > 0 && $stats['documentos'] < $stats['legado_pares']) {
            return false;
        }

        if ($stats['legado_respostas'] > 100
            && $stats['respondidas_payload'] < (int) floor($stats['legado_respostas'] * 0.90)
        ) {
            return false;
        }

        return $stats['documentos'] > 0;
    }

    private function warmCaches(): void
    {
        $this->alunosMap = [];
        $turmas = Schema::hasTable('turmas')
            ? DB::table('turmas')->select(['id', 'id_escola', 'id_serie'])->get()->keyBy('id')
            : collect();

        DB::table('alunos')
            ->select(['id', 'cgm', 'id_turma', 'status', 'tipo_vinculo'])
            ->orderBy('id')
            ->chunkById(2000, function (Collection $rows) use ($turmas): void {
                foreach ($rows as $row) {
                    $turma = $turmas->get((int) $row->id_turma);
                    $row->id_escola = $turma->id_escola ?? null;
                    $row->id_serie = $turma->id_serie ?? null;
                    $this->alunosMap[(int) $row->id] = $row;
                }
            });

        $this->pautasComponenteMap = [];
        if (Schema::hasTable('pautas')) {
            DB::table('pautas')
                ->select(['id', 'componente_curricular_id'])
                ->orderBy('id')
                ->chunkById(2000, function (Collection $rows): void {
                    foreach ($rows as $row) {
                        $this->pautasComponenteMap[(int) $row->id] = $row->componente_curricular_id
                            ? (int) $row->componente_curricular_id
                            : null;
                    }
                });
        }

        $this->pautasEsperadasMap = [];
        if (Schema::hasTable('avaliacao_pauta') && Schema::hasTable('pautas')) {
            $rows = DB::table('avaliacao_pauta as ap')
                ->join('pautas as p', 'p.id', '=', 'ap.pauta_id')
                ->where('p.status', true)
                ->select([
                    'ap.avaliacao_id',
                    'p.serie_id',
                    DB::raw('COUNT(*) as total'),
                ])
                ->groupBy('ap.avaliacao_id', 'p.serie_id')
                ->get();

            // Contagem por avaliação com pauta sem série (válida para todas).
            $globaisPorAvaliacao = [];
            $porSerie = [];

            foreach ($rows as $row) {
                $avaliacaoId = (int) $row->avaliacao_id;
                $serieId = $row->serie_id ? (int) $row->serie_id : 0;
                $total = (int) $row->total;

                if ($serieId === 0) {
                    $globaisPorAvaliacao[$avaliacaoId] = ($globaisPorAvaliacao[$avaliacaoId] ?? 0) + $total;
                } else {
                    $porSerie[$avaliacaoId][$serieId] = ($porSerie[$avaliacaoId][$serieId] ?? 0) + $total;
                }
            }

            $avaliacaoIds = array_unique(array_merge(
                array_keys($globaisPorAvaliacao),
                array_keys($porSerie)
            ));

            foreach ($avaliacaoIds as $avaliacaoId) {
                $global = $globaisPorAvaliacao[$avaliacaoId] ?? 0;
                $this->pautasEsperadasMap[$avaliacaoId.':0'] = $global;

                foreach ($porSerie[$avaliacaoId] ?? [] as $serieId => $totalSerie) {
                    $this->pautasEsperadasMap[$avaliacaoId.':'.$serieId] = $global + $totalSerie;
                }
            }
        }
    }

    private function clearCaches(): void
    {
        $this->alunosMap = null;
        $this->pautasComponenteMap = null;
        $this->pautasEsperadasMap = null;
    }

    private function migrarRespostasParaDocumentos(): void
    {
        if (! Schema::hasTable(self::LEGACY_RESPOSTAS)) {
            return;
        }

        /** @var array<string, array<string, mixed>> $buffer chave "avaliacaoId|alunoId" */
        $buffer = [];
        $lastId = 0;

        while (true) {
            $rows = DB::table(self::LEGACY_RESPOSTAS)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(self::CHUNK_RESPOSTAS)
                ->get([
                    'id',
                    'avaliacao_id',
                    'pauta_id',
                    'turma_id',
                    'aluno_id',
                    'professor_id',
                    'alternativa_id',
                    'observacao',
                    'respondido_em',
                ]);

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $resposta) {
                $lastId = (int) $resposta->id;

                if (! $resposta->alternativa_id) {
                    continue;
                }

                $avaliacaoId = (int) $resposta->avaliacao_id;
                $alunoId = (int) $resposta->aluno_id;
                $chave = $avaliacaoId.'|'.$alunoId;

                if (! isset($buffer[$chave])) {
                    $aluno = $this->alunosMap[$alunoId] ?? null;
                    if (! $aluno) {
                        continue;
                    }

                    $turmaId = (int) ($aluno->id_turma ?: $resposta->turma_id);
                    $escolaId = (int) ($aluno->id_escola ?: 0);
                    $serieId = $aluno->id_serie ? (int) $aluno->id_serie : null;

                    if ($escolaId <= 0 && $turmaId > 0) {
                        $escolaId = (int) (DB::table('turmas')->where('id', $turmaId)->value('id_escola') ?: 0);
                    }

                    if ($escolaId <= 0 || $turmaId <= 0) {
                        continue;
                    }

                    $buffer[$chave] = [
                        'avaliacao_id' => $avaliacaoId,
                        'aluno_id' => $alunoId,
                        'cgm' => (string) ($aluno->cgm ?? ''),
                        'turma_id' => $turmaId,
                        'escola_id' => $escolaId,
                        'serie_id' => $serieId,
                        'pautas' => [],
                    ];
                }

                $pautaId = (int) $resposta->pauta_id;
                $pautaKey = (string) $pautaId;
                $componenteId = $this->pautasComponenteMap[$pautaId] ?? null;

                $buffer[$chave]['pautas'][$pautaKey] = array_filter([
                    'alternativa_id' => (int) $resposta->alternativa_id,
                    'observacao' => filled($resposta->observacao) ? trim((string) $resposta->observacao) : null,
                    'professor_id' => $resposta->professor_id ? (int) $resposta->professor_id : null,
                    'componente_curricular_id' => $componenteId,
                    'respondido_em' => $resposta->respondido_em
                        ? Carbon::parse($resposta->respondido_em)->toIso8601String()
                        : null,
                    'pauta_id' => $pautaId,
                ], fn ($value) => $value !== null && $value !== '');
            }

            // Flush periódico para não estourar memória com 100k respostas.
            if (count($buffer) >= 1500) {
                $this->flushDocumentosBuffer($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $this->flushDocumentosBuffer($buffer);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $buffer
     */
    private function flushDocumentosBuffer(array $buffer): void
    {
        if ($buffer === []) {
            return;
        }

        $pares = array_values($buffer);
        $avaliacaoIds = array_values(array_unique(array_map(fn ($item) => (int) $item['avaliacao_id'], $pares)));
        $alunoIds = array_values(array_unique(array_map(fn ($item) => (int) $item['aluno_id'], $pares)));

        $existentes = DB::table(self::DOCUMENTOS)
            ->whereIn('avaliacao_id', $avaliacaoIds)
            ->whereIn('aluno_id', $alunoIds)
            ->get()
            ->keyBy(fn ($row) => $row->avaliacao_id.'|'.$row->aluno_id);

        $now = now();
        $toInsert = [];
        $toUpdate = [];

        foreach ($pares as $item) {
            $chave = $item['avaliacao_id'].'|'.$item['aluno_id'];
            $existente = $existentes->get($chave);

            $payload = AvaliacaoAlunoDocumento::payloadVazio();
            $payload['pautas'] = $item['pautas'];

            if ($existente) {
                $payloadAtual = json_decode((string) $existente->payload, true) ?: AvaliacaoAlunoDocumento::payloadVazio();
                $payload['pautas'] = array_replace(
                    is_array($payloadAtual['pautas'] ?? null) ? $payloadAtual['pautas'] : [],
                    $item['pautas']
                );
                $payload['informacoes_complementares'] = is_array($payloadAtual['informacoes_complementares'] ?? null)
                    ? $payloadAtual['informacoes_complementares']
                    : [];
            }

            $metricas = $this->metricasDoPayload(
                $payload,
                (int) $item['avaliacao_id'],
                $item['serie_id'] !== null ? (int) $item['serie_id'] : null
            );

            $row = [
                'avaliacao_id' => (int) $item['avaliacao_id'],
                'aluno_id' => (int) $item['aluno_id'],
                'cgm' => (string) $item['cgm'],
                'turma_id' => (int) $item['turma_id'],
                'escola_id' => (int) $item['escola_id'],
                'serie_id' => $item['serie_id'],
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'alternativa_ids' => json_encode($metricas['alternativa_ids'], JSON_UNESCAPED_UNICODE),
                'professor_ids' => json_encode($metricas['professor_ids'], JSON_UNESCAPED_UNICODE),
                'pauta_ids_respondidas' => json_encode($metricas['pauta_ids_respondidas'], JSON_UNESCAPED_UNICODE),
                'total_pautas_esperadas' => $metricas['total_pautas_esperadas'],
                'total_pautas_respondidas' => $metricas['total_pautas_respondidas'],
                'total_infos_complementares' => $metricas['total_infos_complementares'],
                'status_preenchimento' => $metricas['status_preenchimento'],
                'observacoes_obrigatorias_pendentes' => $metricas['observacoes_obrigatorias_pendentes'],
                'primeira_resposta_em' => $metricas['primeira_resposta_em'],
                'ultima_resposta_em' => $metricas['ultima_resposta_em'],
                'version' => 1,
                'updated_at' => $now,
            ];

            if ($existente) {
                $row['id'] = (int) $existente->id;
                $toUpdate[] = $row;
            } else {
                $row['created_at'] = $now;
                $toInsert[] = $row;
            }
        }

        foreach (array_chunk($toInsert, self::BATCH_UPSERT) as $chunk) {
            DB::table(self::DOCUMENTOS)->insert($chunk);
        }

        foreach ($toUpdate as $row) {
            $id = $row['id'];
            unset($row['id']);
            DB::table(self::DOCUMENTOS)->where('id', $id)->update($row);
        }
    }

    private function mesclarInformacoesComplementares(): void
    {
        if (! Schema::hasTable(self::LEGACY_INFORMACOES)) {
            return;
        }

        $lastId = 0;

        while (true) {
            $rows = DB::table(self::LEGACY_INFORMACOES)
                ->where('id', '>', $lastId)
                ->whereNotNull('informacoes_complementares')
                ->orderBy('id')
                ->limit(self::CHUNK_INFOS)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            /** @var array<string, array{texto: string, professor_id: ?int, atualizado_em: string}> $infosPorDoc */
            $infosPorDoc = [];
            $contextoPorDoc = [];

            foreach ($rows as $row) {
                $lastId = (int) $row->id;
                $texto = trim((string) $row->informacoes_complementares);
                if ($texto === '') {
                    continue;
                }

                $componenteId = $row->componente_curricular_id ? (int) $row->componente_curricular_id : 0;
                if ($componenteId <= 0) {
                    continue;
                }

                $avaliacaoId = (int) $row->avaliacao_id;
                $alunoId = (int) $row->aluno_id;
                $chave = $avaliacaoId.'|'.$alunoId;

                $aluno = $this->alunosMap[$alunoId] ?? null;
                if (! $aluno || ! $aluno->id_escola) {
                    continue;
                }

                $infosPorDoc[$chave][(string) $componenteId] = array_filter([
                    'texto' => $texto,
                    'professor_id' => $row->professor_id ? (int) $row->professor_id : null,
                    'atualizado_em' => now()->toIso8601String(),
                ], fn ($value) => $value !== null && $value !== '');

                $contextoPorDoc[$chave] = [
                    'avaliacao_id' => $avaliacaoId,
                    'aluno_id' => $alunoId,
                    'cgm' => (string) ($aluno->cgm ?? ''),
                    'turma_id' => (int) $aluno->id_turma,
                    'escola_id' => (int) $aluno->id_escola,
                    'serie_id' => $aluno->id_serie ? (int) $aluno->id_serie : null,
                ];
            }

            if ($infosPorDoc === []) {
                continue;
            }

            $avaliacaoIds = array_values(array_unique(array_map(
                fn ($ctx) => (int) $ctx['avaliacao_id'],
                $contextoPorDoc
            )));
            $alunoIds = array_values(array_unique(array_map(
                fn ($ctx) => (int) $ctx['aluno_id'],
                $contextoPorDoc
            )));

            $existentes = DB::table(self::DOCUMENTOS)
                ->whereIn('avaliacao_id', $avaliacaoIds)
                ->whereIn('aluno_id', $alunoIds)
                ->get()
                ->keyBy(fn ($row) => $row->avaliacao_id.'|'.$row->aluno_id);

            $now = now();

            foreach ($infosPorDoc as $chave => $infos) {
                $ctx = $contextoPorDoc[$chave];
                $existente = $existentes->get($chave);

                if ($existente) {
                    $payload = json_decode((string) $existente->payload, true) ?: AvaliacaoAlunoDocumento::payloadVazio();
                    $payload['informacoes_complementares'] = array_replace(
                        is_array($payload['informacoes_complementares'] ?? null) ? $payload['informacoes_complementares'] : [],
                        $infos
                    );

                    $metricas = $this->metricasDoPayload(
                        $payload,
                        (int) $ctx['avaliacao_id'],
                        $ctx['serie_id'] !== null ? (int) $ctx['serie_id'] : null
                    );

                    DB::table(self::DOCUMENTOS)->where('id', $existente->id)->update([
                        'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                        'alternativa_ids' => json_encode($metricas['alternativa_ids'], JSON_UNESCAPED_UNICODE),
                        'professor_ids' => json_encode($metricas['professor_ids'], JSON_UNESCAPED_UNICODE),
                        'pauta_ids_respondidas' => json_encode($metricas['pauta_ids_respondidas'], JSON_UNESCAPED_UNICODE),
                        'total_pautas_esperadas' => $metricas['total_pautas_esperadas'],
                        'total_pautas_respondidas' => $metricas['total_pautas_respondidas'],
                        'total_infos_complementares' => $metricas['total_infos_complementares'],
                        'status_preenchimento' => $metricas['status_preenchimento'],
                        'observacoes_obrigatorias_pendentes' => $metricas['observacoes_obrigatorias_pendentes'],
                        'primeira_resposta_em' => $metricas['primeira_resposta_em'],
                        'ultima_resposta_em' => $metricas['ultima_resposta_em'],
                        'updated_at' => $now,
                    ]);

                    continue;
                }

                $payload = AvaliacaoAlunoDocumento::payloadVazio();
                $payload['informacoes_complementares'] = $infos;
                $metricas = $this->metricasDoPayload(
                    $payload,
                    (int) $ctx['avaliacao_id'],
                    $ctx['serie_id'] !== null ? (int) $ctx['serie_id'] : null
                );

                DB::table(self::DOCUMENTOS)->insert([
                    'avaliacao_id' => (int) $ctx['avaliacao_id'],
                    'aluno_id' => (int) $ctx['aluno_id'],
                    'cgm' => (string) $ctx['cgm'],
                    'turma_id' => (int) $ctx['turma_id'],
                    'escola_id' => (int) $ctx['escola_id'],
                    'serie_id' => $ctx['serie_id'],
                    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'alternativa_ids' => json_encode($metricas['alternativa_ids'], JSON_UNESCAPED_UNICODE),
                    'professor_ids' => json_encode($metricas['professor_ids'], JSON_UNESCAPED_UNICODE),
                    'pauta_ids_respondidas' => json_encode($metricas['pauta_ids_respondidas'], JSON_UNESCAPED_UNICODE),
                    'total_pautas_esperadas' => $metricas['total_pautas_esperadas'],
                    'total_pautas_respondidas' => $metricas['total_pautas_respondidas'],
                    'total_infos_complementares' => $metricas['total_infos_complementares'],
                    'status_preenchimento' => $metricas['status_preenchimento'],
                    'observacoes_obrigatorias_pendentes' => $metricas['observacoes_obrigatorias_pendentes'],
                    'primeira_resposta_em' => $metricas['primeira_resposta_em'],
                    'ultima_resposta_em' => $metricas['ultima_resposta_em'],
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function consolidarDocumentosDeAlunosInativos(): void
    {
        $statusAtivos = [Aluno::STATUS_MATRICULADO, Aluno::STATUS_PENDENTE];

        $inativos = DB::table(self::DOCUMENTOS.' as d')
            ->join('alunos as a', 'a.id', '=', 'd.aluno_id')
            ->whereNotIn('a.status', $statusAtivos)
            ->orderBy('d.id')
            ->get([
                'd.id as documento_id',
                'd.avaliacao_id',
                'd.aluno_id',
                'd.cgm',
                'd.turma_id',
                'd.escola_id',
                'd.serie_id',
                'd.payload',
                'd.total_pautas_respondidas',
                'd.total_infos_complementares',
                'd.total_pautas_esperadas',
                'd.status_preenchimento',
                'd.alternativa_ids',
                'a.status as aluno_status',
            ]);

        if ($inativos->isEmpty()) {
            return;
        }

        // Mapa CGM → aluno ativo principal (1 query).
        $cgms = $inativos->pluck('cgm')->filter()->unique()->values()->all();
        $ativosPorCgm = DB::table('alunos')
            ->whereIn('cgm', $cgms)
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
            ->whereIn('status', $statusAtivos)
            ->orderByDesc('id')
            ->get()
            ->groupBy('cgm')
            ->map(fn (Collection $grupo) => $grupo->first());

        $turmaIds = $ativosPorCgm->pluck('id_turma')->filter()->unique()->values()->all();
        $turmas = $turmaIds === []
            ? collect()
            : DB::table('turmas')->whereIn('id', $turmaIds)->get()->keyBy('id');

        foreach ($inativos as $doc) {
            $cgm = (string) $doc->cgm;
            $avaliacaoId = (int) $doc->avaliacao_id;
            $ativo = $ativosPorCgm->get($cgm);

            if (! $ativo) {
                continue;
            }

            $docAtivo = DB::table(self::DOCUMENTOS)
                ->where('avaliacao_id', $avaliacaoId)
                ->where('aluno_id', (int) $ativo->id)
                ->first();

            $movimentacaoTipo = $doc->aluno_status === Aluno::STATUS_TRANSFERIDO
                ? AvaliacaoAlunoDocumentoHistorico::MOVIMENTACAO_TRANSFERENCIA
                : AvaliacaoAlunoDocumentoHistorico::MOVIMENTACAO_REMANEJAMENTO;

            if (Schema::hasTable(self::HISTORICO)) {
                DB::table(self::HISTORICO)->insert([
                    'avaliacao_id' => $avaliacaoId,
                    'documento_id' => $docAtivo->id ?? null,
                    'aluno_origem_id' => (int) $doc->aluno_id,
                    'aluno_destino_id' => (int) $ativo->id,
                    'cgm' => $cgm,
                    'turma_id' => (int) $doc->turma_id,
                    'escola_id' => (int) $doc->escola_id,
                    'serie_id' => $doc->serie_id ? (int) $doc->serie_id : null,
                    'movimentacao_tipo' => $movimentacaoTipo,
                    'payload' => $doc->payload,
                    'alternativa_ids' => $doc->alternativa_ids,
                    'total_pautas_esperadas' => (int) $doc->total_pautas_esperadas,
                    'total_pautas_respondidas' => (int) $doc->total_pautas_respondidas,
                    'total_infos_complementares' => (int) $doc->total_infos_complementares,
                    'status_preenchimento' => (string) $doc->status_preenchimento,
                    'movimentado_em' => now(),
                    'movimentado_por' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $payloadInativo = json_decode((string) $doc->payload, true) ?: AvaliacaoAlunoDocumento::payloadVazio();

            if ($docAtivo) {
                $payloadAtivo = json_decode((string) $docAtivo->payload, true) ?: AvaliacaoAlunoDocumento::payloadVazio();
                $payloadAtivo['pautas'] = array_replace(
                    is_array($payloadAtivo['pautas'] ?? null) ? $payloadAtivo['pautas'] : [],
                    is_array($payloadInativo['pautas'] ?? null) ? $payloadInativo['pautas'] : []
                );
                $payloadAtivo['informacoes_complementares'] = array_replace(
                    is_array($payloadAtivo['informacoes_complementares'] ?? null) ? $payloadAtivo['informacoes_complementares'] : [],
                    is_array($payloadInativo['informacoes_complementares'] ?? null) ? $payloadInativo['informacoes_complementares'] : []
                );

                $serieId = $docAtivo->serie_id ? (int) $docAtivo->serie_id : null;
                $metricas = $this->metricasDoPayload($payloadAtivo, $avaliacaoId, $serieId);

                DB::table(self::DOCUMENTOS)
                    ->where('id', $docAtivo->id)
                    ->update([
                        'payload' => json_encode($payloadAtivo, JSON_UNESCAPED_UNICODE),
                        'alternativa_ids' => json_encode($metricas['alternativa_ids'], JSON_UNESCAPED_UNICODE),
                        'professor_ids' => json_encode($metricas['professor_ids'], JSON_UNESCAPED_UNICODE),
                        'pauta_ids_respondidas' => json_encode($metricas['pauta_ids_respondidas'], JSON_UNESCAPED_UNICODE),
                        'total_pautas_esperadas' => $metricas['total_pautas_esperadas'],
                        'total_pautas_respondidas' => $metricas['total_pautas_respondidas'],
                        'total_infos_complementares' => $metricas['total_infos_complementares'],
                        'status_preenchimento' => $metricas['status_preenchimento'],
                        'observacoes_obrigatorias_pendentes' => $metricas['observacoes_obrigatorias_pendentes'],
                        'primeira_resposta_em' => $metricas['primeira_resposta_em'],
                        'ultima_resposta_em' => $metricas['ultima_resposta_em'],
                        'updated_at' => now(),
                    ]);

                DB::table(self::DOCUMENTOS)->where('id', $doc->documento_id)->delete();
            } else {
                $turmaAtivo = $turmas->get((int) $ativo->id_turma);

                DB::table(self::DOCUMENTOS)
                    ->where('id', $doc->documento_id)
                    ->update([
                        'aluno_id' => (int) $ativo->id,
                        'cgm' => (string) $ativo->cgm,
                        'turma_id' => (int) $ativo->id_turma,
                        'escola_id' => (int) ($turmaAtivo->id_escola ?? $doc->escola_id),
                        'serie_id' => $turmaAtivo->id_serie ?? $doc->serie_id,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    /**
     * @param  array{v?: int, pautas?: array, informacoes_complementares?: array}  $payload
     * @return array{
     *     alternativa_ids: array<int, int>,
     *     professor_ids: array<int, int>,
     *     pauta_ids_respondidas: array<int, int>,
     *     total_pautas_esperadas: int,
     *     total_pautas_respondidas: int,
     *     total_infos_complementares: int,
     *     status_preenchimento: string,
     *     observacoes_obrigatorias_pendentes: int,
     *     primeira_resposta_em: ?string,
     *     ultima_resposta_em: ?string
     * }
     */
    private function metricasDoPayload(array $payload, int $avaliacaoId, ?int $serieId): array
    {
        $pautas = is_array($payload['pautas'] ?? null) ? $payload['pautas'] : [];
        $infos = is_array($payload['informacoes_complementares'] ?? null) ? $payload['informacoes_complementares'] : [];

        $alternativaIds = [];
        $professorIds = [];
        $pautaIdsRespondidas = [];
        $datas = [];

        foreach ($pautas as $pautaKey => $resposta) {
            if (! is_array($resposta) || empty($resposta['alternativa_id'])) {
                continue;
            }

            $pautaId = (int) ($resposta['pauta_id'] ?? $pautaKey);
            if ($pautaId <= 0) {
                continue;
            }

            $pautaIdsRespondidas[] = $pautaId;
            $alternativaIds[] = (int) $resposta['alternativa_id'];

            if (! empty($resposta['professor_id'])) {
                $professorIds[] = (int) $resposta['professor_id'];
            }

            if (! empty($resposta['respondido_em'])) {
                try {
                    $datas[] = Carbon::parse((string) $resposta['respondido_em'])->toDateTimeString();
                } catch (Throwable) {
                    // ignora data inválida do legado
                }
            }
        }

        foreach ($infos as $info) {
            if (! is_array($info)) {
                continue;
            }
            if (! empty($info['professor_id'])) {
                $professorIds[] = (int) $info['professor_id'];
            }
        }

        $alternativaIds = array_values(array_unique($alternativaIds));
        $professorIds = array_values(array_unique($professorIds));
        $pautaIdsRespondidas = array_values(array_unique($pautaIdsRespondidas));

        $totalRespondidas = count($pautaIdsRespondidas);
        $totalEsperadas = $this->totalPautasEsperadas($avaliacaoId, $serieId);
        $totalInfos = count(array_filter(
            $infos,
            fn ($item) => is_array($item) && filled($item['texto'] ?? null)
        ));

        $status = AvaliacaoAlunoDocumento::STATUS_VAZIO;
        if ($totalRespondidas > 0 && $totalEsperadas > 0 && $totalRespondidas >= $totalEsperadas) {
            $status = AvaliacaoAlunoDocumento::STATUS_COMPLETO;
        } elseif ($totalRespondidas > 0 || $totalInfos > 0) {
            $status = AvaliacaoAlunoDocumento::STATUS_PARCIAL;
        }

        $primeira = $datas !== [] ? min($datas) : null;
        $ultima = $datas !== [] ? max($datas) : null;

        return [
            'alternativa_ids' => $alternativaIds,
            'professor_ids' => $professorIds,
            'pauta_ids_respondidas' => $pautaIdsRespondidas,
            'total_pautas_esperadas' => $totalEsperadas,
            'total_pautas_respondidas' => $totalRespondidas,
            'total_infos_complementares' => $totalInfos,
            'status_preenchimento' => $status,
            'observacoes_obrigatorias_pendentes' => 0,
            'primeira_resposta_em' => $primeira,
            'ultima_resposta_em' => $ultima,
        ];
    }

    private function totalPautasEsperadas(int $avaliacaoId, ?int $serieId): int
    {
        $serieKey = $serieId ? (int) $serieId : 0;
        $map = $this->pautasEsperadasMap ?? [];

        if ($serieKey > 0 && isset($map[$avaliacaoId.':'.$serieKey])) {
            return (int) $map[$avaliacaoId.':'.$serieKey];
        }

        return (int) ($map[$avaliacaoId.':0'] ?? 0);
    }
}
