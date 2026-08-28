<?php

namespace App\Services\Avaliacoes;

use App\Data\Avaliacoes\AvaliacaoDocumentoData;
use App\Models\Aluno;
use App\Models\AvaliacaoAlunoDocumento;
use App\Models\AvaliacaoAlunoSnapshot;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\Professor;
use App\Models\Turma;
use RuntimeException;

class AvaliacaoDocumentoReader
{
    public function __construct(
        private readonly AvaliacaoPersistencia $persistencia,
        private readonly ParecerResponsaveisResolver $responsaveis,
    ) {
    }

    public function ler(int $avaliacaoId, Aluno $aluno, Turma $turmaAvaliativa, bool $incluirResponsaveis = true): AvaliacaoDocumentoData
    {
        if ($this->persistencia->leRelacional()) {
            $ciclo = AvaliacaoTurmaCiclo::query()
                ->where('avaliacao_id', $avaliacaoId)
                ->where('turma_avaliativa_id', (int) $turmaAvaliativa->id)
                ->first();

            if ($ciclo?->status === AvaliacaoTurmaCiclo::STATUS_CONCLUIDA && $ciclo->snapshot_evento_atual_id) {
                $snapshot = AvaliacaoAlunoSnapshot::query()
                    ->where('evento_id', $ciclo->snapshot_evento_atual_id)
                    ->where('aluno_id', (int) $aluno->id)
                    ->first();

                if (! $snapshot) {
                    throw new RuntimeException('Snapshot final do aluno não encontrado.');
                }

                $payload = (array) $snapshot->payload;

                return new AvaliacaoDocumentoData(
                    payload: $payload,
                    responsaveisSnapshot: (array) ($payload['responsaveis_snapshot'] ?? []),
                    contexto: $this->contextoSnapshot($payload),
                    origem: 'snapshot',
                );
            }

            if ($ciclo) {
                $respostas = $ciclo->respostas()
                    ->where('aluno_id', (int) $aluno->id)
                    ->get();
                $informacoes = $ciclo->informacoes()
                    ->where('aluno_id', (int) $aluno->id)
                    ->get();
                $professores = Professor::query()
                    ->whereIn('id', $respostas->pluck('professor_id')->merge($informacoes->pluck('professor_id'))->filter()->unique())
                    ->pluck('nome', 'id');
                $payload = [
                    'v' => 1,
                    'pautas' => $respostas->mapWithKeys(fn ($resposta): array => [(string) $resposta->pauta_id => array_filter([
                        'pauta_id' => (int) $resposta->pauta_id,
                        'alternativa_id' => $resposta->alternativa_id ? (int) $resposta->alternativa_id : null,
                        'observacao' => $resposta->observacao,
                        'professor_id' => $resposta->professor_id ? (int) $resposta->professor_id : null,
                        'professor_nome' => (string) ($professores[(int) $resposta->professor_id] ?? ''),
                        'componente_curricular_id' => $resposta->componente_curricular_id ? (int) $resposta->componente_curricular_id : null,
                        'respondido_em' => $resposta->respondido_em?->toIso8601String(),
                    ], fn ($valor) => $valor !== null && $valor !== '')])->all(),
                    'informacoes_complementares' => $informacoes->mapWithKeys(fn ($info): array => [(string) ((int) ($info->componente_curricular_id ?? 0)) => array_filter([
                        'componente_curricular_id' => $info->componente_curricular_id ? (int) $info->componente_curricular_id : null,
                        'professor_id' => $info->professor_id ? (int) $info->professor_id : null,
                        'professor_nome' => (string) ($professores[(int) $info->professor_id] ?? ''),
                        'texto' => $info->texto,
                    ], fn ($valor) => $valor !== null && $valor !== '')])->all(),
                ];
                $turmaAvaliativa->loadMissing('escola:id,nome', 'serie:id,nome');
                $responsaveis = $incluirResponsaveis ? $this->responsaveis->resolver($turmaAvaliativa) : [];

                return new AvaliacaoDocumentoData(
                    payload: $payload,
                    responsaveisSnapshot: $responsaveis,
                    contexto: [
                        'escola' => $responsaveis['escola'] ?? ['id' => (int) $turmaAvaliativa->id_escola, 'nome' => (string) ($turmaAvaliativa->escola?->nome ?? '')],
                        'turma' => $responsaveis['turma'] ?? ['id' => (int) $turmaAvaliativa->id, 'nome' => (string) $turmaAvaliativa->nome, 'turno' => (string) $turmaAvaliativa->turno],
                        'serie' => $responsaveis['serie'] ?? ['id' => (int) $turmaAvaliativa->id_serie, 'nome' => (string) ($turmaAvaliativa->serie?->nome ?? '')],
                    ],
                    origem: 'relacional',
                );
            }
        }

        $documento = AvaliacaoAlunoDocumento::query()
            ->where('avaliacao_id', $avaliacaoId)
            ->where('aluno_id', (int) $aluno->id)
            ->first();

        if (! $documento) {
            throw new RuntimeException('Documento avaliativo do aluno não encontrado.');
        }

        return new AvaliacaoDocumentoData(
            payload: (array) $documento->payload,
            responsaveisSnapshot: (array) ($documento->responsaveis_snapshot ?? []),
            contexto: [
                'escola' => (array) (($documento->responsaveis_snapshot ?? [])['escola'] ?? []),
                'turma' => (array) (($documento->responsaveis_snapshot ?? [])['turma'] ?? []),
                'serie' => (array) (($documento->responsaveis_snapshot ?? [])['serie'] ?? []),
            ],
            origem: 'json_legado',
        );
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
