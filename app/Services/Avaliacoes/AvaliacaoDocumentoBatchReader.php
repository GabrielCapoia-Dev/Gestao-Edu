<?php

namespace App\Services\Avaliacoes;

use App\Data\Avaliacoes\AvaliacaoDocumentoData;
use App\Models\Aluno;
use App\Models\AvaliacaoAlunoDocumento;
use App\Models\AvaliacaoAlunoSnapshot;
use App\Models\AvaliacaoSnapshotEvento;
use App\Models\AvaliacaoRespostaOperacional;
use App\Models\AvaliacaoInformacaoOperacional;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\Professor;
use App\Models\Turma;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Le documentos avaliativos por turma usando consultas agrupadas.
 *
 * O reader individual continua existindo para os demais fluxos, mas os
 * relatórios não devem executar uma sequência de consultas por aluno.
 */
class AvaliacaoDocumentoBatchReader
{
    public function __construct(
        private readonly AvaliacaoPersistencia $persistencia,
        private readonly ParecerResponsaveisResolver $responsaveis,
    ) {
    }

    /**
     * @param  Collection<int, Aluno>  $alunos
     * @return Collection<int, AvaliacaoDocumentoData>
     */
    public function lerParaAlunos(
        int $avaliacaoId,
        Collection $alunos,
        Turma $turmaAvaliativa,
        bool $incluirResponsaveis = true,
        bool $ignorarSnapshotsAusentes = false,
    ): Collection {
        $alunos = $alunos->values();

        if ($alunos->isEmpty()) {
            return collect();
        }

        $alunoIds = $alunos->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $ciclo = $this->ciclo($avaliacaoId, (int) $turmaAvaliativa->id);

        if ($this->persistencia->leRelacional() && $ciclo?->status === AvaliacaoTurmaCiclo::STATUS_CONCLUIDA) {
            return $this->lerSnapshots($ciclo, $alunos, $ignorarSnapshotsAusentes);
        }

        $documentos = collect();
        if (! $this->persistencia->leRelacional()
            || ($ciclo && $ciclo->operacional_inicializado_em === null)) {
            $documentos = AvaliacaoAlunoDocumento::query()
                ->where('avaliacao_id', $avaliacaoId)
                ->whereIn('aluno_id', $alunoIds)
                ->get()
                ->keyBy('aluno_id');
        }

        // O modo lazy mantém o JSON como fonte de leitura até a inicialização
        // operacional terminar. Isso também evita uma consulta relacional por aluno.
        if (! $this->persistencia->leRelacional()
            || ($ciclo && $ciclo->operacional_inicializado_em === null && $documentos->isNotEmpty())) {
            return $this->mapearLegado($alunos, $documentos);
        }

        if (! $ciclo) {
            return $this->mapearLegado($alunos, $documentos);
        }

        $respostas = AvaliacaoRespostaOperacional::query()
            ->where('ciclo_id', (int) $ciclo->id)
            ->whereIn('aluno_id', $alunoIds)
            ->get()
            ->groupBy('aluno_id')
            ->map(fn (Collection $items): Collection => $items->keyBy('pauta_id'));

        $informacoes = AvaliacaoInformacaoOperacional::query()
            ->where('ciclo_id', (int) $ciclo->id)
            ->whereIn('aluno_id', $alunoIds)
            ->get()
            ->groupBy('aluno_id')
            ->map(fn (Collection $items): Collection => $items->keyBy('componente_chave'));

        $professorIds = $respostas
            ->flatten(1)
            ->pluck('professor_id')
            ->merge($informacoes->flatten(1)->pluck('professor_id'))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $professores = $professorIds->isEmpty()
            ? collect()
            : Professor::query()->whereIn('id', $professorIds->all())->pluck('nome', 'id');
        $responsaveis = $incluirResponsaveis ? $this->responsaveis->resolver($turmaAvaliativa) : [];
        $turmaAvaliativa->loadMissing('escola:id,nome', 'serie:id,nome');

        return $alunos->mapWithKeys(function (Aluno $aluno) use ($respostas, $informacoes, $professores, $responsaveis, $turmaAvaliativa): array {
            $payload = [
                'v' => 1,
                'pautas' => $respostas->get((int) $aluno->id, collect())->mapWithKeys(
                    fn ($resposta): array => [(string) $resposta->pauta_id => array_filter([
                        'pauta_id' => (int) $resposta->pauta_id,
                        'alternativa_id' => $resposta->alternativa_id ? (int) $resposta->alternativa_id : null,
                        'observacao' => $resposta->observacao,
                        'professor_id' => $resposta->professor_id ? (int) $resposta->professor_id : null,
                        'professor_nome' => (string) ($professores[(int) $resposta->professor_id] ?? ''),
                        'componente_curricular_id' => $resposta->componente_curricular_id ? (int) $resposta->componente_curricular_id : null,
                        'respondido_em' => $resposta->respondido_em?->toIso8601String(),
                    ], fn ($value) => $value !== null && $value !== '' )]
                )->all(),
                'informacoes_complementares' => $informacoes->get((int) $aluno->id, collect())->mapWithKeys(
                    fn ($info): array => [(string) ((int) ($info->componente_curricular_id ?? 0)) => array_filter([
                        'componente_curricular_id' => $info->componente_curricular_id ? (int) $info->componente_curricular_id : null,
                        'professor_id' => $info->professor_id ? (int) $info->professor_id : null,
                        'professor_nome' => (string) ($professores[(int) $info->professor_id] ?? ''),
                        'texto' => $info->texto,
                    ], fn ($value) => $value !== null && $value !== '' )]
                )->all(),
            ];

            return [(int) $aluno->id => new AvaliacaoDocumentoData(
                payload: $payload,
                responsaveisSnapshot: $responsaveis,
                contexto: [
                    'escola' => $responsaveis['escola'] ?? ['id' => (int) $turmaAvaliativa->id_escola, 'nome' => (string) ($turmaAvaliativa->escola?->nome ?? '')],
                    'turma' => $responsaveis['turma'] ?? ['id' => (int) $turmaAvaliativa->id, 'nome' => (string) $turmaAvaliativa->nome, 'turno' => (string) $turmaAvaliativa->turno],
                    'serie' => $responsaveis['serie'] ?? ['id' => (int) $turmaAvaliativa->id_serie, 'nome' => (string) ($turmaAvaliativa->serie?->nome ?? '')],
                ],
                origem: 'relacional',
            )];
        });
    }

    private function ciclo(int $avaliacaoId, int $turmaId): ?AvaliacaoTurmaCiclo
    {
        return AvaliacaoTurmaCiclo::query()
            ->where('avaliacao_id', $avaliacaoId)
            ->where('turma_avaliativa_id', $turmaId)
            ->first();
    }

    /** @param Collection<int, Aluno> $alunos */
    private function lerSnapshots(
        AvaliacaoTurmaCiclo $ciclo,
        Collection $alunos,
        bool $ignorarSnapshotsAusentes = false,
    ): Collection
    {
        $snapshots = AvaliacaoAlunoSnapshot::query()
            ->where('evento_id', (string) $ciclo->snapshot_evento_atual_id)
            ->where('tipo', AvaliacaoSnapshotEvento::TIPO_CONCLUSAO)
            ->whereIn('aluno_id', $alunos->pluck('id')->map(fn ($id): int => (int) $id)->all())
            ->get()
            ->keyBy('aluno_id');

        return $alunos->mapWithKeys(function (Aluno $aluno) use ($snapshots): array {
            $snapshot = $snapshots->get((int) $aluno->id);

            if (! $snapshot) {
                if ($ignorarSnapshotsAusentes) {
                    return [];
                }

                throw new RuntimeException('Snapshot final do aluno não encontrado.');
            }

            $payload = (array) $snapshot->payload;

            return [(int) $aluno->id => new AvaliacaoDocumentoData(
                payload: $payload,
                responsaveisSnapshot: (array) ($payload['responsaveis_snapshot'] ?? []),
                contexto: $this->contextoSnapshot($payload),
                origem: 'snapshot',
            )];
        });
    }

    /** @param Collection<int, Aluno> $alunos */
    private function mapearLegado(Collection $alunos, Collection $documentos): Collection
    {
        return $alunos->mapWithKeys(function (Aluno $aluno) use ($documentos): array {
            $documento = $documentos->get((int) $aluno->id);

            if (! $documento) {
                throw new RuntimeException('Documento avaliativo do aluno não encontrado.');
            }

            $responsaveisSnapshot = (array) ($documento->responsaveis_snapshot ?? []);

            return [(int) $aluno->id => new AvaliacaoDocumentoData(
                payload: (array) $documento->payload,
                responsaveisSnapshot: $responsaveisSnapshot,
                contexto: [
                    'escola' => (array) ($responsaveisSnapshot['escola'] ?? []),
                    'turma' => (array) ($responsaveisSnapshot['turma'] ?? []),
                    'serie' => (array) ($responsaveisSnapshot['serie'] ?? []),
                ],
                origem: 'json_legado',
            )];
        });
    }

    /** @return array<string, mixed> */
    private function contextoSnapshot(array $payload): array
    {
        $turma = (array) ($payload['turma_avaliativa'] ?? []);

        return [
            'escola' => ['id' => $turma['escola_id'] ?? null, 'nome' => $turma['escola_nome'] ?? ''],
            'turma' => ['id' => $turma['id'] ?? null, 'nome' => $turma['nome'] ?? '', 'turno' => $turma['turno'] ?? ''],
            'serie' => ['id' => $turma['serie_id'] ?? null, 'nome' => $turma['serie_nome'] ?? ''],
        ];
    }
}
