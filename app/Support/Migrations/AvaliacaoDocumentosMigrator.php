<?php

namespace App\Support\Migrations;

use App\Models\Aluno;
use App\Models\AvaliacaoAlunoDocumento;
use App\Models\AvaliacaoAlunoDocumentoHistorico;
use App\Services\Avaliacoes\AvaliacaoAlunoDocumentoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Migração segura legado (avaliacao_respostas / infos) → documentos (payload).
 * Idempotente: pode rodar mais de uma vez sem duplicar dono de ficha.
 */
class AvaliacaoDocumentosMigrator
{
    public const LEGACY_RESPOSTAS = 'avaliacao_respostas';

    public const LEGACY_INFORMACOES = 'avaliacao_informacoes_complementares';

    public const DOCUMENTOS = 'avaliacao_aluno_documentos';

    public const HISTORICO = 'avaliacao_aluno_documentos_historico';

    /**
     * @return array{documentos: int, respondidas_payload: int, legado_pares: int, legado_respostas: int, historicos: int}
     */
    public function migrateFromLegacy(): array
    {
        if (! Schema::hasTable(self::DOCUMENTOS)) {
            throw new RuntimeException('Tabela '.self::DOCUMENTOS.' nao existe. Rode a migration de criacao antes.');
        }

        // Se já há documentos e não há legado, só recalcula métricas (boot rápido).
        if (! $this->hasLegacyTables()) {
            if ((int) DB::table(self::DOCUMENTOS)->count() > 0) {
                $this->recalcularTodosDocumentos();
            }

            return $this->stats();
        }

        $this->migrarRespostasParaDocumentos();
        $this->mesclarInformacoesComplementares();
        $this->consolidarDocumentosDeAlunosInativos();
        $this->recalcularTodosDocumentos();

        return $this->stats();
    }

    /**
     * Garante que o estado novo e coerente com o legado (quando ainda existir).
     *
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
        // Ordem: filhos sem dependencia externa critica.
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

            // SQLite nao aceita COUNT(DISTINCT col1, col2); MySQL sim.
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

    private function migrarRespostasParaDocumentos(): void
    {
        if (! Schema::hasTable(self::LEGACY_RESPOSTAS)) {
            return;
        }

        DB::table(self::LEGACY_RESPOSTAS)
            ->orderBy('aluno_id')
            ->orderBy('avaliacao_id')
            ->orderBy('id')
            ->chunk(500, function (Collection $rows): void {
                $grupos = $rows->groupBy(fn ($row) => $row->avaliacao_id.'|'.$row->aluno_id);

                foreach ($grupos as $grupo) {
                    $primeira = $grupo->first();
                    $avaliacaoId = (int) $primeira->avaliacao_id;
                    $alunoId = (int) $primeira->aluno_id;

                    $aluno = DB::table('alunos as a')
                        ->leftJoin('turmas as t', 't.id', '=', 'a.id_turma')
                        ->where('a.id', $alunoId)
                        ->first([
                            'a.id',
                            'a.cgm',
                            'a.id_turma',
                            't.id_escola',
                            't.id_serie',
                        ]);

                    if (! $aluno) {
                        continue;
                    }

                    $turmaId = (int) ($aluno->id_turma ?: $primeira->turma_id);
                    $escolaId = (int) ($aluno->id_escola ?: 0);
                    $serieId = $aluno->id_serie ? (int) $aluno->id_serie : null;

                    if ($escolaId <= 0) {
                        $escolaId = (int) (DB::table('turmas')->where('id', $turmaId)->value('id_escola') ?: 0);
                    }

                    if ($escolaId <= 0 || $turmaId <= 0) {
                        continue;
                    }

                    $payload = AvaliacaoAlunoDocumento::payloadVazio();

                    foreach ($grupo as $resposta) {
                        if (! $resposta->alternativa_id) {
                            continue;
                        }

                        $pautaId = (string) (int) $resposta->pauta_id;
                        $componenteId = DB::table('pautas')
                            ->where('id', (int) $resposta->pauta_id)
                            ->value('componente_curricular_id');

                        $payload['pautas'][$pautaId] = array_filter([
                            'alternativa_id' => (int) $resposta->alternativa_id,
                            'observacao' => filled($resposta->observacao) ? trim((string) $resposta->observacao) : null,
                            'professor_id' => $resposta->professor_id ? (int) $resposta->professor_id : null,
                            'componente_curricular_id' => $componenteId ? (int) $componenteId : null,
                            'respondido_em' => $resposta->respondido_em
                                ? \Carbon\Carbon::parse($resposta->respondido_em)->toIso8601String()
                                : null,
                        ], fn ($value) => $value !== null && $value !== '');
                    }

                    $this->upsertDocumentoPayload(
                        $avaliacaoId,
                        $alunoId,
                        (string) $aluno->cgm,
                        $turmaId,
                        $escolaId,
                        $serieId,
                        $payload,
                        mergePautas: true
                    );
                }
            });
    }

    private function mesclarInformacoesComplementares(): void
    {
        if (! Schema::hasTable(self::LEGACY_INFORMACOES)) {
            return;
        }

        DB::table(self::LEGACY_INFORMACOES)
            ->whereNotNull('informacoes_complementares')
            ->orderBy('id')
            ->chunkById(500, function (Collection $rows): void {
                foreach ($rows as $row) {
                    $texto = trim((string) $row->informacoes_complementares);
                    if ($texto === '') {
                        continue;
                    }

                    $avaliacaoId = (int) $row->avaliacao_id;
                    $alunoId = (int) $row->aluno_id;
                    $componenteId = $row->componente_curricular_id ? (int) $row->componente_curricular_id : 0;

                    if ($componenteId <= 0) {
                        continue;
                    }

                    $aluno = DB::table('alunos as a')
                        ->leftJoin('turmas as t', 't.id', '=', 'a.id_turma')
                        ->where('a.id', $alunoId)
                        ->first(['a.cgm', 'a.id_turma', 't.id_escola', 't.id_serie']);

                    if (! $aluno || ! $aluno->id_escola) {
                        continue;
                    }

                    $payload = AvaliacaoAlunoDocumento::payloadVazio();
                    $payload['informacoes_complementares'][(string) $componenteId] = array_filter([
                        'texto' => $texto,
                        'professor_id' => $row->professor_id ? (int) $row->professor_id : null,
                        'atualizado_em' => now()->toIso8601String(),
                    ], fn ($value) => $value !== null && $value !== '');

                    $this->upsertDocumentoPayload(
                        $avaliacaoId,
                        $alunoId,
                        (string) $aluno->cgm,
                        (int) $aluno->id_turma,
                        (int) $aluno->id_escola,
                        $aluno->id_serie ? (int) $aluno->id_serie : null,
                        $payload,
                        mergeInfos: true
                    );
                }
            });
    }

    private function consolidarDocumentosDeAlunosInativos(): void
    {
        $inativos = DB::table(self::DOCUMENTOS.' as d')
            ->join('alunos as a', 'a.id', '=', 'd.aluno_id')
            ->whereNotIn('a.status', [Aluno::STATUS_MATRICULADO, Aluno::STATUS_PENDENTE])
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

        foreach ($inativos as $doc) {
            $cgm = (string) $doc->cgm;
            $avaliacaoId = (int) $doc->avaliacao_id;

            $ativo = DB::table('alunos')
                ->where('cgm', $cgm)
                ->where('tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
                ->whereIn('status', [Aluno::STATUS_MATRICULADO, Aluno::STATUS_PENDENTE])
                ->orderByDesc('id')
                ->first();

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

                DB::table(self::DOCUMENTOS)
                    ->where('id', $docAtivo->id)
                    ->update([
                        'payload' => json_encode($payloadAtivo, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);

                DB::table(self::DOCUMENTOS)->where('id', $doc->documento_id)->delete();
            } else {
                $turmaAtivo = DB::table('turmas')->where('id', (int) $ativo->id_turma)->first();

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

    private function recalcularTodosDocumentos(): void
    {
        try {
            /** @var AvaliacaoAlunoDocumentoService $service */
            $service = app(AvaliacaoAlunoDocumentoService::class);
        } catch (Throwable $e) {
            Log::warning('avaliacao_documentos.recalculo_indisponivel', ['error' => $e->getMessage()]);

            return;
        }

        AvaliacaoAlunoDocumento::query()
            ->orderBy('id')
            ->chunkById(100, function (Collection $documentos) use ($service): void {
                foreach ($documentos as $documento) {
                    $service->recalcularMetricas($documento);
                }
            });
    }

    private function upsertDocumentoPayload(
        int $avaliacaoId,
        int $alunoId,
        string $cgm,
        int $turmaId,
        int $escolaId,
        ?int $serieId,
        array $payloadNovo,
        bool $mergePautas = false,
        bool $mergeInfos = false,
    ): void {
        $existente = DB::table(self::DOCUMENTOS)
            ->where('avaliacao_id', $avaliacaoId)
            ->where('aluno_id', $alunoId)
            ->first();

        if ($existente) {
            $payloadAtual = json_decode((string) $existente->payload, true) ?: AvaliacaoAlunoDocumento::payloadVazio();

            if ($mergePautas) {
                $payloadAtual['pautas'] = array_replace(
                    is_array($payloadAtual['pautas'] ?? null) ? $payloadAtual['pautas'] : [],
                    is_array($payloadNovo['pautas'] ?? null) ? $payloadNovo['pautas'] : []
                );
            }

            if ($mergeInfos) {
                $payloadAtual['informacoes_complementares'] = array_replace(
                    is_array($payloadAtual['informacoes_complementares'] ?? null) ? $payloadAtual['informacoes_complementares'] : [],
                    is_array($payloadNovo['informacoes_complementares'] ?? null) ? $payloadNovo['informacoes_complementares'] : []
                );
            }

            if (! $mergePautas && ! $mergeInfos) {
                $payloadAtual = $payloadNovo;
            }

            DB::table(self::DOCUMENTOS)
                ->where('id', $existente->id)
                ->update([
                    'payload' => json_encode($payloadAtual, JSON_UNESCAPED_UNICODE),
                    'cgm' => $cgm,
                    'turma_id' => $turmaId,
                    'escola_id' => $escolaId,
                    'serie_id' => $serieId,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table(self::DOCUMENTOS)->insert([
            'avaliacao_id' => $avaliacaoId,
            'aluno_id' => $alunoId,
            'cgm' => $cgm,
            'turma_id' => $turmaId,
            'escola_id' => $escolaId,
            'serie_id' => $serieId,
            'payload' => json_encode($payloadNovo, JSON_UNESCAPED_UNICODE),
            'alternativa_ids' => json_encode([]),
            'professor_ids' => json_encode([]),
            'pauta_ids_respondidas' => json_encode([]),
            'total_pautas_esperadas' => 0,
            'total_pautas_respondidas' => 0,
            'total_infos_complementares' => 0,
            'status_preenchimento' => AvaliacaoAlunoDocumento::STATUS_VAZIO,
            'observacoes_obrigatorias_pendentes' => 0,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
