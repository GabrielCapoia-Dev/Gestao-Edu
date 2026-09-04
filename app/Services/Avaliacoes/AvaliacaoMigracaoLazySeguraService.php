<?php

namespace App\Services\Avaliacoes;

use App\Models\AvaliacaoAlunoDocumento;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\Turma;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Proteções adicionais para a migração lazy do legado.
 *
 * O JSON antigo é fonte de recuperação: quando o contexto não pode ser
 * reconstruído sem perda ou ambiguidade, a conversão é interrompida. É melhor
 * manter a turma no JSON para correção controlada do que fabricar um relacional
 * incompleto.
 */
class AvaliacaoMigracaoLazySeguraService extends AvaliacaoMigracaoLazyService
{
    public function __construct(
        AvaliacaoTurmaCicloService $ciclos,
        private readonly TurmaAvaliacaoAlunoScopeService $escoposSeguros,
    ) {
        parent::__construct($ciclos, $escoposSeguros);
    }

    public function garantirTurma(int $avaliacaoId, int $turmaAvaliativaId): AvaliacaoTurmaCiclo
    {
        if ($ciclo = $this->cicloConhecido($avaliacaoId, $turmaAvaliativaId)) {
            return $ciclo;
        }

        $ciclo = AvaliacaoTurmaCiclo::query()
            ->where('avaliacao_id', $avaliacaoId)
            ->where('turma_avaliativa_id', $turmaAvaliativaId)
            ->first();

        if (
            $ciclo?->status === AvaliacaoTurmaCiclo::STATUS_CONCLUIDA
            || $ciclo?->operacional_inicializado_em !== null
        ) {
            return $ciclo;
        }

        $turmas = Turma::query()
            ->whereKey($turmaAvaliativaId)
            ->get();
        $escopos = $this->escoposSeguros->escoposPorTurma($turmas);
        $escopoAtual = $escopos[$turmaAvaliativaId] ?? null;

        if (is_array($escopoAtual)) {
            $turmaOrigemId = (int) $escopoAtual['turma_origem_id'];
            $documentos = AvaliacaoAlunoDocumento::query()
                ->where('avaliacao_id', $avaliacaoId)
                ->where('turma_id', $turmaOrigemId)
                ->get(['id', 'aluno_id', 'payload']);

            if ($documentos->isNotEmpty()) {
                $turmasCandidatas = Turma::query()
                    ->whereIn('id', DB::table('avaliacao_turma')
                        ->where('avaliacao_id', $avaliacaoId)
                        ->select('turma_id'))
                    ->get();
                $candidatas = collect($this->escoposSeguros->escoposPorTurma($turmasCandidatas))
                    ->filter(fn (array $escopo): bool => (int) $escopo['turma_origem_id'] === $turmaOrigemId)
                    ->keys()
                    ->map(fn ($id): int => (int) $id)
                    ->values();

                if ($candidatas->count() > 1) {
                    throw new RuntimeException(
                        'O JSON legado da turma física '.$turmaOrigemId
                        .' corresponde a mais de uma turma avaliativa ('.$candidatas->implode(', ')
                        .'). A conversão automática foi bloqueada para evitar duplicação de respostas.',
                    );
                }

                $this->validarReferenciasHistoricas($documentos);
            }
        }

        return parent::garantirTurma($avaliacaoId, $turmaAvaliativaId);
    }

    /** @param Collection<int, AvaliacaoAlunoDocumento> $documentos */
    private function validarReferenciasHistoricas(Collection $documentos): void
    {
        $professorIds = $documentos
            ->flatMap(fn (AvaliacaoAlunoDocumento $documento): array => collect($documento->pautasPayload())
                ->pluck('professor_id')
                ->merge(collect($documento->informacoesComplementaresPayload())->pluck('professor_id'))
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->all())
            ->unique()
            ->values();

        if ($professorIds->isNotEmpty()) {
            $existentes = DB::table('professores')
                ->whereIn('id', $professorIds->all())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id);
            $ausentes = $professorIds->diff($existentes);

            if ($ausentes->isNotEmpty()) {
                throw new RuntimeException(
                    'O JSON legado referencia professor(es) que não existem mais: '
                    .$ausentes->implode(', ')
                    .'. A conversão foi bloqueada para preservar a autoria histórica.',
                );
            }
        }

        $componenteIds = $documentos
            ->flatMap(fn (AvaliacaoAlunoDocumento $documento): array => collect($documento->pautasPayload())
                ->pluck('componente_curricular_id')
                ->merge(collect($documento->informacoesComplementaresPayload())->keys())
                ->filter(fn ($id): bool => (int) $id > 0)
                ->map(fn ($id): int => (int) $id)
                ->all())
            ->unique()
            ->values();

        if ($componenteIds->isNotEmpty()) {
            $existentes = DB::table('componentes_curriculares')
                ->whereIn('id', $componenteIds->all())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id);
            $ausentes = $componenteIds->diff($existentes);

            if ($ausentes->isNotEmpty()) {
                throw new RuntimeException(
                    'O JSON legado referencia componente(s) que não existem mais: '
                    .$ausentes->implode(', ')
                    .'. A conversão foi bloqueada para preservar o contexto histórico.',
                );
            }
        }
    }
}
