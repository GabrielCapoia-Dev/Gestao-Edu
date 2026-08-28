<?php

namespace App\Console\Commands;

use App\Models\AvaliacaoAlunoDocumento;
use App\Models\AvaliacaoInformacaoOperacional;
use App\Models\AvaliacaoRespostaOperacional;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\Turma;
use App\Services\Avaliacoes\AvaliacaoTurmaCicloService;
use App\Services\Avaliacoes\TurmaAvaliacaoAlunoScopeService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class MigrarAvaliacaoDocumentosOperacionais extends Command
{
    protected $signature = 'avaliacoes:migrar-documentos-operacionais
        {--avaliacao= : Restringe a uma avaliação}
        {--chunk=200 : Quantidade por lote}
        {--limit=0 : Limite total desta execução}
        {--checkpoint=default : Chave do checkpoint}
        {--fresh : Ignora o checkpoint sem apagar dados}
        {--dry-run : Valida sem gravar tabelas operacionais nem checkpoint}';

    protected $description = 'Decompõe documentos JSON em respostas relacionais operacionais com checkpoint e quarentena';

    public function handle(AvaliacaoTurmaCicloService $ciclos, TurmaAvaliacaoAlunoScopeService $escopos): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, min(1000, (int) $this->option('chunk')));
        $limit = max(0, (int) $this->option('limit'));
        $avaliacaoId = (int) ($this->option('avaliacao') ?: 0);
        $chave = 'avaliacao_documentos:'.($this->option('checkpoint') ?: 'default').':'.($avaliacaoId ?: 'todas');
        $checkpoint = $this->option('fresh') || $dryRun
            ? null
            : DB::table('avaliacao_migracao_checkpoints')->where('chave', $chave)->first();
        $ultimoId = (int) ($checkpoint->ultimo_documento_id ?? 0);
        $anteriores = [
            'processados' => (int) ($checkpoint->processados ?? 0),
            'migrados' => (int) ($checkpoint->migrados ?? 0),
            'inconsistentes' => (int) ($checkpoint->inconsistentes ?? 0),
        ];
        $stats = ['processados' => 0, 'migrados' => 0, 'inconsistentes' => 0];

        $query = AvaliacaoAlunoDocumento::query()
            ->where('id', '>', $ultimoId)
            ->when($avaliacaoId > 0, fn ($query) => $query->where('avaliacao_id', $avaliacaoId))
            ->with('aluno:id,nome,cgm,id_turma,status,tipo_vinculo')
            ->orderBy('id');

        $query->chunkById($chunk, function (Collection $documentos) use (
            $ciclos, $escopos, $dryRun, $limit, $chave, $anteriores, &$stats,
        ): bool {
            foreach ($documentos as $documento) {
                if ($limit > 0 && $stats['processados'] >= $limit) {
                    return false;
                }

                $stats['processados']++;
                $hashSemantico = null;
                try {
                    $contexto = $this->resolverContexto($documento, $escopos);
                    $this->validarDocumento($documento);
                    $hashSemantico = $this->hashSemantico($documento);

                    if (! $dryRun) {
                        $this->migrarDocumento($documento, $contexto, $ciclos);
                    }
                    $stats['migrados']++;
                } catch (Throwable $exception) {
                    $stats['inconsistentes']++;
                    if (! $dryRun) {
                        $this->registrarInconsistencia($documento, $exception);
                    }
                    $this->warn("Documento {$documento->id}: {$exception->getMessage()}");
                }

                if (! $dryRun) {
                    DB::table('avaliacao_migracao_checkpoints')->updateOrInsert(
                        ['chave' => $chave],
                        [
                            'ultimo_documento_id' => (int) $documento->id,
                            'processados' => $anteriores['processados'] + $stats['processados'],
                            'migrados' => $anteriores['migrados'] + $stats['migrados'],
                            'inconsistentes' => $anteriores['inconsistentes'] + $stats['inconsistentes'],
                            'metadata' => json_encode([
                                'atualizado_em' => now()->toIso8601String(),
                                'ultimo_hash_semantico' => $hashSemantico,
                            ], JSON_THROW_ON_ERROR),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            }

            return ! ($limit > 0 && $stats['processados'] >= $limit);
        });

        $this->table(['Modo', 'Processados', 'Migrados', 'Inconsistentes'], [[
            $dryRun ? 'dry-run' : 'escrita',
            $stats['processados'],
            $stats['migrados'],
            $stats['inconsistentes'],
        ]]);

        return $stats['inconsistentes'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return array{turma_avaliativa_id:int,turma_origem_id:int} */
    private function resolverContexto(AvaliacaoAlunoDocumento $documento, TurmaAvaliacaoAlunoScopeService $service): array
    {
        $turmas = Turma::query()
            ->whereIn('id', DB::table('avaliacao_turma')
                ->where('avaliacao_id', (int) $documento->avaliacao_id)
                ->select('turma_id'))
            ->get();
        $escopos = $service->escoposPorTurma($turmas);
        $candidatas = collect($escopos)
            ->filter(fn (array $escopo): bool => (int) $escopo['turma_origem_id'] === (int) $documento->turma_id)
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($candidatas->count() !== 1) {
            throw new RuntimeException($candidatas->isEmpty()
                ? 'Nenhuma turma avaliativa corresponde à turma física do documento.'
                : 'Mais de uma turma avaliativa corresponde à turma física; o caso exige resolução manual.');
        }

        return ['turma_avaliativa_id' => (int) $candidatas->first(), 'turma_origem_id' => (int) $documento->turma_id];
    }

    private function validarDocumento(AvaliacaoAlunoDocumento $documento): void
    {
        if (! $documento->aluno) {
            throw new RuntimeException('Aluno do documento não encontrado.');
        }

        $pautas = collect($documento->pautasPayload());
        $pautaIds = $pautas->keys()->map(fn ($id): int => (int) $id)->all();
        $validas = $pautaIds === [] ? 0 : DB::table('avaliacao_pauta')
            ->where('avaliacao_id', (int) $documento->avaliacao_id)
            ->whereIn('pauta_id', $pautaIds)
            ->count();

        if ($validas !== count($pautaIds)) {
            throw new RuntimeException('O payload contém pautas que não pertencem à avaliação.');
        }

        $alternativaIds = $pautas->pluck('alternativa_id')->filter()->map(fn ($id): int => (int) $id)->unique()->all();
        if ($alternativaIds !== [] && DB::table('alternativas')->whereIn('id', $alternativaIds)->count() !== count($alternativaIds)) {
            throw new RuntimeException('O payload contém alternativas inexistentes.');
        }
    }

    /** @param array{turma_avaliativa_id:int,turma_origem_id:int} $contexto */
    private function migrarDocumento(AvaliacaoAlunoDocumento $documento, array $contexto, AvaliacaoTurmaCicloService $ciclos): void
    {
        DB::transaction(function () use ($documento, $contexto, $ciclos): void {
            $ciclo = $ciclos->obterOuCriar(
                (int) $documento->avaliacao_id,
                $contexto['turma_avaliativa_id'],
                $contexto['turma_origem_id'],
            );
            $tokenId = (int) $ciclo->tokenEscrita()->value('id');
            $agora = now();

            foreach ($documento->pautasPayload() as $pautaId => $item) {
                if (! is_array($item) || empty($item['alternativa_id'])) {
                    continue;
                }
                AvaliacaoRespostaOperacional::query()->updateOrCreate(
                    ['ciclo_id' => (int) $ciclo->id, 'aluno_id' => (int) $documento->aluno_id, 'pauta_id' => (int) $pautaId],
                    [
                        'token_escrita_id' => $tokenId,
                        'avaliacao_id' => (int) $documento->avaliacao_id,
                        'turma_avaliativa_id' => $contexto['turma_avaliativa_id'],
                        'turma_origem_id' => $contexto['turma_origem_id'],
                        'componente_curricular_id' => $item['componente_curricular_id'] ?? null,
                        'professor_id' => $item['professor_id'] ?? null,
                        'alternativa_id' => (int) $item['alternativa_id'],
                        'observacao' => $item['observacao'] ?? null,
                        'respondido_em' => $item['respondido_em'] ?? $documento->ultima_resposta_em ?? $agora,
                        'version' => max(1, (int) ($item['version'] ?? 1)),
                    ],
                );
            }

            foreach ($documento->informacoesComplementaresPayload() as $componenteId => $item) {
                if (! is_array($item) || trim((string) ($item['texto'] ?? '')) === '') {
                    continue;
                }
                AvaliacaoInformacaoOperacional::query()->updateOrCreate(
                    ['ciclo_id' => (int) $ciclo->id, 'aluno_id' => (int) $documento->aluno_id, 'componente_chave' => (int) $componenteId],
                    [
                        'token_escrita_id' => $tokenId,
                        'avaliacao_id' => (int) $documento->avaliacao_id,
                        'turma_avaliativa_id' => $contexto['turma_avaliativa_id'],
                        'turma_origem_id' => $contexto['turma_origem_id'],
                        'componente_curricular_id' => (int) $componenteId > 0 ? (int) $componenteId : null,
                        'professor_id' => $item['professor_id'] ?? null,
                        'texto' => trim((string) $item['texto']),
                        'version' => max(1, (int) ($item['version'] ?? 1)),
                    ],
                );
            }
        }, 3);
    }

    private function registrarInconsistencia(AvaliacaoAlunoDocumento $documento, Throwable $exception): void
    {
        $codigo = str_contains($exception->getMessage(), 'Mais de uma turma') ? 'turma_ambigua' : 'documento_invalido';
        DB::table('avaliacao_migracao_inconsistencias')->updateOrInsert(
            ['documento_id' => (int) $documento->id, 'codigo' => $codigo],
            [
                'avaliacao_id' => (int) $documento->avaliacao_id,
                'aluno_id' => (int) $documento->aluno_id,
                'mensagem' => $exception->getMessage(),
                'contexto' => json_encode(['turma_documento_id' => $documento->turma_id], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function hashSemantico(AvaliacaoAlunoDocumento $documento): string
    {
        $pautas = collect($documento->pautasPayload())
            ->map(fn (array $item): array => [
                'alternativa_id' => ! empty($item['alternativa_id']) ? (int) $item['alternativa_id'] : null,
                'observacao' => $this->normalizarTexto($item['observacao'] ?? null),
                'professor_id' => ! empty($item['professor_id']) ? (int) $item['professor_id'] : null,
                'componente_curricular_id' => ! empty($item['componente_curricular_id']) ? (int) $item['componente_curricular_id'] : null,
            ])
            ->sortKeys()
            ->all();
        $informacoes = collect($documento->informacoesComplementaresPayload())
            ->map(fn (array $item): array => [
                'texto' => $this->normalizarTexto($item['texto'] ?? null),
                'professor_id' => ! empty($item['professor_id']) ? (int) $item['professor_id'] : null,
            ])
            ->sortKeys()
            ->all();

        return hash('sha256', json_encode([
            'avaliacao_id' => (int) $documento->avaliacao_id,
            'aluno_id' => (int) $documento->aluno_id,
            'turma_id' => (int) $documento->turma_id,
            'pautas' => $pautas,
            'informacoes' => $informacoes,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function normalizarTexto(mixed $valor): ?string
    {
        $valor = trim((string) ($valor ?? ''));

        return $valor !== '' ? $valor : null;
    }
}
